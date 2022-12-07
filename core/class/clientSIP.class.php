<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
class clientSIP extends eqLogic { 
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'clientSIP';
		$return['launchable'] = 'nok';
		$engine = config::byKey('tts::engine','core','pico');
			if($engine == 'espeak'){
				$cmd = "dpkg -l | grep espeak";
				if(exec($cmd) == '')
					return $return;
			
			}else if($engine == 'pico'){
				$cmd = "dpkg -l | grep libttspico-utils";
				if(exec($cmd) == '')
					return $return;
			}
			else
				return $return;
		$return['launchable'] = 'ok';
		$return['state'] = 'ok';
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			$pid_file = jeedom::getTmpFolder('clientSIP') . '/clientSIP_'.$clientSIP->getId().'.pid';
			if (file_exists($pid_file)) {
				if (!@posix_getsid(trim(file_get_contents($pid_file)))) {
					$return['state'] = 'nok';
					return $return;	
				}
			}else{
				$return['state'] = 'nok';
				return $return;	
			}
			if($clientSIP->getIsEnable()){
				if($clientSIP->getConfiguration("Expiration") != ''){
					$cron = cron::byClassAndFunction('clientSIP', 'ConnectSip', array('id' => $clientSIP->getId()));
					if (!is_object($cron)){ 
						$return['state'] = 'nok'; 	
						return $return;
					}
				}
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
		$directory=jeedom::getTmpFolder('clientSIP');
		$directory = calculPath($directory);
		if(!file_exists($directory))
			exec('sudo mkdir -p -m 777 '.$directory);
		if (!is_writable($directory)) 
			exec('sudo chmod 777 -R '.$directory);
		$path = realpath(dirname(__FILE__) . '/../python');
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			$cmd = 'sudo /usr/bin/python3 ' . $path . '/clientSIP.py';
			$cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel('clientSIP'));
			$cmd .= ' --sockethost 127.0.0.1';
			$cmd .= ' --socketport 9090';
			$cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/clientSIP/core/php/callback.php';
			$cmd .= ' --apikey ' . jeedom::getApiKey('clientSIP');
			$cmd .= ' --pid ' . jeedom::getTmpFolder('clientSIP') . '/clientSIP_'.$clientSIP->getId().'.pid';
			$cmd .= ' --serverhost '.config::byKey('Host');
			$cmd .= ' --serverport '.config::byKey('Port');
			$cmd .= ' --clienthost '.$clientSIP->getId();
			$cmd .= ' --clientport '.$clientSIP->getConfiguration("Port");
			$cmd .= ' --username '.$clientSIP->getConfiguration("Username");
			$cmd .= ' --userpass '.$clientSIP->getConfiguration("Password");
			$cmd .= ' --jeedomId '.$clientSIP->getId();
			log::add('clientSIP', 'info', 'Lancement démon clientSIP : ' . $cmd);
			$result = exec($cmd . ' >> ' . log::getPathToLog('clientSIP') . ' 2>&1 &');
	
			if($clientSIP->getIsEnable()){
				if($clientSIP->getConfiguration("Expiration") != ''){
					$minute=round($clientSIP->getConfiguration("Expiration")/60,0);
					$clientSIP->CreateDemon('ConnectSip','*/'.$minute.' * * * *',false); 
				}
			}
		}
	}
	public static function deamon_stop() {	
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			$clientSIP->checkAndUpdateCmd('RegStatus','Inactif');
			$clientSIP->checkAndUpdateCmd('CallStatus','Racrocher');
			$pid_file = jeedom::getTmpFolder('clientSIP') . '/clientSIP_'.$clientSIP->getId().'.pid';
			if (file_exists($pid_file)) {
				$pid = intval(trim(file_get_contents($pid_file)));
				system::kill($pid);
			}
			system::kill('clientSIP.py');
			if($clientSIP->getConfiguration("Expiration") != ''){
				$cron = cron::byClassAndFunction('clientSIP', 'ConnectSip', array('id' => $clientSIP->getId()));
				if (is_object($cron)) 	
					$cron->remove();
			}
			$cache = cache::byKey('clientSIP::Port::'.$clientSIP->getId());
			if (is_object($cache)) 	
				$cache->remove();
		}
	}	
	public static function socket_connection($value){
		try {
			$socket = socket_create(AF_INET, SOCK_STREAM, 0);
			socket_connect($socket, '127.0.0.1', 9090);
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
		$this->AddCommande('Message','message','action','message','notif');
		$this->checkAndUpdateCmd('RegStatus','Inactif');
	}
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
	
	private function RegisterClient(){
		if($this->_sip == null)			
			$this->CreateConnexion(false);
		$this->checkAndUpdateCmd('RegStatus','Inactif');
		$return = $this->_sip->request('REGISTER');
		if ($return->code == '200')
			$this->checkAndUpdateCmd('RegStatus','OK');
		else
			$this->checkAndUpdateCmd('RegStatus','Echec');
	}
	public function Racrocher($message) {
		if($this->_sip == null)			
			$this->CreateConnexion(false);
		$CallStatus=$this->getCmd(null,'CallStatus');
		if ($this->_sip->request('BYE',$message)->code == '200')		
			$this->checkAndUpdateCmd('CallStatus','Racrocher');
	}
	public function call($number, $CallEvents){
		$Message = array();
		foreach($this->getConfiguration($CallEvents) as $CallEvent){
			$number = str_replace('sip:','',explode('@', $message->to->addr)[0]);
			if($CallEvent['Numero'] == '' || $CallEvent['Numero'] == $number)
				$Message[] = $this->TextToSpeach($CallEvent['Message']);
		}
		
		$value['apikey'] = jeedom::getApiKey('clientSIP');
		$value['cmd'] = 'call';
		$value['pause'] = 5;
		$value['Message'] = $Message;
		self::socket_connection(json_encode($value));
	}
	public function AddCommande($Name,$_logicalId,$Type="info", $SubType='string',$Template='default') {
		$Commande = $this->getCmd(null,$_logicalId);
		if (!is_object($Commande)){
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
	public function sendDTMF($DTMF) {
		$value['apikey'] = jeedom::getApiKey('clientSIP');
		$value['cmd'] = 'sendDTMF';
		$value['dtmf'] = $DTMF;
		self::socket_connection(json_encode($value));
	}
	public function TextToSpeach($Texte) {
		$Texte = str_replace(array('[', ']', '#', '{', '}'), '', $Texte);
		$md5 = md5($Texte);
		$tts_dir = jeedom::getTmpFolder('clientSIP');
		$SpeachFile = $tts_dir . '/' . $md5 . '.mp3';
		if (!file_exists($SpeachFile)) {
			$engine = config::byKey('tts::engine','core','pico');
			if($engine == 'espeak'){
				$voice = init('voice', 'fr+f4');
				$avconv = 'avconv';
				if(!com_shell::commandExists('avconv')){
					$avconv = 'ffmpeg';
				}				
				$cmd = 'espeak -v' . $voice . ' "' . $Texte . '" --stdout | '.$avconv.' -i - -ar 44100 -ac 2 -ab 192k -f mp3 ' . $SpeachFile;
				shell_exec($cmd);			
				log::add('clientSIP', 'debug', $cmd);
			}else if($engine == 'pico'){
				$volume = '-af "volume=' . init('volume', '6') . 'dB"';
				$lang = str_replace('_','-',init('lang',config::byKey('language')));
				$avconv = 'avconv';
				if(!com_shell::commandExists('avconv')){
					$avconv = 'ffmpeg';
				}
				$cmd = 'pico2wave -l=' . $lang . ' -w=' .$SpeachFile .' "' . $Texte . '"';
				$cmd .= $avconv.' -i ' . $md5 . '.wav -ar 44100 ' . $volume . ' -ac 2 -ab 192k -f mp3 ' . $SpeachFile;
				shell_exec($cmd);
				log::add('clientSIP', 'debug', $cmd);
				shell_exec('sudo rm ' . $md5 . '.wav');			
			}else{
				$engine::tts($SpeachFile,$Texte);
			}
		}	
		return $SpeachFile;
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
				$this->getEqLogic()->call($_options['title']);
			break;
			case 'message':				
				$this->getEqLogic()->sendMessage($_options['title'],$_options['message']);
			break;
		}
	}
}
?>
