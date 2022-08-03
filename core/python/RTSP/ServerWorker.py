from random import randint
import sys, traceback, threading, socket

from RTSP.VideoStream import *
from RTSP.RtpPacket import *

class ServerWorker:
	SETUP = 'SETUP'
	PLAY = 'PLAY'
	PAUSE = 'PAUSE'
	TEARDOWN = 'TEARDOWN'
	
	INIT = 0
	READY = 1
	PLAYING = 2
	state = INIT

	OK_200 = 0
	FILE_NOT_FOUND_404 = 1
	CON_ERR_500 = 2
	
	clientInfo = {}
	
	DTMF_TABLE = {
		'1': [1209, 697],
		'2': [1336, 697],
		'3': [1477, 697],
		'A': [1633, 697],

		'4': [1209, 770],
		'5': [1336, 770],
		'6': [1477, 770],
		'B': [1633, 770],

		'7': [1209, 852],
		'8': [1336, 852],
		'9': [1477, 852],
		'C': [1633, 852],

		'*': [1209, 941],
		'0': [1336, 941],
		'#': [1477, 941],
		'D': [1633, 941]
	} 
	
	def __init__(self, clientInfo):
		self.clientInfo = clientInfo
		
	def run(self):
		threading.Thread(target=self.recvRtspRequest).start()
	
	def recvRtspRequest(self):
		"""Receive RTSP request from the client."""
		connSocket = self.clientInfo['rtspSocket'][0]
		while True:            
			data = connSocket.recv(256)
			if data:
				print ("DATA RECEIVED: \n", data)
				self.processRtspRequest(data)
	
	def processRtspRequest(self, data):
		"""Process RTSP request sent from the client."""
		# Get the request type
		request = data.split('\n')
		line1 = request[0].split(' ')
		requestType = line1[0]
		
		# Get the media file name
		filename = line1[1]
		
		# Get the RTSP sequence number 
		seq = request[1].split(' ')
		
		# Process SETUP request
		if requestType == self.SETUP:
			if self.state == self.INIT:
				# Update state
				print ("PROCESSING SETUP\n")
				
				try:
					self.clientInfo['videoStream'] = VideoStream(filename)
					self.state = self.READY
				except IOError:
					self.replyRtsp(self.FILE_NOT_FOUND_404, seq[1])
				
				# Generate a randomized RTSP session ID
				self.clientInfo['session'] = randint(100000, 999999)
				
				# Send RTSP reply
				self.replyRtsp(self.OK_200, seq[1])
				
				# Get the RTP/UDP port from the last line
				self.clientInfo['rtpPort'] = request[2].split(' ')[3]
		
		# Process PLAY request 		
		elif requestType == self.PLAY:
			if self.state == self.READY:
				print ("PROCESSING PLAY\n")
				self.state = self.PLAYING
				
				# Create a new socket for RTP/UDP
				self.clientInfo["rtpSocket"] = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
				
				self.replyRtsp(self.OK_200, seq[1])
				
				# Create a new thread and start sending RTP packets
				self.clientInfo['event'] = threading.Event()
				self.clientInfo['worker']= threading.Thread(target=self.sendRtp) 
				self.clientInfo['worker'].start()
		
		# Process PAUSE request
		elif requestType == self.PAUSE:
			if self.state == self.PLAYING:
				print ("PROCESSING P A U S E\n")
				self.state = self.READY
				
				self.clientInfo['event'].set()
			
				self.replyRtsp(self.OK_200, seq[1])
		
		# Process TEARDOWN request
		elif requestType == self.TEARDOWN:
			print ("PROCESSING TEARDOWN\n")

			self.clientInfo['event'].set()
			
			self.replyRtsp(self.OK_200, seq[1])
			
			# Close the RTP socket
			self.clientInfo['rtpSocket'].close()
			
	def sendRtp(self):
		"""Send RTP packets over UDP."""
		while True:
			self.clientInfo['event'].wait(0.05) 
			
			# Stop sending if request is PAUSE or TEARDOWN
			if self.clientInfo['event'].isSet(): 
				break 
				
			data = self.clientInfo['videoStream'].nextFrame()
			if data: 
				frameNumber = self.clientInfo['videoStream'].frameNbr()
				try:
					address = self.clientInfo['rtspSocket'][1][0]
					port = int(self.clientInfo['rtpPort'])
					self.clientInfo['rtpSocket'].sendto(self.makeRtp(data, frameNumber),(address,port))
				except:
					print ("Connection Error")
					#print '-'*60
					#traceback.print_exc(file=sys.stdout)
					#print '-'*60

	def makeRtp(self, payload, frameNbr):
		"""RTP-packetize the video data."""
		version = 2
		padding = 0
		extension = 0
		cc = 0
		marker = 0
		pt = 26 # MJPEG type
		seqnum = frameNbr
		ssrc = 0 
		
		rtpPacket = RtpPacket()
		
		rtpPacket.encode(version, padding, extension, cc, seqnum, marker, pt, ssrc, payload)
		
		return rtpPacket.getPacket()
		
	def replyRtsp(self, code, seq):
		"""Send RTSP reply to the client."""
		if code == self.OK_200:
			#print "200 OK"
			reply = 'RTSP/1.0 200 OK\nCSeq: ' + seq + '\nSession: ' + str(self.clientInfo['session'])
			connSocket = self.clientInfo['rtspSocket'][0]
			connSocket.send(reply)
		
		# Error messages
		elif code == self.FILE_NOT_FOUND_404:
			print ("404 NOT FOUND")
		elif code == self.CON_ERR_500:
			print ("500 CONNECTION ERROR")
	def DTMF_detetor(self,data):
		if len(data) == 0:
			return None
		# Calculate fourier trasform of data
		FourierTransformOfData = np.fft.fft(data, 20000)
		# Convert fourier transform complex number to integer numbers
		for i in range(len(FourierTransformOfData)):
			FourierTransformOfData[i] = int(np.absolute(FourierTransformOfData[i]))
			# Calculate lower bound for filtering fourier trasform numbers
			LowerBound = 20 * np.average(FourierTransformOfData)
			# Filter fourier transform data (only select frequencies that X(jw) is greater than LowerBound)
			FilteredFrequencies = []
			for i in range(len(FourierTransformOfData)):
				if (FourierTransformOfData[i] > LowerBound):
					FilteredFrequencies.append(i)
					# Detect and print pressed button
					for char, frequency_pair in DTMF_TABLE.items():
						if (isNumberInArray(FilteredFrequencies, frequency_pair[0]) and isNumberInArray(FilteredFrequencies, frequency_pair[1])):
							return char
		return None
