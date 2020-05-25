<?php
require_once 'SocketClient.class.php';
ini_set('error_reporting', E_ALL ^ E_NOTICE);
ini_set('display_errors', 1);
set_time_limit(0);
ob_implicit_flush();
class SpaServer{
	protected $sockServer;
	protected $SpaSock;
	protected $HostServer;
	protected $Port;
	protected $HostSpa;
	protected $_listenLoop;
	private $clients = [];
	
	public function __construct($PortServer = 50600) {
		$this->HostServer = '127.0.0.1';
		$this->PortServer = $PortServer;
		$this->_listenLoop = false;
		$this->_createSocket();
		$this->_bindSocket();
	}
	public function __destruct(){
		$this->CloseToSpa();
		$this->_closeSocket();
	}
	private function _createSocket() {
		$this->sockServer = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if( $this->sockServer === false ) 
			throw new Exception(__(' {{[Serveur] Impossible de créer le service verifier votre configuration => '.$this->HostServer.':'.$this->PortServer.'}}', __FILE__));
		socket_set_option($this->sockServer, SOL_SOCKET, SO_REUSEADDR, 1);
		if(is_resource($this->sockServer))
			log::add('clientSIP','debug',"[Serveur] Creation d'un serveur de connexion => ".$this->HostServer.':'.$this->PortServer);
	}
	private function _closeSocket(){
		if(is_resource($this->sockServer)){
			log::add('clientSIP','debug',"[Serveur] Fermmeture de la connexion du serveur de connexion=> ".$this->HostServer.':'.$this->PortServer);
			socket_close($this->sockServer);
			$this->socket=null;
		}
	}
	private function _bindSocket() {
		if( socket_bind($this->sockServer, $this->HostServer, $this->PortServer) === false ) 
			throw new Exception(__(' {{[Serveur] Impossible de binder le serveur de connexion => '.$this->HostServer.':'.$this->PortServer.'}}', __FILE__));
	}
	public function listen() {
		if( socket_listen($this->sockServer, 5) === false)
			throw new Exception(__(' {{[Serveur] Impossible d\'ecouter le service => '.$this->HostServer.':'.$this->PortServer.'}}', __FILE__));
		//socket_set_nonblock($this->sockServer);
		socket_set_option($this->sockServer,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>5, "usec"=>0));
		$this->_listenLoop = true;
		$this->beforeServerLoop();
		$this->serverLoop();
		$this->CloseConnexion();
		socket_close($this->sockServer);
	}
	
	protected function beforeServerLoop() {
		log::add('clientSIP', 'debug',"Listening on ".$this->HostServer.":". $this->PortServer." ...");
	}	
	protected function serverLoop() {
		while( $this->_listenLoop ) {
			if(( $client = @socket_accept($this->sockServer)) === false )
				continue;	
			$socketClient = new SocketClient( $client );
			$this->onConnect($socketClient);
		}
	}
	private function onConnect($client) {
		log::add('clientSIP', 'debug',"Connexion d'un nouveau client => ".$client->getAddress());
		$pid = pcntl_fork();
		if ($pid == -1){
			log::add('clientSIP', 'debug', "[Server] Impossible de cree une nouvelle instance");
			die;
		}else
			$this->interact($client);
	}
	private function interact($client) {
		while(true){
			$value = $client->read();
		
			$ClientWrite = $client->send(value);
			if($ClientWrite === false)
				break;
			}
			$client->close();
			unset($client);
		}	
}
