<?php
require_once dirname(__FILE__) . '/Exception/SIPException.php';
require_once dirname(__FILE__) . '/Header/Header.php';
require_once dirname(__FILE__) . '/Body/Body.php';

require_once dirname(__FILE__) . '/Message.php';
require_once dirname(__FILE__) . '/Request.php';
require_once dirname(__FILE__) . '/Response.php';

class client{ 
	public function __construct($src_ip = null, $src_port = null ,$ClientNumber,$Username,$Password,$userAgent,$socket_bind = true)	{
		$this->_cHost = $src_ip;
		$this->_cPort = $src_port;
		$this->_ClientNumber = $ClientNumber;
		$this->_CallNumber = 0;
		$this->_Username = $Username;
		$this->_Password = $Password;
		$this->_userAgent = $userAgent;
		$this->_csec = 1;
      
		$this->_Stun=config::byKey('Stun', 'clientSIP');
		$this->_sHost=config::byKey('Host', 'clientSIP');
		$this->_sPort=config::byKey('Port', 'clientSIP');
		$this->createSocket($socket_bind);
	}
	public function __destruct(){
		$this->closeSocket();
	}
	public function isConnect(){
		return feof($this->socket);
	}
	private function createSocket($socket_bind){ 
		if (!$this->socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)){
			$err_no = socket_last_error($this->socket);
			log::add('clientSIP','error',socket_strerror($err_no));
			die();
		}
		if($socket_bind){
			if (!@socket_bind($this->socket, $this->_cHost, $this->_cPort)){
				$err_no = socket_last_error($this->socket);
				log::add('clientSIP','error',"Failed to bind ".$this->_cHost.":".$this->_cPort." ".socket_strerror($err_no));
				die();
			}
		}
		/*if (!@socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>10,"usec"=>0))){
			$err_no = socket_last_error($this->socket);
			log::add('clientSIP','error',socket_strerror($err_no));
			die();
		}*/
		if (!@socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array("sec"=>5,"usec"=>0))){
			$err_no = socket_last_error($this->socket);
			log::add('clientSIP','error',socket_strerror($err_no));
			die();
		}
	}
	private function closeSocket(){
		socket_close($this->socket);
	}
  	private function send($data){
   		if (!@socket_sendto($this->socket, $data, strlen($data), 0, $this->_sHost, $this->_sPort)){
			$err_no = socket_last_error($this->socket);
			log::add('clientSIP','error',"Impossible d'envoyer la data sur ".$this->_sHost.":".$this->_sPort.". Source IP ".$this->_cHost.", source port: ".$this->_cPort.". ".socket_strerror($err_no));
			die();
		}
		$Monitor['Time'] = date('d/m/Y H:i:s');
		$Monitor['Mode'] = '[TX]';
		$Monitor['Message'] = $data;
		event::add('clientSIP::monitor', json_encode($Monitor));
	}
	private function read()	{
		$from = "";
		$port = 0;
		$data = null;
		if (!@socket_recvfrom($this->socket, $data, 10000, 0, $from, $port)){
			$err_no = socket_last_error($this->socket);
			log::add('clientSIP','error',"Impossible de recevoire la data ".$this->_sHost.":".$this->_sPort.". Source IP ".$this->_cHost.", source port: ".$this->_cPort.". ".socket_strerror($err_no));
			die();
		}
		$Monitor['Time'] = date('d/m/Y H:i:s');
		$Monitor['Mode'] = '[RX]';
		$Monitor['Message'] = $data;
		event::add('clientSIP::monitor', json_encode($Monitor));
		return SIPMessage::parse($data);
	}
	private function getReponse($message){
		$this->_csec = $message->cSeq->sequence;
		switch($message->code){
			case '100':
				$message = $this->read();
				return $this->getReponse($message);
				//$this->reply(100,'Trying');
			break;
			case '180':
				$message = $this->read();
				return $this->getReponse($message);
				//$this->reply(180,'Ringing');
			break;
			case '200':
				switch($this->method){
					case 'INVITE':
						$this->method = 'ACK';
						$request = $this->formatRequest();
						$request->from = $message->from;
						$request->to = $message->to;
						$request->callId = $message->callId;
						$request->cSeq = $message->cSeq;
						//$request->cSeq->sequence += 1;
						$request->cSeq->method = $this->method;
						$request->contentType = new SingleValueWithParamsHeader;
						$request->contentType->value ='application/sdp';
						$request->body = $this->clientSDP($request);
						$this->send($request->render());
					break;
					case 'REGISTER':
					case 'ACK':
					break;
					default:
						$this->reply($message,200);
					break;
				}
				return $message;
			break;
			case '407':
		            	if($message->cSeq->method == $this->method){
					$request = $this->formatRequest();
					$request->from = $message->from;
					$request->proxyAuthorization = $message->proxyAuthenticate;
					$request->proxyAuthorization->values[0]->params['username'] = $this->_Username;
					$request->proxyAuthorization->values[0]->params['password'] = $this->_Password;
					$request->proxyAuthorization->values[0]->params['uri'] = $request->uri;
					$request->proxyAuthorization->values[0]->params['method'] = $this->method;
					$request->cSeq = $message->cSeq;
					$request->cSeq->sequence += 1;
					$request->callId = $message->callId;
					switch($this->method){
						case 'INVITE':
							$request->contentType = new SingleValueWithParamsHeader;
							$request->contentType->value ='application/sdp';
							$request->body = $this->clientSDP($request);
						break;
					}
					$this->send($request->render());
				}
				$message = $this->read();
				return $this->getReponse($message);
			break;
			case '401':
				/*$this->cseq++;
				$this->authWWW();
				$data = $this->formatRequest();
				$this->sendData($data);
				$this->readMessage();*/
			break;
			case '486':
			break;
		}
	}
	public function listen(){
		return $this->read();
	}
	public function reply($message, $code){
		$response = new Response;
		$response->version = $message->version;
		$response->code = $code;

		$response->via = new ViaHeader;
		$response->via->values[0] = new ViaValue;
		$response->via->values[0]->protocol = 'SIP';
		$response->via->values[0]->version = '2.0';
		$response->via->values[0]->transport = 'UDP';
		$response->via->values[0]->host = $this->_sHost.':'.$this->_sPort;
		$response->via->values[0]->branch = 'z9hG4bK-'.rand(10000,99999);
		$response->via->values[0]->params['rport'] = '';

		$response->from = $message->from;
		$response->to = $message->to;
		$response->callId = $message->callId;
		$response->cSeq = $message->cSeq;

		$response->callId = $message->callId;

		$response->maxForwards = new ScalarHeader;
		$response->maxForwards->value = 70;
		
		$response->userAgent = new Header;
		$response->userAgent->values[0] = $this->_userAgent;
		
		return $this->send($response->render());		
	}
	public function newMessage($number,$texte){
		$this->method = 'MESSAGE';
		$request = $this->formatRequest();
		$request->to->addr = 'sip:'.$number.'@'.$this->_sHost.':'.$this->_sPort;
		$request->body = $texte;
		$this->send($request->render());
		$message = $this->read();
		return $this->getReponse($message);
	}
	public function newCall($number){
		$this->method = 'INVITE';
		$this->_CallNumber = $number;
		$request = $this->formatRequest();
		$request->contentType = new SingleValueWithParamsHeader;
		$request->contentType->value ='application/sdp';
		$request->body = $this->clientSDP($request);
		$this->send($request->render());
		$message = $this->read();
		return $this->getReponse($message);
	}
	public function request($method, $message = null){
		$this->method = $method;
		$request = $this->formatRequest();
		if($message != null){
			$request->from = $message->from;
			$request->to = $message->to;
			$request->callId = $message->callId;
			$request->cSeq = $message->cSeq;
			//$request->cSeq->sequence += 1;
			$request->cSeq->method = $this->method;
		}
		$this->send($request->render());
		$message = $this->read();
		return $this->getReponse($message);
	}
	public function formatRequest(){
		$request = new Request;
		$request->version = 'SIP/2.0';
		$request->method = $this->method;
		$request->uri = 'sip:'.$this->_CallNumber.'@'.$this->_sHost.':'.$this->_sPort;

		$request->via = new ViaHeader;
		$request->via->values[0] = new ViaValue;
		$request->via->values[0]->protocol = 'SIP';
		$request->via->values[0]->version = '2.0';
		$request->via->values[0]->transport = 'UDP';
		$request->via->values[0]->host = $this->_cHost.':'.$this->_cPort;
		$request->via->values[0]->branch = 'z9hG4bK-'.rand(10000,99999);
		$request->via->values[0]->params['rport'] = '';

		$request->from = new NameAddrHeader;
		$request->from->addr = 'sip:'.$this->_ClientNumber.'@'.$this->_sHost.':'.$this->_sPort;
		$request->from->tag = rand(10000,99999);
          
		$request->to = new NameAddrHeader;
		$request->to->addr = 'sip:'.$this->_CallNumber.'@'.$this->_sHost.':'.$this->_sPort;
		//$request->to->tag = rand(10000,99999);
      
		$request->cSeq = new CSeqHeader;
		$request->cSeq->sequence = $this->_csec;
		$request->cSeq->method = $this->method;

		$request->callId = new CallIdHeader;
		$request->callId->value = md5(uniqid());

		$request->maxForwards = new ScalarHeader;
		$request->maxForwards->value = 70;

		$request->contact = new ContactHeader;
		$request->contact->values[0] = new ContactValue;
		$request->contact->values[0]->addr = 'sip:'.$this->_ClientNumber.'@'.$this->_cHost.':'.$this->_cPort;
		//$request->contact->values[0]->q = 0.7;
		//$request->contact->values[0]->expires = 3600;
		//$request->contact->values[0]->params['+sip.instance'] = '"<urn:uuid:5cc54b96-ab90-4652-b4e5-de74c8e56fb7>"';
		
		$request->allow = new MultiValueHeader;
		$request->allow->values[0] = 'INVITE, ACK, CANCEL, OPTIONS, BYE, REFER, NOTIFY, MESSAGE, SUBSCRIBE, INFO';

		$request->userAgent = new Header;
		$request->userAgent->values[0] = $this->_userAgent;
		return $request;
	}
	private function clientSDP($message){
		$SDP = "v=0\r\n";
		$SDP .= "o=".$this->_userAgent." ".$message->callId->value." ".$message->cSeq->value." IN IP4 ".$this->_cHost."\r\n";
		$SDP .= "s=".$this->_userAgent."\r\n";
		$SDP .= "c=IN IP4 ".$this->_cHost."\r\n";
		$SDP .= "t=0 0\r\n";
		$SDP .= "m=audio ".rand(9000,10999)." RTP/AVP 0 3 8\r\n";
		//$SDP .= "m=audio ".rand(9000,10999)." RTP/AVP 0 3 4 8 18 101\r\n";
		$SDP .= "a=rtpmap:0 PCMU/8000\r\n";
		$SDP .= "a=rtpmap:3 GSM/8000\r\n";
		//$SDP .= "a=rtpmap:4 G723/8000\r\n";
		$SDP .= "a=rtpmap:8 PCMA/8000\r\n";
		//$SDP .= "a=rtpmap:18 G729/8000\r\n";
		//$SDP .= "a=rtpmap:101 telephone-event/8000\r\n";
		//$SDP .= "a=fmtp:101 0-16\r\n";
		//$SDP .= "a=sendrecv\r\n";
		//$SDP .= "m=video 45450 RTP/AVP 34\r\n";
		//$SDP .= "a=rtpmap:34 H263/8000\r\n";
		//$SDP .= "a=rtpmap:35 H264/90000\r\n";
		return $SDP;
	}
}
?>
