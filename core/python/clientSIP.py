import subprocess
import os,re,copy
import logging
import sys,io
import argparse
from datetime import *
import signal
import json
import traceback
import threading
import globals

try:
	from jeedom.jeedom import *
except ImportError:
	print("Error: importing module from jeedom folder")
	sys.exit(1)
from pyVoIP import * #https://pyvoip.readthedocs.io/en/v1.6.0/
from pyVoIP.VoIP import * 
from pyVoIP.SIP import *
from picotts import PicoTTS
import wave

Phone = None
def read_socket(cycle):
	global Phone
	global JEEDOM_SOCKET_MESSAGE
	while True :
		try:
			if not JEEDOM_SOCKET_MESSAGE.empty():
				logging.debug("SOCKET-READ------Message received in socket JEEDOM_SOCKET_MESSAGE")
				message = JEEDOM_SOCKET_MESSAGE.get().decode('utf-8')
				message =json.loads(message)
				if message['apikey'] != globals.apikey:
					logging.error("Invalid apikey from socket : " + str(message))
					return
				logging.debug("Received command from jeedom : " + str(message['cmd']))
				if message['cmd'] == 'call':
					logging.debug("Composition du numero : " + str(message['Numero']))
					call = Phone.call(message['Numero'])
					callAnswered(call,False)
					audioPlay(call, message['Message'],int(message['pause']))
					waitDTMF(call)
					if call.state == CallState.ANSWERED:
						call.hangup()
					getCallStatus(call)
				if message['cmd'] == 'answer':
					globals.InCallMessage = message['Message']
		except Exception as e:
			logging.error("Exception on socket : %s" % str(e))
			logging.debug(traceback.format_exc())
		time.sleep(cycle)	
def listen():
	global Phone
	try:
		jeedom_socket.open()
		thread.start_new_thread(read_socket,(globals.cycle,))
		Phone=VoIPPhone(globals.serverhost, globals.serverport,globals.username,globals.userpass, myIP=globals.clienthost, callCallback=answer, sipPort=globals.clientport)
		Phone.DEBUG = True
		Phone.start()
		while True:
			if globals.PhoneStatus != Phone.get_status().value:
				globals.PhoneStatus = Phone.get_status().value
				action = {}
				action['RegStatus']= globals.PhoneStatus
				globals.JEEDOM_COM.add_changes('devices::'+globals.jeedomId,action)
			time.sleep(1)
	except:
		shutdown()
def shutdown():
	global Phone
	Phone.stop()
	action = {}
	globals.PhoneStatus = Phone.get_status().value
	action = {}
	action['RegStatus']= globals.PhoneStatus
	globals.JEEDOM_COM.add_changes('devices::'+globals.jeedomId,action)
	logging.debug("Shutdown")
	logging.debug("Removing PID file " + str(globals.pidfile))
	try:
		os.remove(globals.pidfile)
	except:
		pass
	logging.debug("Exit 0")
	sys.stdout.flush()
	os._exit(0)
def getCallStatus(call):
	action = {}
	if globals.CallStatus != call.state.value:
		globals.CallStatus = call.state.value
		action['CallStatus']= globals.CallStatus
		globals.JEEDOM_COM.add_changes('devices::'+globals.jeedomId,action)
		time.sleep(0.1)
def callAnswered(call, dial):
	action = {}
	while call.state != CallState.ANSWERED:
		getCallStatus(call)
		if dial:
			call.answer()
		time.sleep(0.1)
	getCallStatus(call)
def audioPlay(call, Messages, Wait):
	for Message in Messages:
		logging.info("TTS: %s" % Message)
		picotts = PicoTTS()
		picotts.voice = 'fr-FR'
		wavs = picotts.synth_wav(Message)
		params = (1, 2, 8000, 0, 'NONE', 'not compressed')
		with wave.open('/var/www/html/tmp/sample.wav', 'wb') as audio_file:
			audio_file.setparams(params)
			audio_file.writeframes(wavs)
		os.chmod('/var/www/html/tmp/sample.wav', 0o777)
		wav = wave.open('/var/www/html/tmp/sample.wav', 'rb')
		logging.info("sampwidth: %s" % str(wav.getsampwidth()))
		logging.info("nchannels: %s" % str(wav.getnchannels()))
		logging.info("framerate: %s" % str(wav.getframerate()))
		logging.info("nframes: %s" % str(wav.getnframes()))
		frames = wav.getnframes()
		data = wav.readframes(frames)
		wav.close()

		call.write_audio(data)  # This writes the audio data to the transmit buffer, this must be bytes.

		start = time.time() 
		duree = frames / 8000  # frames/8000 is the length of the audio in seconds. 8000 is the hertz of PCMU.
		temps = time.time() - start
		logging.info("Durée du message: %s" % duree)
		while temps <= duree and call.state == CallState.ANSWERED:
			temps = time.time() - start
			#logging.info("Temps écoulé: %s" % temps)
			time.sleep(0.1)
		time.sleep(Wait)
def waitDTMF(call):
	action = {}
	time.sleep(1)
	timeWait = time.time()
	logging.info("Attente de DTMF")
	while call.state == CallState.ANSWERED:
		getCallStatus(call)
		dtmf = call.get_dtmf()
		if dtmf != '':		
			timeWait = time.time()
			action['DTMF']= dtmf
			#action['CallStatus']= call.state.value
			action['Numero']= ''
			globals.JEEDOM_COM.add_changes('devices::'+globals.jeedomId,action)
		if time.time() - timeWait > 30:
			logging.info(">30s sans DTMF: Nous quitton la conversation")
			break # On quite la boucle d'attente DTMF 30s apres le dernier recus
		time.sleep(0.1)
def waitInCallMessage(call):
	action = {}
	logging.info("Attente de Message")
	if call.state == CallState.ANSWERED:
		getCallStatus(call)
		globals.InCallMessage == None
		action['Answer']= True
		action['Numero']= ''
		#action['CallStatus']= call.state.value
		globals.JEEDOM_COM.add_changes('devices::'+globals.jeedomId,action)
		while globals.InCallMessage == None:
			logging.info("Attente de Message %s" % str(globals.InCallMessage))
			time.sleep(0.1)
		audioPlay(call, globals.InCallMessage, 2)
		globals.InCallMessage == None
def answer(call):
	try:
		callAnswered(call, True)
		waitInCallMessage(call)
		waitDTMF(call)
		if call.state == CallState.ANSWERED:
			call.hangup()
		getCallStatus(call)
	except InvalidStateError:
		pass
	except:
		call.hangup()
		getCallStatus(call)
parser = argparse.ArgumentParser(description='SIP RTSP serveur Daemon for Jeedom plugin')
parser.add_argument("--loglevel", help="Niveau de log daemon", type=str)
parser.add_argument("--pidfile", help="Value to write", type=str)
parser.add_argument("--callback", help="Url to return detection", type=str)
parser.add_argument("--apikey", help="Identification jeedom plugin", type=str)
parser.add_argument("--jeedomId", help="Identification équipement jeedom", type=str)
parser.add_argument("--socketport", help="Socket Port", type=str)
parser.add_argument("--sockethost", help="Socket Host", type=str)
parser.add_argument("--serverport", help="Server Port", type=str)
parser.add_argument("--serverhost", help="Sertver Host", type=str)
parser.add_argument("--username", help="User name", type=str)
parser.add_argument("--userpass", help="User password", type=str)
parser.add_argument("--clientport", help="Client Port", type=str)
parser.add_argument("--clienthost", help="Client host", type=str)
args = parser.parse_args()

if args.loglevel:
	globals.log_level = args.loglevel
if args.pidfile:
	globals.pidfile = args.pidfile
if args.callback:
	globals.callback = args.callback
if args.apikey:
	globals.apikey = args.apikey
if args.jeedomId:
	globals.jeedomId = args.jeedomId
if args.socketport:
	globals.socketport = int(args.socketport)
if args.sockethost:
	globals.sockethost = args.sockethost
if args.serverport:
	globals.serverport = int(args.serverport)
if args.serverhost:
	globals.serverhost = args.serverhost
if args.username:
	globals.username = args.username
if args.userpass:
	globals.userpass = args.userpass	
if args.clientport:
	globals.clientport = int(args.clientport)
if args.clienthost:
	globals.clienthost = args.clienthost

jeedom_utils.set_log_level(globals.log_level)
logging.info("Start Face Detection Daemon for Jeedom plugin")
logging.info("Log level : " + str(globals.log_level))
logging.info("PID file : " + str(globals.pidfile))
logging.info("Apikey : " + str(globals.apikey))
logging.info("Callback : " + str(globals.callback))
logging.info("Cycle : " + str(globals.cycle))
logging.info("Deamon Socket connexion : " + str(globals.sockethost) + ":" + str(globals.socketport))
logging.info("Server connexion : "+ str(globals.username)+":"+ str(globals.userpass)+"@"+ str(globals.serverhost)+":" + str(globals.serverport))
logging.info("Client connexion : "+ str(globals.clienthost)+":" + str(globals.clientport))
try:
	jeedom_utils.write_pid(str(globals.pidfile))
	globals.JEEDOM_COM = jeedom_com(apikey = globals.apikey,url = globals.callback,cycle=0)
	if not globals.JEEDOM_COM.test():
		logging.error('GLOBAL------Network communication issues. Please fix your Jeedom network configuration.')
		shutdown()
	jeedom_socket = jeedom_socket(port=globals.socketport,address=globals.sockethost)
	listen()
except Exception as e:
	logging.error("Fatal error : " + str(e))
	logging.debug(traceback.format_exc())
	shutdown()
