<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'Serveur/SocketServeur', 'class', 'clientSIP');
class clientSIP extends eqLogic {
  	public static function dependancy_info() {
		$return = array();
		$return['log'] = log::getPathToLog(__CLASS__ . '_update');
		$cmd = "dpkg -l | grep libttspico-utils";
		exec($cmd, $output, $return_var);
		if (isset($output[0])) {
			if (`which pico2wave`) {
				$return['state'] = 'ok';
			} else {
				$return['state'] = 'nok';
			}
		} else {
			$return['state'] = 'nok';
		}
		$return['progress_file'] = jeedom::getTmpFolder('clientSIP') . '/compilation_in_progress';
		return $return;
	}
	public static function dependancy_install() {
		log::remove(__CLASS__ . '_update');
		return array('script' => dirname(__FILE__) . '/../../resources/install.sh ' . jeedom::getTmpFolder('clientSIP') . '/compilation_in_progress', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'clientSIP';
		$return['launchable'] = 'ok';
		$return['state'] = 'nok';
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			if($clientSIP->getIsEnable()){
				if($clientSIP->getConfiguration("Expiration") != ''){
					$cron = cron::byClassAndFunction('clientSIP', 'ConnectSip', array('id' => $clientSIP->getId()));
					if (!is_object($cron))  	
						return $return;
				}
				$cron = cron::byClassAndFunction('clientSIP', 'SipServer', array('id' => $clientSIP->getId()));
				if (!is_object($cron) || !$cron->running()) 	
					return $return;
				$cron = cron::byClassAndFunction('clientSIP', 'WaitCall', array('id' => $clientSIP->getId()));
				if (!is_object($cron) || !$cron->running()) 	
					return $return;
				$cron = cron::byClassAndFunction('clientSIP', 'WaitMessage', array('id' => $clientSIP->getId()));
				if (!is_object($cron) || !$cron->running()) 	
					return $return;
			}
		}
		$return['state'] = 'ok';
		return $return;
	}
	public static function deamon_start($_debug = false) {
		log::remove('clientSIP');
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') 
			return;
		if ($deamon_info['state'] == 'ok') 
			return;
		$cache = cache::byKey('clientSIP::HistoryCall');
		$cache->remove();
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			if($clientSIP->getIsEnable()){
				if($clientSIP->getConfiguration("Expiration") != ''){
					$minute=round($clientSIP->getConfiguration("Expiration")/60,0);
					$clientSIP->CreateDemon('ConnectSip','*/'.$minute.' * * * *',false); 
				}
				$clientSIP->CreateDemon('SipServer','* * * * * *',true);   
				$clientSIP->CreateDemon('WaitCall','* * * * * *',true);   
				$clientSIP->CreateDemon('WaitMessage','* * * * * *',true);   
			}
		}
	}
	public static function deamon_stop() {	
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			$clientSIP->checkAndUpdateCmd('RegStatus','Inactif');
			$clientSIP->checkAndUpdateCmd('CallStatus','Racrocher');
			if($clientSIP->getConfiguration("Expiration") != ''){
				$cron = cron::byClassAndFunction('clientSIP', 'ConnectSip', array('id' => $clientSIP->getId()));
				if (is_object($cron)) 	
					$cron->remove();
			}
			$cron = cron::byClassAndFunction('clientSIP', 'SipServer', array('id' => $clientSIP->getId()));
			if (is_object($cron)) 	
				$cron->remove();
			$cron = cron::byClassAndFunction('clientSIP', 'WaitCall', array('id' => $clientSIP->getId()));
			if (is_object($cron)) 	
				$cron->remove();
			$cron = cron::byClassAndFunction('clientSIP', 'WaitMessage', array('id' => $clientSIP->getId()));
			if (is_object($cron)) 	
				$cron->remove();
			$cache = cache::byKey('clientSIP::Port::'.$clientSIP->getId());
			if (is_object($cache)) 	
				$cache->remove();
		}
	}	
	
	public static function SipServer($_option) {	
		$clientSIP = eqlogic::byId($_option['id']);
		if (is_object($clientSIP) && $clientSIP->getIsEnable()) {
			$Server = new SocketServeur($clientSIP);
          		$Server->listen();
		}
	}
	private function _createSocket(){ 
		$HostServer = '127.0.0.1';
		$PortServer = $this->getConfiguration("Port") * 10;
		set_time_limit(0);
		$this->sockServer = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if( $this->sockServer === false ) 
			throw new Exception(__(' {{[Serveur] Impossible de créer le service verifier votre configuration => '.$HostServer.':'.$PortServer.'}}', __FILE__));
		socket_connect($this->sockServer, $HostServer,$PortServer);
		if(is_resource($this->sockServer))
			log::add('clientSIP','debug',"[Serveur] Creation d'un serveur de connexion => ".$HostServer.':'.$PortServer);
	}
	private function _closeSocket(){
		$HostServer = '127.0.0.1';
		$PortServer = $this->getConfiguration("Port") * 10;
		if(is_resource($this->sockServer)){
			log::add('clientSIP','debug',"[Serveur] Fermmeture de la connexion du serveur de connexion=> ".$HostServer.':'.$PortServer);
			socket_close($this->sockServer);
			$this->socket=null;
		}
	}
	public function toHtml($_version = 'mobile') {
		$replace = $this->preToHtml($_version);
		if (!is_array($replace)) {
			return $replace;
		}
		$version = jeedom::versionAlias($_version);
		if ($this->getDisplay('hideOn' . $version) == 1) {
			return '';
		}
		foreach ($this->getCmd(null, null, true) as $cmd) {
			 $replace['#'.$cmd->getLogicalId().'#'] = $cmd->toHtml($_version);
		} 
		return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version,'eqLogic','clientSIP')));
	}
	public function postSave() {
		$this->AddCommande('Etat connexion','RegStatus','info', 'string');
		$this->AddCommande('Etat appel','CallStatus','info', 'string','CallStatus');
		$this->AddCommande('Appel','call','action','message','call');
		$this->AddCommande('Message','message','action','message','default');
		$this->checkAndUpdateCmd('RegStatus','Inactif');
	}
	public static $_widgetPossibility = array('custom' => array(
	        'visibility' => true,
	        'displayName' => true,
	        'optionalParameters' => true,
	));
	public function CreateDemon($Name,$Schedule,$deamon=false) {
		$cron =cron::byClassAndFunction('clientSIP', $Name, array('id' => $this->getId()));
		if (!is_object($cron)) {
			$cron = new cron();
			$cron->setClass('clientSIP');
			$cron->setFunction($Name);
			$cron->setOption(array('id' => $this->getId()));
			$cron->setEnable(1);
			if($deamon){
				$cron->setDeamon(1);
				$cron->setTimeout('999999');
			}
			$cron->setSchedule($Schedule);
			$cron->save();
		}
		$cron->start();
		$cron->run();
	}
	public static function ConnectSip($_option){
		log::add('clientSIP', 'debug', 'Objet mis à jour => ' . json_encode($_option));
		$clientSIP = clientSIP::byId($_option['id']);
		if (is_object($clientSIP) && $clientSIP->getIsEnable()){
			$clientSIP->_createSocket();
			$_options['action']='RegisterClient';
			$message = json_encode($_options);	
			$reponse =socket_write($clientSIP->sockServer, $message, strlen($message));
			$clientSIP->_closeSocket();
		}
	}
	public static function WaitCall($_option){
		$clientSIP = clientSIP::byId($_option['id']);
		if (is_object($clientSIP) && $clientSIP->getIsEnable()) {
			$clientSIP->_createSocket();
			$_options['action']='WaitCall';
			$message = json_encode($_options);	
			$reponse =socket_write($clientSIP->sockServer, $message, strlen($message));
			while(true)
				sleep(60);
			$clientSIP->_closeSocket();
		}
	}	
	public static function WaitMessage($_option){
		$clientSIP = clientSIP::byId($_option['id']);
		if (is_object($clientSIP) && $clientSIP->getIsEnable()) {
			$clientSIP->_createSocket();
			$_options['action']='WaitMessage';
			$message = json_encode($_options);	
			$reponse =socket_write($clientSIP->sockServer, $message, strlen($message));
			while(true)
				sleep(60);
			$clientSIP->_closeSocket();
		}
	}
	public static function addHistoryCall($_call) {
		$cache = cache::byKey('clientSIP::HistoryCall');
		$value = json_decode($cache->getValue('[]'), true);
		if($key=array_search($value,$_call['start'])===false)
			$value[$key]=$_call;
		else
			$value[] = $_call;
		cache::set('clientSIP::HistoryCall', json_encode(array_slice($value, -250, 250)), 0);
	}

	public function AddCommande($Name,$_logicalId,$Type="info", $SubType='string',$Template='default') {
		$Commande = $this->getCmd(null,$_logicalId);
		if (!is_object($Commande))
		{
			$Commande = new clientSIPCmd();
			$Commande->setId(null);
			$Commande->setEqLogic_id($this->getId());
		}
		$Commande->setLogicalId($_logicalId);
		$Commande->setName($Name);
		$Commande->setIsVisible(1);
		$Commande->setType($Type);
		$Commande->setSubType($SubType);
		if($Template !=''){
			$Commande->setTemplate('dashboard',$Template);
			$Commande->setTemplate('mobile',$Template);
		}
		$Commande->save();
		return $Commande;
	}
	public function TextToSpeach($Texte) {
		$SpeachFile = '/tmp/' . hash('md5', $Texte) . '.mp3';
		if (!file_exists($SpeachFile)) {
			$lang = $this->getConfiguration('lang');
			if ($lang == '') {
				$lang == 'fr-FR';
			}
			exec("pico2wave -l " . $lang . " -w /tmp/voice.wav \"" . $Texte . "\"");
			exec("sox /tmp/voice.wav -r 48k " . $SpeachFile);
		}	
		return file_get_contents($SpeachFile);
	}
}
class clientSIPCmd extends cmd {
	public function execute($_options = null){
		$this->getEqLogic()->_createSocket();
		switch($this->getLogicalId()){
			case 'call':		
				$_options['action']='call';
				$message = json_encode($_options);	
				$reponse =socket_write($this->getEqLogic()->sockServer, $message, strlen($message));
			break;
			case 'message':		
				$_options['action']='sendMessage';		
				$this->getEqLogic()->_createSocket();
				$message = json_encode($_options);	
				$reponse =socket_write($this->getEqLogic()->sockServer, $message, strlen($message));
			break;
		}
		$this->getEqLogic()->_closeSocket();
	}
}
?>
