import subprocess
import os,re,copy
import logging
import sys
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
try:
	from RTSP.RTSP import *
except ImportError:
	print("Error: importing module from RTSP folder")
	sys.exit(1)
Server = None
def read_socket(cycle):
	while True :
		try:
			global JEEDOM_SOCKET_MESSAGE
			if not JEEDOM_SOCKET_MESSAGE.empty():
				logging.debug("SOCKET-READ------Message received in socket JEEDOM_SOCKET_MESSAGE")
				message = JEEDOM_SOCKET_MESSAGE.get().decode('utf-8')
				message =json.loads(message)
				if message['apikey'] != globals.apikey:
					logging.error("Invalid apikey from socket : " + str(message))
					return
				logging.debug("Received command from jeedom : "+str(message['cmd']))
				Client = RtspClient(message['host'],message['port'])
				if message['cmd'] == 'sendDTMF':
					pass
				if message['cmd'] == 'playMessage':
					for message['Message'] in Message:
						Client.streamCamera(self,camera=0)
						time.sleep(message['pause'])
		except Exception as e:
			logging.error("Exception on socket : %s" % str(e))
			logging.debug(traceback.format_exc())
		time.sleep(cycle)	
def listen():
	global Server
	jeedom_socket.open()
	thread.start_new_thread(read_socket,(globals.cycle,))
	Server = RtspServeur(globals.RTSPport)
	while(True):
		Recive = Server.stream() 
		#Analyse DMTF
		#Analyse SpeachToText
	shutdown()  
def shutdown():
	global Server
	logging.debug("Shutdown")
	logging.debug("Removing PID file " + str(globals.pidfile))
	try:
		os.remove(globals.pidfile)
	except:
		pass
	logging.debug("Exit 0")
	sys.stdout.flush()
	os._exit(0)

parser = argparse.ArgumentParser(description='SIP RTSP serveur Daemon for Jeedom plugin')
parser.add_argument("--loglevel", help="Niveau de log daemon", type=str)
parser.add_argument("--pidfile", help="Value to write", type=str)
parser.add_argument("--callback", help="Url to return detection", type=str)
parser.add_argument("--apikey", help="Identification jeedom plugin", type=str)
parser.add_argument("--socketport", help="Socket Port", type=str)
parser.add_argument("--sockethost", help="Socket Host", type=str)
parser.add_argument("--RTSPport", help="RTSP Port", type=str)
args = parser.parse_args()

if args.loglevel:
	globals.log_level = args.loglevel
if args.pidfile:
	globals.pidfile = args.pidfile
if args.callback:
	globals.callback = args.callback
if args.apikey:
	globals.apikey = args.apikey
if args.socketport:
	globals.socketport = int(args.socketport)
if args.sockethost:
	globals.sockethost = args.sockethost
if args.RTSPport:
	globals.RTSPport = int(args.RTSPport)

jeedom_utils.set_log_level(globals.log_level)
logging.info("Start Face Detection Daemon for Jeedom plugin")
logging.info("Log level : " + str(globals.log_level))
logging.info("PID file : " + str(globals.pidfile))
logging.info("Apikey : " + str(globals.apikey))
logging.info("Callback : " + str(globals.callback))
logging.info("Cycle : " + str(globals.cycle))
logging.info("Deamon Socket connexion : " + str(globals.sockethost) + ":" + str(globals.socketport))
logging.info("RTSP connexion : 127.0.0.1:" + str(globals.RTSPport))
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
