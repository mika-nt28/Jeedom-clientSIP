<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
class clientSIP extends eqLogic { 
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'clientSIP';
		$return['launchable'] = 'ok';
		$return['state'] = 'nok';
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			$pid_file = jeedom::getTmpFolder('clientSIP') . '/clientSIP_'.$clientSIP->getId().'.pid';
			if (file_exists($pid_file)) {
				if (!@posix_getsid(trim(file_get_contents($pid_file)))) 
					return $return;	
			}else
				return $return;	
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
		$path = realpath(dirname(__FILE__) . '/../python');
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			//$cmd = 'sudo /usr/bin/python3 ' . $path . '/clientSIP.py';
			$cmd = 'sudo nice -n 19 ' . realpath(dirname(__FILE__) . '/../../resources') . '/python_venv/bin/python3 ' . $path . '/clientSIP.py';
			$cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel('clientSIP'));
			$cmd .= ' --sockethost 127.0.0.1';
			$cmd .= ' --socketport 9091';
			$cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/clientSIP/core/php/callback.php';
			$cmd .= ' --apikey ' . jeedom::getApiKey('clientSIP');
			$cmd .= ' --pidfile ' . jeedom::getTmpFolder('clientSIP') . '/clientSIP_'.$clientSIP->getId().'.pid';
			$cmd .= ' --serverhost '.config::byKey('Host','clientSIP');
			$cmd .= ' --serverport '.config::byKey('Port','clientSIP');
			$cmd .= ' --clienthost '.config::byKey('Host','clientSIP');//.network::getNetworkAccess('internal');
			$cmd .= ' --clientport '.$clientSIP->getConfiguration("Port");
			$cmd .= ' --username '.$clientSIP->getConfiguration("Username");
			$cmd .= ' --userpass '.$clientSIP->getConfiguration("Password");
			$cmd .= ' --jeedomId '.$clientSIP->getId();
			log::add('clientSIP', 'info', 'Lancement démon clientSIP : ' . $cmd);
			$result = exec($cmd . ' >> ' . log::getPathToLog('clientSIP') . ' 2>&1 &');
		}
	}
	public static function deamon_stop() {	
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			$clientSIP->checkAndUpdateCmd('RegStatus','INACTIVE');
			$clientSIP->checkAndUpdateCmd('CallStatus','ENDED');
			$pid_file = jeedom::getTmpFolder('clientSIP') . '/clientSIP_'.$clientSIP->getId().'.pid';
			if (file_exists($pid_file)) {
				$pid = intval(trim(file_get_contents($pid_file)));
				system::kill($pid);
			}
			system::kill('clientSIP.py');
		}
	}	
	public static function socket_connection($value){
		try {
			$socket = socket_create(AF_INET, SOCK_STREAM, 0);
			socket_connect($socket, '127.0.0.1', 9091);
			socket_write($socket, $value, strlen($value));
			socket_close($socket);
			return true;
		} catch (Exception $e) {
			return false;
		}
	}
	public function postSave() {
		$this->AddCommande('Etat connexion','RegStatus','info', 'string');
		$this->AddCommande('Etat appel','CallStatus','info', 'string','CallStatus');
		$this->AddCommande('Appel','call','action','message','call');
		$this->checkAndUpdateCmd('RegStatus','Inactif');
	}
	public function AddCommande($Name,$_logicalId,$Type="info", $SubType='string',$Template='default') {
		$Commande = $this->getCmd(null,$_logicalId);
		if (!is_object($Commande)){
			$Commande = new clientSIPCmd();
			$Commande->setId(null);
			$Commande->setEqLogic_id($this->getId());
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
		}
		return $Commande;
	}
	public function getMessage($number, $CallEvents){			
		$Message = array();
		foreach($this->getConfiguration($CallEvents) as $CallEvent){
			if($CallEvent['Numero'] == '' || $CallEvent['Numero'] == $number)
				$Message[] = $CallEvent['Message'];
		}
		return $Message;
	}
	public function getDtmfList($number, $CallEvents){			
		$dtmfList = array();
		foreach($this->getConfiguration($CallEvents) as $CallEvent){
			if($CallEvent['Numero'] == '' || $CallEvent['Numero'] == $number){
				foreach($CallEvent['action'] as $Action){
					if($Action['enable']){
						$Action['message'] = str_replace('#dtmf#',$Action['dtmf'],$Action['message']);
						$Action['message'] = jeedom::evaluateExpression($Action['message']);
						$dtmfList[] = $Action;
					}
				}
			}
		}
		return $dtmfList;
	}
	public function execDTMF($number, $dtmf){
		log::add('clientSIP','debug',$this->getHumanName().' Recherche des action pour le numero ' . $number . ' avec le DTMF ' . $dtmf);
		foreach($this->getConfiguration('InCallEvent') as $CallEvent){
			if($CallEvent['Numero'] == '' || $CallEvent['Numero'] == $number){
				foreach($CallEvent['action'] as $Action)
					$this->ExecuteAction($Action,$dtmf);
			}
		}
	}	
	private function CheckIsValid($Element,$dtmf){
		if(isset($Element['enable']) && $Element['enable'] == 0)
			return false;	
		if(isset($Element['dtmf']) && $Element['dtmf'] == $dtmf)
			return false;		
		return true;
	}
	public function ExecuteAction($Action,$dtmf){	
		try {				
			if(!$this->CheckIsValid($Action,$dtmf))
				return false;
			$_options=$this->EvaluateOptions($Action);
			scenarioExpression::createAndExec('action', $Action['cmd'], $_options);
			log::add('clientSIP','debug',$this->getHumanName().'Exécution de '.jeedom::toHumanReadable($Action['cmd']).' ('.json_encode($_options).')');
		} catch (Exception $e) {
			log::add('clientSIP', 'error',$this->getHumanName().''. __('Erreur lors de l\'exécution de ', __FILE__) . jeedom::toHumanReadable($Cmd['cmd']) . __('. Détails : ', __FILE__) . $e->getMessage());
			return false;
		}
	}
	public function EvaluateOptions($Cmd){
		$options = null;
		if(isset($Cmd['options'])){
			foreach($Cmd['options'] as $key => $option)
				$options[$key]=jeedom::evaluateExpression($option);
		}
		return $options;
	}
	public function call($number, $CallEvents){		
		$value['apikey'] = jeedom::getApiKey('clientSIP');
		$value['cmd'] = 'call';
		$value['pause'] = 5;
		$value['Numero'] = $number;
		$value['Message'] = $this->getMessage($number, $CallEvents);
		self::socket_connection(json_encode($value));
	}
	public function answer($number, $CallEvents){		
		$value['apikey'] = jeedom::getApiKey('clientSIP');
		$value['cmd'] = 'answer';
		$value['pause'] = 5;
		$value['Numero'] = $number;
		$value['Message'] = $this->getMessage($number, $CallEvents);
		$value['DTMFList'] = $this->getDtmfList($number, $CallEvents);
		self::socket_connection(json_encode($value));
	}
	public function sendDTMF($DTMF) {
		$value['apikey'] = jeedom::getApiKey('clientSIP');
		$value['cmd'] = 'sendDTMF';
		$value['dtmf'] = $DTMF;
		self::socket_connection(json_encode($value));
	}
	public static $_widgetPossibility = array('custom' => array(
	        'visibility' => true,
	        'displayName' => true,
	        'optionalParameters' => true,
	));
}
class clientSIPCmd extends cmd {
	public function execute($_options = null){
		switch($this->getLogicalId()){
			case 'call':			
				$this->getEqLogic()->call($_options['title'],'OutCallEvent');
			break;
		}
	}
}
?>
