<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'sip', 'class', 'clientSIP');
class clientSIP extends eqLogic {
  	protected $_sip = null;
	protected $_Host=null;
	protected $_Port=null;
	protected $_Username= null;
	protected $_Password= null;
	public static function dependancy_info() {
		$return = array();
		$return['log'] = 'clientSIP';
		$cmd = "dpkg -l | grep mplayer";
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
		return $return;
	}
	public static function dependancy_install() {
		passthru('/bin/bash ' . realpath(dirname(__FILE__)) . '/../../resources/install.sh ' . realpath(dirname(__FILE__)) . '/../../resources/ > ' . log::getPathToLog('clientSIP') . ' 2>&1 &');
	}
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'clientSIP';
		$return['launchable'] = 'ok';
		$return['state'] = 'nok';
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			if($clientSIP->getIsEnable()){
				$cron = cron::byClassAndFunction('clientSIP', 'ConnectSip', array('id' => $clientSIP->getId()));
				if (!is_object($cron))  	
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
		unlink("/tmp/PhpSIP.lock");
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
				$minute=round($clientSIP->getConfiguration("Expiration")/60,0);
				$clientSIP->CreateDemon('ConnectSip','*/'.$minute.' * * * *',false);   
				$clientSIP->CreateDemon('WaitCall','* * * * * *',true);   
				$clientSIP->CreateDemon('WaitMessage','* * * * * *',true);   
			}
		}
	}
	public static function deamon_stop() {	
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			$clientSIP->checkAndUpdateCmd('RegStatus','Inactif');
			$clientSIP->checkAndUpdateCmd('CallStatus','Racrocher');
			$cron = cron::byClassAndFunction('clientSIP', 'ConnectSip', array('id' => $clientSIP->getId()));
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
	public function toHtml($_version = 'mobile') {
		$_version = jeedom::versionAlias($_version);
		$replace = array(
			'#id#' => $this->getId(),
			'#name#' => ($this->getIsEnable()) ? $this->getName() : '<del>' . $this->getName() . '</del>',
			'#eqLink#' => $this->getLinkToConfiguration(),
			'#background#' => $this->getBackgroundColor($_version),				
			'#height#' => $this->getDisplay('height', 'auto'),
			'#width#' => $this->getDisplay('width', 'auto')
		);	
		foreach ($this->getCmd(null, null, true) as $cmd) {
			 $replace['#'.$cmd->getLogicalId().'#'] = $cmd->toHtml($_version);
		}   
		return template_replace($replace, getTemplate('core', $_version, 'eqLogic','clientSIP'));
	}
	public function postSave() {
		$this->AddCommande('Etat connexion','RegStatus','info', 'string');
		$this->AddCommande('Etat appel','CallStatus','info', 'string','CallStatus');
		$this->AddCommande('Appel','call','action','message','call');
		$this->AddCommande('Message','message','action','message','call');
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
		if (is_object($clientSIP) && $clientSIP->getIsEnable())
			$clientSIP->RegisterClient();
	}
	public static function WaitCall($_option){
		$clientSIP = clientSIP::byId($_option['id']);
		if (is_object($clientSIP) && $clientSIP->getIsEnable()) {
			while(true){
				if(!is_object($clientSIP->_sip))
					$clientSIP->CreateConnexion();
				$clientSIP->_sip->newCall();
				$clientSIP->_sip->listen('INVITE');
				$clientSIP->RepondreAppel();
			}
		}
	}	
	public static function WaitMessage($_option){
		$clientSIP = clientSIP::byId($_option['id']);
		if (is_object($clientSIP) && $clientSIP->getIsEnable()) {
			while(true){
				if(!is_object($clientSIP->_sip))
					$clientSIP->CreateConnexion();
				$clientSIP->_sip->newCall();
				$clientSIP->_sip->listen('MESSAGE');
				if ($res == '200')
					message::add('sucess', $clientSIP->_sip->getBody());
					//event::add('clientSIP::message', $clientSIP->_sip->getBody());
			}
		}
	}	
	private function CreateConnexion(){
		$cache = cache::byKey('clientSIP::Port::'.$this->getId());
		$this->_Host=config::byKey('Host', 'clientSIP');
		$this->_Port=config::byKey('Port', 'clientSIP');
		$this->_Username=$this->getConfiguration("Username");
		$this->_Password=$this->getConfiguration("Password");
		if($this->_sip == null){
			$this->_sip = new sip(network ::getNetworkAccess('internal', 'ip', '', false),$cache->getValue('');
			if($this->getConfiguration("Proxy")!="") 
				$this->_sip->setProxy($this->getConfiguration("Proxy"));
			$this->_sip->setUsername($this->_Username);
			$this->_sip->setPassword($this->_Password);
			$this->_sip->setServerMode(true);
		}
	}
	private function RegisterClient(){
		if($this->_sip == null)			
        	  	$this->CreateConnexion();
		$this->checkAndUpdateCmd('RegStatus','Inactif');
		$this->_sip->addHeader('Expires: '.$this->getConfiguration("Expiration"));
		$this->_sip->setMethod('REGISTER');
		if($this->getConfiguration("Proxy")!="") 
			$this->_sip->setProxy($this->getConfiguration("Proxy"));
		$this->_sip->setFrom('sip:'.$this->_Username.'@'.$this->_Host.':'.$this->_Port);
		$this->_sip->setUri('sip:'.$this->_Username.'@'.$this->_Host.':'.$this->_Port.';transport='.$this->getConfiguration("transport"));
		$res = $this->_sip->send();
		if ($res == '200')
			$this->checkAndUpdateCmd('RegStatus','OK');
		else
			$this->checkAndUpdateCmd('RegStatus','Echec');			
		cache::set('clientSIP::Port::'.$this->getId(), $this->_sip->getPort(), 0);
	}
	public function RepondreAppel() {
		$call['status']='ringing'; 
		$call['flow']='incoming';  
		$call['number']='';  
		$call['start']=date('d/m/Y H:i:s');  
		self::addHistoryCall($call);
		//$this->_sip->reply(100,'Trying');
		$this->_sip->reply(180,'Ringing');
		$this->checkAndUpdateCmd('CallStatus','Sonnerie');
		event::add('clientSIP::call', utils::o2a($this));
		$CallStatus=$this->getCmd(null,'CallStatus');
		$call['status']= 'ringing';
		while($CallStatus->execCmd() == 'Sonnerie');
		self::addHistoryCall($call);
		switch($CallStatus->execCmd()){
			case 'Decrocher':
				$call['status']= 'call';
				$this->Decrocher();
			break;
			case 'Racrocher':
				$call['status']= 'reject';
				$this->Racrocher();
			return;
		}
		self::addHistoryCall($call);
	}
	public function Decrocher() {
		//ajouter les options de compatibilité de jeedom
		$this->_sip->reply(200,'Ok');
		event::add('clientSIP::rtsp', $this->_sip->getBody());
		$this->checkAndUpdateCmd('CallStatus','Décrocher');
		while($CallStatus->execCmd() == 'Decrocher')
			sleep(5);
		$this->Racrocher();
	}
	public function Racrocher() {
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
		$this->checkAndUpdateCmd('CallStatus','Racrocher');
	}
	public function call($number) {	
		log::add('clientSIP', 'debug', 'Appel en demandé => ' . $number);
		$this->checkAndUpdateCmd('CallStatus','Racrocher');	
		$this->CreateConnexion();
		$this->_sip->setUsername($this->_Username);
		$this->_sip->setPassword($this->_Password);
		$this->_sip->newCall();
		$this->_sip->setFrom('sip:'.$this->_Username.'@'.$this->_Host);
		$this->_sip->setUri('sip:'.$number.'@'.$this->_Host.':'.$this->_Port.';transport='.$this->getConfiguration("transport"));
		$this->_sip->setTo('sip:'.$number.'@'.$this->_Host.':'.$this->_Port);
		$this->_sip->setMethod('INVITE');
		$this->checkAndUpdateCmd('CallStatus','Sonnerie');
		$res=$this->_sip->send();
		$CallStatus=$this->getCmd(null,'CallStatus');
		while($CallStatus->execCmd() == 'Sonnerie'){
			$this->actionResCode();
		}
		switch($CallStatus->execCmd()){
			case 'Decrocher':
				$call['status']= 'call';
				self::addHistoryCall($call);
				$this->Decrocher();
				while($CallStatus->execCmd() == 'Decrocher'){
					$this->actionResCode();
				}
				$call['status']= 'close';
				self::addHistoryCall($call);
				$this->Racrocher();
			break;
			case 'Racrocher':
				$call['status']= 'reject';
				self::addHistoryCall($call);
				$this->Racrocher();
			return;
		}
	}
	public function sendMessage($number,$message) {	
		log::add('clientSIP', 'debug', 'Appel en demandé => ' . $number);
		$this->checkAndUpdateCmd('CallStatus','Racrocher');	
		$this->CreateConnexion();
		$this->_sip->setUsername($this->_Username);
		$this->_sip->setPassword($this->_Password);
		$this->_sip->newCall();
		$this->_sip->setFrom('sip:'.$this->_Username.'@'.$this->_Host);
		$this->_sip->setUri('sip:'.$number.'@'.$this->_Host.':'.$this->_Port.';transport='.$this->getConfiguration("transport"));
		$this->_sip->setTo('sip:'.$number.'@'.$this->_Host.':'.$this->_Port);
		$this->_sip->setBody($message);
		$this->_sip->setMethod('MESSAGE');
		$res=$this->_sip->send();
		if ($res == '200')
			event::add('clientSIP::message', 'Le message a bien été transmis');
		
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
	private function actionResCode(){
		switch($this->_sip->getResCode()){
			case 100:
				$this->checkAndUpdateCmd('CallStatus','Appel en cours');
				$this->_sip->reply(100,'Trying');
			break;
			case 180:
				$this->checkAndUpdateCmd('CallStatus','Sonnerie');
				$this->_sip->reply(180,'Ringing');
			break;
			case '200':
				$this->checkAndUpdateCmd('CallStatus','Décroché');
				$this->_sip->reply(200,'OK');
			break;
			case '486':
				$this->checkAndUpdateCmd('CallStatus','Décroché');
			break;
		}
	}
	public function AddCommande($Name,$_logicalId,$Type="info", $SubType='string',$Template='') {
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
}
class clientSIPCmd extends cmd {
	public function execute($_options = null){
		switch($this->getLogicalId()){
			case 'call':				
				$this->getEqLogic()->call($_options['message']);
			break;
			case 'message':				
				$this->getEqLogic()->sendMessage($_options['title'],$_options['message']);
			break;
		}
	}
}
?>
