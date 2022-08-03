import sys, socket
from ServerWorker import ServerWorker

class RtspServeur():
	def __init__(self,port):
		self.port = port
	def waitClient(self):
		self.socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
		self.socket.bind(('', self.port))
		self.socket.listen(5)  
		# Receive client info (address,port) through RTSP/TCP session
		while True:
			clientInfo = {}
			clientInfo['rtspSocket'] = self.socket.accept()
			ServerWorker(clientInfo).run()	
	
