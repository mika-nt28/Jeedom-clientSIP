import numpy as np
import cv2
from socket import *
import sounddevice as sd
class RtspClient():
  def __init__(self,url,port):
    self.addr = (url, port)
    self.socket = socket(af_inet, sock_dgram) # create UDP socket
  def stream(self,send_data):
   s.sendto(send_data,  self.addr)
  def streamMessage(self,message):
    pass
  def streamCamera(self,camera=0):
    cap = cv2.VideoCapture(camera)
    cap.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
    cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
    while True:
      _, img = cap.read()
      img = cv2.flip(img, 1)
      _, send_data = cv2.imencode('.jpg', img, [cv2.IMWRITE_JPEG_QUALITY, 50])
      self.stream(send_data):
