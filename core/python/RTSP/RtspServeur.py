import numpy as np
import cv2
from socket import *

class RtspServeur():
  def __init__(self,port):
    self.addr = ('0.0.0.0 ', port)
    self.socket = socket(af_inet, sock_dgram)
    self.socket.bind(self.addr)
    self.socket.Setblocking(0)
  def stream(self):
    data = None
    try:
      data, _ = self.socket.recvfrom(921600)
      return np.frombuffer(data, dtype='uint8')
    except BlockingIOError as e:
      pass
