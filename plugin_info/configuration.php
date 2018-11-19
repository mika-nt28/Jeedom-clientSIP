<?php
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}
?>
 <div class="col-sm-6">
	<form class="form-horizontal">
		<legend>Serveur SIP</legend>
		<fieldset>
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

 <div class="col-sm-6">
	<form class="form-horizontal">
		<legend>Serveur SIP</legend>
		<fieldset>
			<div class="form-group">
				<label class="col-lg-4 control-label">u-law</label>
				<div class="col-lg-4">
					<input type="checkbox" class="configKey form-control" data-l1key="codec" data-l2key="ulaw" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-lg-4 control-label">a-law</label>
				<div class="col-lg-4">
					<input type="checkbox" class="configKey form-control" data-l1key="codec" data-l2key="alaw" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-lg-4 control-label">GSM</label>
				<div class="col-lg-4">
					<input type="checkbox" class="configKey form-control" data-l1key="codec" data-l2key="gsm" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-lg-4 control-label">ILBC</label>
				<div class="col-lg-4">
					<input type="checkbox" class="configKey form-control" data-l1key="codec" data-l2key="ilbc" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-lg-4 control-label">SPEEX</label>
				<div class="col-lg-4">
					<input type="checkbox" class="configKey form-control" data-l1key="codec" data-l2key="speex" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-lg-4 control-label">G.726</label>
				<div class="col-lg-4">
					<input type="checkbox" class="configKey form-control" data-l1key="codec" data-l2key="g726" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-lg-4 control-label">ADPCM</label>
				<div class="col-lg-4">
					<input type="checkbox" class="configKey form-control" data-l1key="codec" data-l2key="adpcm" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-lg-4 control-label">LPC10</label>
				<div class="col-lg-4">
					<input type="checkbox" class="configKey form-control" data-l1key="codec" data-l2key="lpc10" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-lg-4 control-label">G.726</label>
				<div class="col-lg-4">
					<input type="checkbox" class="configKey form-control" data-l1key="codec" data-l2key="g729" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-lg-4 control-label">G.723</label>
				<div class="col-lg-4">
					<input type="checkbox" class="configKey form-control" data-l1key="codec" data-l2key="g723" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-lg-4 control-label">H.263</label>
				<div class="col-lg-4">
					<input type="checkbox" class="configKey form-control" data-l1key="codec" data-l2key="h263" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-lg-4 control-label">H.263P</label>
				<div class="col-lg-4">
					<input type="checkbox" class="configKey form-control" data-l1key="codec" data-l2key="h263p" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-lg-4 control-label">H.264</label>
				<div class="col-lg-4">
					<input type="checkbox" class="configKey form-control" data-l1key="codec" data-l2key="h264" />
				</div>
			</div>
		</fieldset>
	</form>
</div>
