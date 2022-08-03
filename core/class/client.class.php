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
		$this->_CallNumber = $ClientNumber;
		$this->_Username = $Username;
		$this->_Password = $Password;
		$this->_userAgent = $userAgent;
		$this->_cSeq =	cache::byKey('clientSIP::cSeq::'.$this->_userAgent)->getValue(0);
		if(cache::byKey('clientSIP::authorization::realm::'.$this->_userAgent)->getValue('') != ''){
			$this->_authorization = new Header();
			$realm = cache::byKey('clientSIP::authorization::realm::'.$this->_userAgent)->getValue('');;
			$algorithm = cache::byKey('clientSIP::authorization::algorithm::'.$this->_userAgent)->getValue('');;
			$nonce = cache::byKey('clientSIP::authorization::nonce::'.$this->_userAgent)->getValue('');;
			$ha1 = md5($this->_Username.':'.$realm.':'.$this->_Password);
			$ha2 = md5($this->method.':'.$message->uri);
			$res = md5($ha1.':'.$nonce.':'.$ha2);
			$this->authorization->values[] = 'username='.$this->_Username;
			$this->authorization->values[] = 'uri='.$this->uri;
			$this->authorization->values[] = 'algorithm='.$algorithm;
			$this->authorization->values[] = 'nonce='.$nonce;
			$this->authorization->values[] = 'realm='.$realm;
			$this->authorization->values[] = 'response='.$res;
		}
		if(cache::byKey('clientSIP::ProxyAuthorization::realm::'.$this->_userAgent)->getValue('') != ''){
			$this->_ProxyAuthorization = new ProxyAuthHeader();
			$this->_ProxyAuthorization->values[0]->params['username'] = $this->_Username;
			$this->_ProxyAuthorization->values[0]->params['password'] = $this->_Password;
			$this->_ProxyAuthorization->values[0]->params['algorithm'] = cache::byKey('clientSIP::ProxyAuthorization::algorithm::'.$this->_userAgent)->getValue('');
			$this->_ProxyAuthorization->values[0]->params['nonce'] = cache::byKey('clientSIP::ProxyAuthorization::nonce::'.$this->_userAgent)->getValue('');
			$this->_ProxyAuthorization->values[0]->params['realm'] = cache::byKey('clientSIP::ProxyAuthorization::realm::'.$this->_userAgent)->getValue('');
		}					
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
		if($this->_cSeq != $message->cSeq->sequence)
			return $message;
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
			case '401':
		            	if($message->cSeq->method == $this->method){
					$this->_cSeq += 1;
					cache::set('clientSIP::cSeq::'.$this->_userAgent,$this->_cSeq,0);
					$request = $this->formatRequest();
					$request->from = $message->from;
					$request->authorization = $message->wwwAuthenticate;
					$realm = $message->wwwAuthenticate->values[0]->params['realm'];
					cache::set('clientSIP::authorization::realm::'.$this->_userAgent,$realm ,0);
					$algorithm = $message->wwwAuthenticate->values[0]->params['algorithm'];
					cache::set('clientSIP::authorization::algorithm::'.$this->_userAgent,$algorithm ,0);
					$nonce = $message->wwwAuthenticate->values[0]->params['nonce'];
					cache::set('clientSIP::authorization::algononcerithm::'.$this->_userAgent,$nonce ,0);
					$ha1 = md5($this->_Username.':'.$realm.':'.$this->_Password);
					$ha2 = md5($this->method.':'.$message->uri);
					if ($message->wwwAuthenticate->values[0]->params['qop']){
						$cnonce = md5(time());
						$request->authorization->values[] = "nc=00000001";
						$request->authorization->values[] = "cnonce=".$cnonce;
						$res = md5($ha1.':'.$nonce.':00000001:'.$cnonce.':auth:'.$ha2);
					}else{
						$res = md5($ha1.':'.$nonce.':'.$ha2);
					}
					$request->authorization->values[] = 'username='.$this->_Username;
					$request->authorization->values[] = 'uri='.$message->uri;
					$request->authorization->values[] = 'response='.$res;
					$this->_authorization = $request->authorization;	
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
			case '407':
		            	if($message->cSeq->method == $this->method){
					$this->_cSeq += 1;
					cache::set('clientSIP::cSeq::'.$this->_userAgent,$this->_cSeq,0);
					$request = $this->formatRequest();
					$request->from = $message->from;
					$request->proxyAuthorization = $message->proxyAuthenticate;
					$request->proxyAuthorization->values[0]->params['username'] = $this->_Username;
					$request->proxyAuthorization->values[0]->params['password'] = $this->_Password;
					$request->proxyAuthorization->values[0]->params['uri'] = $request->uri;
					$request->proxyAuthorization->values[0]->params['method'] = $this->method;
					$this->_ProxyAuthorization = $request->proxyAuthorization;	
					cache::set('clientSIP::ProxyAuthorization::algorithm::'.$this->_userAgent,$request->proxyAuthorization->values[0]->params['algorithm'] ,0);
					cache::set('clientSIP::ProxyAuthorization:nonce::'.$this->_userAgent,$request->proxyAuthorization->values[0]->params['nonce'] ,0);
					cache::set('clientSIP::ProxyAuthorization:realm::'.$this->_userAgent,$request->proxyAuthorization->values[0]->params['realm'] ,0);
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
      	if($number =='')
          return false;
		$this->method = 'INVITE';
		$this->_cSeq += 1;
		cache::set('clientSIP::cSeq::'.$this->_userAgent,$this->_cSeq,0);
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
		$request->cSeq->sequence = $this->_cSeq;
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
		if(is_object($this->_ProxyAuthorization)){
			$request->proxyAuthorization = $this->_ProxyAuthorization;
			$request->proxyAuthorization->values[0]->params['uri'] = $request->uri;
			$request->proxyAuthorization->values[0]->params['method'] = $this->method;
		}
		if(is_object($this->_authorization)){
			$request->authorization = $this->_authorization;
			$request->authorization->values[0]->params['uri'] = $request->uri;
			$request->authorization->values[0]->params['method'] = $this->method;
		}
		return $request;
	}
	private function clientSDP($message){
		$SDP = "v=0\r\n";
		$SDP .= "o=".$this->_userAgent." ".$message->callId->value." ".$message->cSeq->value." IN IP4 ".$this->_cHost."\r\n";
		$SDP .= "s=".$this->_userAgent."\r\n";
		$SDP .= "c=IN IP4 ".$this->_cHost."\r\n";
		$SDP .= "t=0 0\r\n";
		$SDP .= "m=audio 32767 RTP/AVP 0 3 8\r\n";
		//$SDP .= "m=audio ".rand(16384,32767)." RTP/AVP 0 3 4 8 18 101\r\n";
		$SDP .= "a=rtpmap:0 PCMU/8000\r\n";
		$SDP .= "a=rtpmap:3 GSM/8000\r\n";
		//$SDP .= "a=rtpmap:4 G723/8000\r\n";
		$SDP .= "a=rtpmap:8 PCMA/8000\r\n";
		//$SDP .= "a=rtpmap:18 G729/8000\r\n";
		//$SDP .= "a=rtpmap:101 telephone-event/8000\r\n";
		//$SDP .= "a=fmtp:101 0-16\r\n";
		$SDP .= "a=sendrecv\r\n";
		//$SDP .= "m=video 45450 RTP/AVP 34\r\n";
		//$SDP .= "a=rtpmap:34 H263/8000\r\n";
		//$SDP .= "a=rtpmap:35 H264/90000\r\n";
		return $SDP;
	}
}
?>
