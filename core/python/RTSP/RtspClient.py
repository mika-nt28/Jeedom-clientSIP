import sys
from tkinter import Tk
from ClientWorker import ClientWorker
class RtspClient():
	def __init__(self,serverAddr,rtpPort):
		self.serverAddr = serverAddr
		self.serverPort = serverPort
		self.rtpPort = rtpPort
	def stream(self,fileName):
		root = Tk()
		# Create a new client
		app = Client(root, self.serverAddr, self.serverPort, self.rtpPort, fileName)
		app.master.title("RTPClient")	
		root.mainloop()
