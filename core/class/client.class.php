<?php
     
include 'Exception\InvalidBodyLengthException';
include 'Exception\InvalidCSeqValue';
include 'Exception\InvalidHeaderLineException';
include 'Exception\InvalidHeaderSectionException';
include 'Header\CallIdHeader';
include 'Header\ContactHeader';
include 'Header\CSeqHeader';
include 'Header\Header';
include 'Header\FromHeader';
include 'Header\MaxForwardsHeader';
include 'Header\MultiValueHeader';
include 'Header\MultiValueWithParamsHeader';
include 'Header\NameAddrHeader';
include 'Header\RAckHeader';
include 'Header\RSeqHeader';
include 'Header\ScalarHeader';
include 'Header\SingleValueWithParamsHeader';
include 'Header\ViaHeader';
class sip{ 
     public function __construct($jeedom = null,$src_ip = null, $src_port = null, $fr_timer = null, $socket_bind = true)	{
		$this->jeedom = $jeedom;
		$this->socket_bind = $socket_bind;
		if (!function_exists('socket_create')){
			log::add('clientSIP','error',$this->jeedom->getHumanName()." socket_create() function missing.");
			die();
		}
		if ($src_ip){
			if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/', $src_ip)){
				log::add('clientSIP','error',$this->jeedom->getHumanName()." Invalid src_ip $src_ip");
				die();
			}
		}else{
			if (isset($_SERVER['SERVER_ADDR'])){
				// running in a web server
				$src_ip = $_SERVER['SERVER_ADDR'];
			}else	{
				// running from command line
				$addr = gethostbynamel(php_uname('n'));
				if (!is_array($addr) || !isset($addr[0]) || substr($addr[0],0,3) == '127')	{
					log::add('clientSIP','error',$this->jeedom->getHumanName()." Failed to obtain IP address to bind. Please set bind address manualy.");
					die();
				}
				$src_ip = $addr[0];
			}
		}
		$this->src_ip = $src_ip;
		if ($src_port){
			if (!preg_match('/^[0-9]+$/',$src_port)){
				log::add('clientSIP','error',$this->jeedom->getHumanName(). " Invalid src_port $src_port");
				die();
			}
			$this->src_port = $src_port;
			$this->lock_file = null;
		}
		if ($fr_timer){
			if (!preg_match('/^[0-9]+$/',$fr_timer)){
				log::add('clientSIP','error',$this->jeedom->getHumanName()."Invalid fr_timer $fr_timer");
				die();
			}
			$this->fr_timer = $fr_timer;
		}
		$this->createSocket();
	}
     public function __destruct(){
		$this->closeSocket();
	}
     private function createSocket(){ 
		$this->getPort();
		if (!$this->src_ip){
			log::add('clientSIP','error',$this->jeedom->getHumanName()."Source IP not defined.");
			die();
		}
		if (!$this->socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)){
			$err_no = socket_last_error($this->socket);
			log::add('clientSIP','error',$this->jeedom->getHumanName().socket_strerror($err_no));
			die();
		}
		if($this->socket_bind){
			if (!@socket_bind($this->socket, $this->src_ip, $this->src_port)){
				$err_no = socket_last_error($this->socket);
				log::add('clientSIP','error',$this->jeedom->getHumanName()."Failed to bind ".$this->src_ip.":".$this->src_port." ".socket_strerror($err_no));
				die();
			}
		}
		$microseconds = $this->fr_timer * 1000;
		$usec = $microseconds % 1000000;
		$sec = floor($microseconds / 1000000);
		if (!@socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>$sec,"usec"=>$usec))){
			$err_no = socket_last_error($this->socket);
			log::add('clientSIP','error',$this->jeedom->getHumanName().socket_strerror($err_no));
			die();
		}
		if (!@socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array("sec"=>5,"usec"=>0))){
			$err_no = socket_last_error($this->socket);
			log::add('clientSIP','error',$this->jeedom->getHumanName().socket_strerror($err_no));
			die();
		}
	}
	private function closeSocket(){
		socket_close($this->socket);
		$this->releasePort();
	}
}
?>
