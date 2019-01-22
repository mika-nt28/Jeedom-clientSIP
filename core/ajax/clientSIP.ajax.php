<?php
try {
	require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    	include_file('core', 'authentification', 'php');
    	/*if (!isConnect('admin')) {
        	throw new Exception(__('401 - Accès non autorisé', __FILE__));
    	}*/
	if (init('action') == 'updateCmd') {
   		$cmd=cmd::byId(init('id'));
    		if(is_object($cmd)){
		  	log::add('clientSIP','debug','Mise a jours du status: '.init('value'));
      			$cmd->event(init('value'));
		  	ajax::success(true);
		}
		ajax::success(false);
	}
	if (init('action') == 'getHistoryCall') {
		ajax::success(cache::byKey('clientSIP::HistoryCall')->getValue('[]'));
	}
	if (init('action') == 'clearHistoryCall') {
		cache::set('clientSIP::HistoryCall', '[]', 0);
		ajax::success(true);
	}
   throw new Exception(__('Aucune methode correspondante à : ', __FILE__) . init('action'));
} catch (Exception $e) {
    ajax::error(displayExeption($e), $e->getCode());
}
?>
