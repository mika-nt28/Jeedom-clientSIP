<?php
     
include '/Request.php';
include '/Header/Header.php';

class client{ 
     public function __construct($src_ip = null, $src_port = null ,$CallNumber,$userAgent, $socket_bind = true)	{
		$this->_cHost = $src_ip;
		$this->_cPort = $src_port;
	  	$this->_CallNumber = $CallNumber;
	  	$this->_userAgent = $userAgent;
		$this->_sHost=config::byKey('Host', 'clientSIP');
		$this->_sPort=config::byKey('Port', 'clientSIP');
		$this->createSocket($socket_bind);
	}
     public function __destruct(){
		$this->closeSocket();
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
		if (!@socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>5,"usec"=>0))){
			$err_no = socket_last_error($this->socket);
			log::add('clientSIP','error',socket_strerror($err_no));
			die();
		}
		if (!@socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array("sec"=>5,"usec"=>0))){
			$err_no = socket_last_error($this->socket);
			log::add('clientSIP','error',socket_strerror($err_no));
			die();
		}
	}
	private function closeSocket(){
		socket_close($this->socket);
	}
	public function register(){
		$request = new Request;
		$request->version = 'SIP/2.0';
		$request->method = 'REGISTER';
		$request->uri = 'sip:'.$this->_cHost;

		$request->via = new ViaHeader;
		$request->via->values[0] = new ViaValue;
		$request->via->values[0]->protocol = 'SIP';
		$request->via->values[0]->version = '2.0';
		$request->via->values[0]->transport = 'UDP';
		$request->via->values[0]->host = .$this->_sHost.':'.$this->_sPort;
		$request->via->values[0]->branch = 'z9hG4bK.eAV4o0nXr';

		$request->from = new NameAddrHeader;
		$request->from->addr = 'sip:'.$this->_CallNumber.'@'.$this->_cHost.':'.$this->_sPort;
		$request->from->tag = 'SFJbQ2oWh';

		//$request->to = new NameAddrHeader;
		//$request->to->addr = 'sip:'.$this->_CallNumber.'@'.$this->_cHost.':'.$this->_cPort;

		$request->cSeq = new CSeqHeader;
		$request->cSeq->sequence = 20;
		$request->cSeq->method = $request->method;

		$request->callId = new CallIdHeader;
		$request->callId->value = 'ob0EYyuyC0';

		$request->maxForwards = new ScalarHeader;
		$request->maxForwards->value = 70;

		$request->contact = new ContactHeader;
		$request->contact->values[0] = new ContactValue;
		$request->contact->values[0]->addr = 'sip:'.$this->_CallNumber.'@'.$this->_cHost.':'.$this->_cPort.';transport=udp';
		$request->contact->values[0]->q = 0.7;
		$request->contact->values[0]->expires = 3600;
		//$request->contact->values[0]->params['+sip.instance'] = '"<urn:uuid:5cc54b96-ab90-4652-b4e5-de74c8e56fb7>"';

		$request->userAgent = new Header;
		$request->userAgent->values[0] = $this->_userAgent;

		$request->render();
	}
}
?>
