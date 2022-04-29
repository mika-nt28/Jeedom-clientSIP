import subprocess
import os,re,copy
import logging
import sys
import argparse
from datetime import *
import signal
import json
import traceback
import globals
import threading
try:
	from jeedom.jeedom import *
except ImportError:
	print("Error: importing module from jeedom folder")
	sys.exit(1)
  
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
				if message['cmd'] == 'sendRTSP':
          pass
		except Exception as e:
			logging.error("Exception on socket : %s" % str(e))
			logging.debug(traceback.format_exc())
		time.sleep(cycle)	
def listen():
	threads = []
	currentThreads = None
	jeedom_socket.open()
	thread.start_new_thread(read_socket,(globals.cycle,))
	while(True):
    #Lanceer le serveur RSTP
    pass 
	shutdown()  
def shutdown():
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

jeedom_utils.set_log_level(globals.log_level)
logging.info("[" + globals.camera + "] Start Face Detection Daemon for Jeedom plugin")
logging.info("[" + globals.camera + "] Log level : " + str(globals.log_level))
logging.info("[" + globals.camera + "] PID file : " + str(globals.pidfile))
logging.info("[" + globals.camera + "] Apikey : " + str(globals.apikey))
logging.info("[" + globals.camera + "] Callback : " + str(globals.callback))
logging.info("[" + globals.camera + "] Cycle : " + str(globals.cycle))
logging.info("[" + globals.camera + "] Socket connexion : " + str(globals.sockethost) + ":" + str(globals.socketport))
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
