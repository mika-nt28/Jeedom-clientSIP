<?php
	require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
	include_file('core', 'authentification', 'php');
	if (!isConnect()) {
		include_file('desktop', '404', 'php');
		die();
	}
?>
<div class="row">
	<div class="col-sm-6">
		<form class="form-horizontal">
			<legend>Serveur SIP</legend>
			<fieldset>
				<div class="form-group">
					<label class="col-lg-4 control-label">{{STUN :}}</label>
					<div class="col-lg-4">
						<input class="configKey form-control" data-l1key="Stun" />
					</div>
				</div>
				<div class="form-group">
					<label class="col-lg-4 control-label">{{Adresse IP :}}</label>
					<div class="col-lg-4">
						<input class="configKey form-control" data-l1key="Host" />
					</div>
				</div>
				<div class="form-group">
					<label class="col-lg-4 control-label">{{Port :}}</label>
					<div class="col-lg-4">
						<input class="configKey form-control" data-l1key="Port" />
					</div>
				</div>
			</fieldset>
		</form>
	</div>
</div>
