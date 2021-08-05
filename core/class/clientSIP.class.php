<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'client', 'class', 'clientSIP');
class clientSIP extends eqLogic { 
	protected $_sip = null;
	protected $_Host=null;
	protected $_Port=null;
	protected $_Username= null;
	protected $_Password= null;
	protected $_CallNumber= null;
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
		$return['state'] = 'nok';
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			if($clientSIP->getIsEnable()){
				if($clientSIP->getConfiguration("Expiration") != ''){
					$cron = cron::byClassAndFunction('clientSIP', 'ConnectSip', array('id' => $clientSIP->getId()));
					if (!is_object($cron))  	
						return $return;
				}
				$cron = cron::byClassAndFunction('clientSIP', 'WaitCall', array('id' => $clientSIP->getId()));
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
		$directory=jeedom::getTmpFolder('clientSIP');
		$directory = calculPath($directory);
		if(!file_exists($directory))
			exec('sudo mkdir -p -m 777 '.$directory);
		if (!is_writable($directory)) 
			exec('sudo chmod 777 -R '.$directory);
		$cache = cache::byKey('clientSIP::HistoryCall');
		$cache->remove();
		foreach(eqLogic::byType('clientSIP') as $clientSIP){
			if($clientSIP->getIsEnable()){
				if($clientSIP->getConfiguration("Expiration") != ''){
					$minute=round($clientSIP->getConfiguration("Expiration")/60,0);
					$clientSIP->CreateDemon('ConnectSip','*/'.$minute.' * * * *',false); 
				}
				$clientSIP->CreateDemon('WaitCall','* * * * * *',true);   
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
	public static function WaitCall($_option){
		$clientSIP = clientSIP::byId($_option['id']);
		if (is_object($clientSIP) && $clientSIP->getIsEnable()) {
			if(!is_object($clientSIP->_sip))
				$clientSIP->CreateConnexion(true);
			while($clientSIP->_sip->isConnect()){
				$message = $clientSIP->_sip->listen();
				switch($message->method){
					case 'INVITE':
						log::add('clientSIP', 'debug', 'Réception d\'un appel');
						sleep(1);
						$clientSIP->_sip->reply($message,100);
						sleep(1);
						$clientSIP->_sip->reply($message,180);
						sleep(1);
						$clientSIP->_sip->reply($message,200);
						sleep(1);
						$clientSIP->checkAndUpdateCmd('CallStatus','Appel en cours');
						$clientSIP->calling($message, 'InCallEvent');
						$clientSIP->Racrocher($message);
					break;
					case 'MESSAGE':
						//message::add('sucess', $clientSIP->_sip->getBody());
					break;
					case 'NOTIFY':
					break;
				}
			}
		}
	}	
	private function CreateConnexion($socket_bind = false){
		if($this->_sip == null)
			//$this->getConfiguration("Expiration");
			//$this->getConfiguration("Proxy");
			$this->_sip = new client(network ::getNetworkAccess('internal', 'ip', '', false),
						$this->getConfiguration("Port"),
						$this->getConfiguration("CallNumber"),
						$this->getConfiguration("Username"),
						$this->getConfiguration("Password"),
						$this->getName(),
						$socket_bind);
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
	public function Racrocher() {
		if($this->_sip == null)			
			$this->CreateConnexion(false);
		$CallStatus=$this->getCmd(null,'CallStatus');
		if ($this->_sip->request('BYE',$message)->code == '200')		
			$this->checkAndUpdateCmd('CallStatus','Racrocher');
	}
	public function call($number) {	
		log::add('clientSIP', 'debug', 'Appel en demandé => ' . $number);
		$this->checkAndUpdateCmd('CallStatus','Racrocher');
		if($this->_sip == null)			
			$this->CreateConnexion();
		$this->checkAndUpdateCmd('CallStatus','Sonnerie');
		$message = $this->_sip->newCall($number);
		if ($message->code == '200')
			$this->checkAndUpdateCmd('CallStatus','Appel en cours');
		else
			$this->checkAndUpdateCmd('CallStatus','Racrocher');
		sleep(5);
		$this->calling($message, 'OutCallEvent');
		$this->Racrocher($message);
	}
	public function sendMessage($number,$texte) {	
		log::add('clientSIP', 'debug', 'Envoie un message => ' . $number);
		if($this->_sip == null)			
			$this->CreateConnexion();
		if ($this->_sip->newMessage($number,$texte)->code == '200')
			log::add('clientSIP', 'debug', 'Message envoyé => ' . $number);
	}
	public function calling($message, $CallEvents){
		foreach($this->getConfiguration($CallEvents) as $CallEvent){
			$number = str_replace('sip:','',explode('@', $message->to->addr)[0]);
			if($CallEvent['Numero'] == '' || $CallEvent['Numero'] == $number){
				$SdpFile = jeedom::getTmpFolder('clientSIP').'/' . $this->getName(). '.sdp';
				$fp =fopen($SdpFile,"w");
				fwrite($fp,$message->body);
				fclose($fp);
				$cmd ='ffmpeg -loglevel debug -protocol_whitelist file,crypto,udp,rtp -re -vcodec libvpx -acodec opus -i '.$SdpFile.' -vcodec libx264 -acodec aac -y ';
				$cmd .= $this->TextToSpeach($CallEvent['Message']); 
				/*$cmd ='ffmpeg -i ';
				$cmd .= $this->TextToSpeach($CallEvent['Message']).' ';
				$cmd .= $this->getFFMEGcodec($message). ' ';
				$cmd .= $this->getRtspUrl($message);
				$cmd .= ' >> ' . log::getPathToLog('clientSIP');*/
				exec($cmd);
				sleep(5);
			}
		}
	}
	public function getRtspUrl($message){
		switch($message->body->SessionMediaDescription[0]->protocol){
			default:
			case 'RTP/AVP':
				return 'rtp://'.$message->body->SessionConnexion->adresse.':'.$message->body->SessionMediaDescription->port;
		}
	}
	public function getFFMEGcodec($message){
		switch($message->body->SessionMediaDescription[0]->codec[0]){
			case 0:
				//a=rtpmap:0 PCMU/8000
			return '-f mulaw';//-acodec mulaw ';
			case 3:
				//a=rtpmap:3 GSM/8000			
			return '-f gsm';//-acodec gsm ';
			case 4:
				//a=rtpmap:4 G723/8000		
			return '-f g723';//-acodec g723 ';
			case 8:
				//a=rtpmap:8 PCMA/8000		
			return '-f alaw';//-acodec alaw ';
			case 18:
				//a=rtpmap:18 G729/8000
			return '-f g729';//-acodec g729 ';
		}
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
				$cmd = 'espeak -v' . $voice . ' "' . $Texte . '" --stdout | '.$avconv.' -i - -ar 44100 -ac 2 -ab 192k -f mp3 ' . $SpeachFile . ' > /dev/null 2>&1';
				log::add('clientSIP', 'debug', $cmd);
				shell_exec($cmd);
			}else if($engine == 'pico'){
				$volume = '-af "volume=' . init('volume', '6') . 'dB"';
				$lang = str_replace('_','-',init('lang',config::byKey('language')));
				$avconv = 'avconv';
				if(!com_shell::commandExists('avconv')){
					$avconv = 'ffmpeg';
				}
				$cmd = 'pico2wave -l=' . $lang . ' -w=' . $md5 . '.wav "' . $Texte . '" > /dev/null 2>&1;';
				$cmd .= $avconv.' -i ' . $md5 . '.wav -ar 44100 ' . $volume . ' -ac 2 -ab 192k -f mp3 ' . $SpeachFile . ' > /dev/null 2>&1;rm ' . $md5 . '.wav';
				log::add('clientSIP', 'debug', $cmd);
				shell_exec($cmd);
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
