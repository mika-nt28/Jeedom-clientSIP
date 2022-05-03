import numpy as np
import socket

class RtspServeur():
	def __init__(self,port):
		self.socket = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
		self.socket.bind(('0.0.0.0', port))
		self.socket.setblocking(0)
	def stream(self):
		data = None
		try:
			data, _ = self.socket.recvfrom(921600)
			return np.frombuffer(data, dtype='uint8')
		except BlockingIOError as e:
			pass
