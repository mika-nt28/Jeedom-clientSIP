<?php
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";
if (!jeedom::apiAccess(init('apikey'), 'clientSIP')) {
	echo 'Clef API non valide, vous n\'etes pas autorisé à effectuer cette action';
	die();
}
$result = json_decode(file_get_contents("php://input"), true);
if (isset($result['devices'])) {
	foreach ($result['devices'] as $Client => $datas) {
		$clientSIP = eqLogic::byId($Client);
		if(is_object($clientSIP)){
			log::add('clientSIP','debug',$clientSIP->getHumanName().' Nouvelle data du demon ' . json_encode($datas));	
			if(isset($datas['RegStatus'])){
				$RegStatus = $clientSIP->getCmd(null,'RegStatus');
				$RegStatus->event($datas['RegStatus']);
			}
			if(isset($datas['CallStatus'])){
				$CallStatus = $clientSIP->getCmd(null,'CallStatus');
				$CallStatus->event($datas['CallStatus']);
			}
			if(isset($datas['Numero'])){
				foreach($clientSIP->getConfiguration('InCallEventAttr') as $CallEvent){
					if($CallEvent['Numero'] == '' || $CallEvent['Numero'] == $datas['Numero']){
						$Message[] = $this->TextToSpeach($CallEvent['Message']);
						$value['apikey'] = jeedom::getApiKey('clientSIP');
						$value['cmd'] = 'call';
						$value['pause'] = 5;
						$value['Message'] = $Message;
						clientSIP::socket_connection(json_encode($value));
						if(isset($datas['dtmf']) && $CallEvent['action']['enable'] && $CallEvent['action']['dtmf'] == $datas['dtmf']){
							$cmd = cmd::byId($CallEvent['action']['cmd']);
							if(is_object($cmd))
								$cmd->execute();
						}
					}
				}
			}
		}
	}
}
