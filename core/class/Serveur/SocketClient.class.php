<?php
class SocketClient {
	private $connection;
	private $address;
	private $port;
	private function ByteToMessage($data){
		$message='';
		foreach($data as $byte)
			$message.=chr($byte);
		return $message;
	}
	public function __construct( $connection ) {
		$address = ''; 
		$port = '';
		socket_getsockname($connection, $address, $port);
		$this->address = $address;
		$this->port = $port;
		$this->connection = $connection;
		socket_set_timeout($this->connection,5);
		//socket_set_nonblock($this->connection);
		socket_set_option($this->connection,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>5, "usec"=>0));
	}
	public function send($data) {
		$message = $this->ByteToMessage($data);
		if(is_resource($this->connection)){
			$reponse =socket_write($this->connection, $message, strlen($message));
			if($reponse === false){
				//log::add('clientSIP', 'debug',socket_strerror(socket_last_error()));
				return false;
			}
			log::add('clientSIP', 'debug',"[Client][TX] ".implode(',',$data));
			usleep(200);
		}
		return $reponse;
	}
	public function read() {
		if(is_resource($this->connection)){
			socket_recv($this->connection, $buf, 50, MSG_PEEK);
			if($buf == '' or strlen($buf) == 0)
				return false;
			$data= unpack("C*", $buf);
			log::add('clientSIP', 'debug',"[Client][RX] => ".implode(',',$data));
			return $this->ByteToMessage($data);
		}
		return false;
	}
	public function getAddress() {
		return $this->address;
	}
	public function getPort() {
		return $this->port;
	}
	public function close() {
		socket_shutdown( $this->connection );
		socket_close( $this->connection );
	}
}
