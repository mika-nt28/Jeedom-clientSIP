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
			if(isset($datas['Answer']))
				$clientSIP->answer($datas['Numero'], 'InCallEvent');
			if(isset($datas['DTMF']))
				$clientSIP->execDTMF($datas['Numero'], $datas['DTMF']);
		}
	}
}
