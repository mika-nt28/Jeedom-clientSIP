<?php
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";
if (!jeedom::apiAccess(init('apikey'), 'clientSIP')) {
	echo 'Clef API non valide, vous n\'etes pas autorisé à effectuer cette action';
	die();
}
$result = json_decode(file_get_contents("php://input"), true);
if (isset($result['devices'])) {
	foreach ($result['devices'] as $Client => $datas) {
		log::add('clientSIP','debug','['.$Client.'] Detection ' . json_encode($datas));	
		
	}
}
