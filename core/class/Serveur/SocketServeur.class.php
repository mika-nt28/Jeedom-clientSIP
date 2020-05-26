<?php
require_once 'SocketClient.class.php';
require_once 'sip.class.php';
ini_set('error_reporting', E_ALL ^ E_NOTICE);
ini_set('display_errors', 1);
set_time_limit(0);
ob_implicit_flush();
class SocketServeur{
	protected $sockServer;
	protected $HostServer;
	protected $Port;
	protected $_listenLoop;
	private $clients = [];
	
	public function __construct($Jeedom) {
		$this->Jeedom=$Jeedom;
		$this->HostServer = '127.0.0.1';
		$this->PortServer = $this->Jeedom->getConfiguration("Port") * 10;
		$this->_listenLoop = false;
		$this->_createSocket();
		$this->_bindSocket();
		$this->CreateSipConnexion();
	}
	public function __construct($Jeedom) {
		unlink("/tmp/PhpSIP.lock");
	}
	private function CreateSipConnexion(){
		$this->_Host=config::byKey('Host', 'clientSIP');
		$this->_Port=config::byKey('Port', 'clientSIP');
		$this->_Username=$this->Jeedom->getConfiguration("Username");
		$this->_Password=$this->Jeedom->getConfiguration("Password");
		if($this->_sip == null){
			$this->_sip = new sip($this->Jeedom,network ::getNetworkAccess('internal', 'ip', '', false),$this->Jeedom->getConfiguration("Port"));
			if($this->Jeedom->getConfiguration("Proxy")!="") 
				$this->_sip->setProxy($this->Jeedom->getConfiguration("Proxy"));
			$this->_sip->setUsername($this->_Username);
			$this->_sip->setPassword($this->_Password);
			$this->_sip->setServerMode(true);
		}
	}
	private function RegisterClient(){
		if($this->_sip == null)		
			return;
		$this->_sip->addHeader('Expires: '.$this->Jeedom->getConfiguration("Expiration"));
		$this->_sip->setMethod('REGISTER');
		if($this->Jeedom->getConfiguration("Proxy")!="") 
			$this->_sip->setProxy($this->Jeedom->getConfiguration("Proxy"));
		$this->_sip->setFrom('sip:'.$this->_Username.'@'.$this->_Host.':'.$this->_Port);
		$this->_sip->setUri('sip:'.$this->_Username.'@'.$this->_Host.':'.$this->_Port.';transport='.$this->Jeedom->getConfiguration("transport"));
		$res = $this->_sip->send();
		if ($res == '200')
			$this->Jeedom->checkAndUpdateCmd('RegStatus','OK');
		else
			$this->Jeedom->checkAndUpdateCmd('RegStatus','Echec');			
	}	
	public function RepondreAppel() {
		$call['status']='ringing'; 
		$call['flow']='incoming';  
		$call['number']='';  
		$call['callLength']='';  
		$call['callActive']=false;
		$call['start']=date('d/m/Y H:i:s');  
		clientSIP::addHistoryCall($call);
		event::add('clientSIP::call', utils::o2a($this));
		$CallStatus=$this->Jeedom->getCmd(null,'CallStatus');
		while($CallStatus->execCmd() == 'Sonnerie');
		switch($CallStatus->execCmd()){
			case 'Decrocher':
				$call['status']= 'call';
				clientSIP::addHistoryCall($call);
				$this->Decrocher($call);
			break;
			case 'Racrocher':
				$call['status']= 'reject';
				clientSIP::addHistoryCall($call);
				$this->Racrocher($call);
			return;
		}
	}
	public function Decrocher($call) {
		//ajouter les options de compatibilité de jeedom
		$this->_sip->reply(200,'Ok');
		event::add('clientSIP::rtsp', $this->_sip->getBody());
		$this->Jeedom->checkAndUpdateCmd('CallStatus','Appel en cours');
		$CallStatus=$this->Jeedom->getCmd(null,'CallStatus');
		while($CallStatus->execCmd() == 'Appel en cours'){
			$call['callActive']=true;
			$call['callLength']=$strtotime("now")-strtotime($call['start']);  
			clientSIP::addHistoryCall($call);
			sleep(5);
		}
		$this->Racrocher();
	}
	public function Racrocher($call) {
		$CallStatus=$this->getCmd(null,'CallStatus');
		if($CallStatus->execCmd() == 'Sonnerie'){
			$this->_sip->reply(487,'Request Terminated');
			$this->_sip->reply(603,'Decline');
			$this->_sip->setMethod('CANCEL');
			$this->_sip->setFrom('sip:'.$this->_Username.'@'.$this->_Host);
			$this->_sip->send();
		}else{
			$this->_sip->setMethod('BYE');
			$this->_sip->setFrom('sip:'.$this->_Username.'@'.$this->_Host);
			$this->_sip->send();
		}
		$this->Jeedom->checkAndUpdateCmd('CallStatus','Racrocher');
		$call['callLength']=$strtotime("now")-strtotime($call['start']);  
		$call['callActive']=false;
		clientSIP::addHistoryCall($call);
	}
	public function call($number) {	
		$call['status']='ringing'; 
		$call['flow']='outcoming';  
		$call['number']=$number;  
		$call['start']=date('d/m/Y H:i:s');  
		$call['callLength']=''; 
		$call['callActive']=false; 
		clientSIP::addHistoryCall($call);
		log::add('clientSIP', 'debug', 'Appel en demandé => ' . $number);
		$this->Jeedom->checkAndUpdateCmd('CallStatus','Racrocher');	
		$this->_sip->setUsername($this->_Username);
		$this->_sip->setPassword($this->_Password);
		$this->_sip->newCall();
		$this->_sip->setFrom('sip:'.$this->_Username.'@'.$this->_Host);
		$this->_sip->setUri('sip:'.$number.'@'.$this->_Host.':'.$this->_Port.';transport='.$this->Jeedom->getConfiguration("transport"));
		$this->_sip->setTo('sip:'.$number.'@'.$this->_Host.':'.$this->_Port);
		$this->_sip->setMethod('INVITE');
		$res=$this->_sip->send();
	}
	private function sendMessage($number,$message) {	
		log::add('clientSIP', 'debug', 'Appel en demandé => ' . $number);
		$this->Jeedom->checkAndUpdateCmd('CallStatus','Racrocher');	
		$this->_sip->setUsername($this->_Username);
		$this->_sip->setPassword($this->_Password);
		$this->_sip->newCall();
		$this->_sip->setFrom('sip:'.$this->_Username.'@'.$this->_Host);
		$this->_sip->setUri('sip:'.$number.'@'.$this->_Host.':'.$this->_Port.';transport='.$this->Jeedom->getConfiguration("transport"));
		$this->_sip->setTo('sip:'.$number.'@'.$this->_Host.':'.$this->_Port);
		$this->_sip->setBody($message);
		$this->_sip->setMethod('MESSAGE');
		$res=$this->_sip->send();
		if ($res == '200')
			event::add('clientSIP::message', 'Le message a bien été transmis');
		
	}
	
	private function WaitCall(){
		while(true){
			if(!is_object($this->_sip))
				$this->CreateConnexion();
			$this->_sip->newCall();
			$this->_sip->listen('INVITE');
			$this->RepondreAppel();
		}
	}	
	private function WaitMessage(){
		while(true){
			if(!is_object($this->_sip))
				$this->CreateConnexion();
			$this->_sip->newCall();
			$this->_sip->listen('MESSAGE');
			if ($res == '200')
				message::add('sucess', $this->_sip->getBody());
				//event::add('clientSIP::message', $clientSIP->_sip->getBody());
		}
	}
	public function __destruct(){
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
			$Read = json_decode($client->read());
			switch($Read['action']){
				case 'RegisterClient':
					$this->RegisterClient();
				break;	
				case 'sendMessage':
					$this->sendMessage($Read['$number'],$Read['$message']);
				break;					
				case 'call':
					$this->call($Read['$number']);
				break;					
				case 'Racrocher':
					$this->Racrocher($Read['$call']);
				break;					
				case 'Decrocher':
					$this->Decrocher($Read['$call']);
				break;					
				case 'RepondreAppel':
					$this->RepondreAppel();
				break;					
				case 'WaitCall':
					$this->WaitCall();
				break;					
				case 'WaitMessage':
					$this->WaitCall();
				break;	
			}
			$ClientWrite = $client->send($value);
			if($ClientWrite === false)
				break;
		}
		$client->close();
		unset($client);
	}	
}
