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
import asyncio
import globals
import numpy as np

try:
	from jeedom.jeedom import *
except ImportError:
	print("Error: importing module from jeedom folder")
	sys.exit(1)
from pyVoIP import * #https://pyvoip.readthedocs.io/en/v1.6.0/
from pyVoIP.VoIP import * 
from pyVoIP.SIP import *
import speech_recognition as sr
import wave
from pydub import AudioSegment
from pydub.silence import split_on_silence

Phone = None
Call = None

def read_socket(cycle):
	global Phone,Call
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
					globals.timeout = time.time()
					globals.dial = 'To'
					Call = Phone.call(message['Numero'])
					thread.start_new_thread(callAnswered,(False,))
				if message['cmd'] == 'answer':
					globals.CallMessages = message['Message']
					globals.DTMFList = message['DTMFList']
					for DTMFMessages in globals.DTMFList:
						globals.CallMessages.append(DTMFMessages['message'])
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
def getCallStatus():
	global Call
	action = {}
	if globals.CallStatus != Call.state.value:
		globals.CallStatus = Call.state.value
		action['CallStatus']= globals.CallStatus
		globals.JEEDOM_COM.add_changes('devices::'+globals.jeedomId,action)
def callAnswered(dial):
	global Call
	try:
		action = {}
		while Call.state != CallState.ANSWERED:
			if time.time() - globals.timeout > 30:
				logging.info("30s sans envenement, nous quitton la conversation")
				break
			getCallStatus()
			if dial:
				Call.answer()
			time.sleep(0.1)
		thread.start_new_thread(writeAudio,())
		thread.start_new_thread(waitDTMF,())
		thread.start_new_thread(readAudio,())
		while(Call.state == CallState.ANSWERED):
			getCallStatus()
			if time.time() - globals.timeout > 30:
				#if dial:
				Call.hangup()
				logging.info("30s sans envenement, nous quittons la conversation")
				break
			time.sleep(1)
		getCallStatus()
		while Call.state != CallState.ENDED:
			getCallStatus()
	except Exception as e:
		logging.error("Erreur sur le processus de conversation : %s" % str(e))
		logging.debug(traceback.format_exc())
def _picotts_exe(args, sync=False):
	cmd = ['pico2wave','-l', globals.Voice,]
	cmd.extend(args)
	logging.debug('picotts: executing %s' % repr(cmd))
	p = subprocess.Popen(cmd,stdout=subprocess.PIPE,stderr=subprocess.STDOUT)
	res = iter(p.stdout.readline, b'')
	if not sync:
		return res
	res2 = []
	for line in res:
		res2.append(line)
	return res2
def TextToSpeak(Message):
	waveFile = '/var/www/html/tmp/TTS.wav'
	audio = []
	duree = 0
	try:
		if os.path.isfile(waveFile):
			os.remove(waveFile)
		txte = Message.encode('utf8')
		args = ['-w', waveFile, txte]
		_picotts_exe(args, sync=True)
		os.chmod(waveFile, 0o777)
			#Note: Audio must be 8 bit, 8000Hz, and Mono/1 channel. You can accomplish this in a free program called Audacity. To make an audio recording Mono, go to Tracks > Mix > Mix Stereo Down to Mono. To make an audio recording 8000 Hz, go to Tracks > Resample… and select 8000, then ensure that your ‘Project Rate’ in the bottom left is also set to 8000. To make an audio recording 8 bit, go to File > Export > Export as WAV, then change ‘Save as type:’ to ‘Other uncompressed files’, then set ‘Header:’ to ‘WAV (Microsoft)’, then set the ‘Encoding:’ to ‘Unsigned 8-bit PCM’
		sound = AudioSegment.from_file(waveFile)
		sound = sound.set_frame_rate(8000)
		sound = sound.set_sample_width(1)
		sound = sound.set_channels(1)
		audio = sound.get_array_of_samples()
		duree = sound.duration_seconds
		with wave.open(waveFile,'rb') as wav:
			logging.debug("parmaetr audio : %s" % str(wav.getparams()))
	except Exception as error:
		logging.error("Erreur TTS: %s" % str(error))
	return audio,duree
def writeAudio():
	global Call
	jeedomGetMessages()	
	while(Call.state == CallState.ANSWERED):
		if globals.CallMessages != None:
			audioPlay()
		time.sleep(1)
def readAudio():
	sound = AudioSegment.empty()
	audio2 = bytearray()
	while(Call.state == CallState.ANSWERED):
		try:
			voice = None
			audio = Call.read_audio(length=160, blocking=True)
			sound += AudioSegment(audio,frame_rate=8000,sample_width=1,channels=1)
			audio2.extend(audio)
			#logging.debug("Ecoute audio data: %s" % str(audio))
			chunks = split_on_silence (sound, min_silence_len = 2000,silence_thresh = -16)
			if len(chunks) > 1:
				sound = chunks[1]
				voice = chunks[0]
			if voice != None:
				thread.start_new_thread(SpeakToText,(Voice))
		except sr.RequestError as e:
			logging.debug("Could not request results; {0}".format(e))
		except sr.UnknownValueError:
			logging.debug("unknown error occurred")
		except Exception as e:
			logging.error("Erreur sur le processus de conversation : %s" % str(e))
			logging.debug(traceback.format_exc())
		time.sleep(1)
	waveFile = '/var/www/html/tmp/Speak.wav'
	logging.debug("Ecoute audio data: %s" % str(audio2))
	sound = AudioSegment(audio2,frame_rate=8000,sample_width=1,channels=1)
	sound.export(waveFile,format='wav')
def SpeakToText(Voice):
	Recognizer = sr.Recognizer()
	try:
		CallText = Recognizer.recognize_google(Voice, language = globals.Voice)
		CallText = CallText.lower()
		CallText = ''
		if CallText != '':
			action = {}
			action['SpeakToText']= CallText
			action['CallStatus']= call.state.value
			action['Numero']= ''
			globals.JEEDOM_COM.add_changes('devices::'+globals.jeedomId,action)
	except sr.RequestError as e:
		logging.debug("Could not request results; {0}".format(e))
	except sr.UnknownValueError:
		logging.debug("unknown error occurred")
	except Exception as e:
		logging.error("Erreur sur le processus de conversation : %s" % str(e))
		logging.debug(traceback.format_exc())
def audioPlay():
	global Call
	for Message in globals.CallMessages:
		logging.info("TTS: %s" % Message)
		data, duree = TextToSpeak(Message)
		logging.info("Durée du message: %s" % duree)
		Call.write_audio(data)
		start = time.time() 
		temps = time.time() - start
		while temps <= duree and Call.state == CallState.ANSWERED:
			globals.timeout = time.time()
			temps = time.time() - start
			#logging.info("Temps écoulé: %s" % temps)
			time.sleep(0.1)
		time.sleep(1)
	globals.CallMessages = None
def waitDTMF():
	global Call
	try:
		action = {}
		logging.info("Attente de DTMF")
		while Call.state == CallState.ANSWERED:
			dtmf = Call.get_dtmf()
			if dtmf != '':
				globals.timeout = time.time()
				action['DTMF']= dtmf
				action['Numero']= Call.request.headers[globals.dial]['number']
				globals.JEEDOM_COM.add_changes('devices::'+globals.jeedomId,action)
			time.sleep(0.1)
	except Exception as e:
		logging.error("Erreur sur le processus de conversation : %s" % str(e))
		logging.debug(traceback.format_exc())
def jeedomGetMessages():
	action = {}
	logging.info("Attente de Message")
	globals.CallMessages = None
	if globals.dial == 'From':
		action['Answer']= 'InCallEvent'
	else:
		action['Answer']= 'OutCallEvent'
	action['Numero']= Call.request.headers[globals.dial]['number']
	globals.JEEDOM_COM.add_changes('devices::'+globals.jeedomId,action)
def answer(_call):
	global Call
	try:
		Call = _call
		globals.timeout = time.time()
		globals.dial = 'From'
		thread.start_new_thread(callAnswered,(True,))
	except InvalidStateError:
		pass
	except:
		Call.hangup()
		getCallStatus()
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
