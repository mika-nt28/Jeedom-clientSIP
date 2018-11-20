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
		<legend>{{Codec supportés}}
			<a class="btn btn-success btn-xs pull-right cursor" id="bt_AddCodec"><i class="fa fa-check"></i> {{Ajouter}}</a>
		</legend>
		<form class="form-horizontal">
			<fieldset>
				<div class="form-group">
					<table id="table_codec" class="table table-bordered table-condensed tablesorter">
						<thead>
							<tr>
								<th>{{Type}}</th>
								<th>{{Port}}</th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</fieldset>
		</form>
	</div>
</div>
<script>
	$("body").on('click', ".listAction", function() {
		var el = $(this).closest('.input-group').find('input');
		jeedom.cmd.getSelectModal({}, function (result) {
			el.value(result.human);
		});
	});
	$.ajax({
		type: "POST",
		timeout:8000,
		url: "core/ajax/config.ajax.php",
		data: {
			action:'getKey',
			key:'{"codec":""}',
			plugin:'clientSIP',
		},
		dataType: 'json',
		error: function(request, status, error) {
			handleAjaxError(request, status, error);
		},
		success: function(data) {
			if (data.state != 'ok') {
				$('#div_alert').showAlert({message: data.result, level: 'danger'});
				return;
			}
			if (data.result['configuration']!=''){
				var Codec= new Object();
				$.each(data.result['configuration'], function(param,valeur){
					switch(typeof(valeur)){
						case 'object':
							$.each(valeur, function(Codeckey,value ){
								if (typeof(Codec[Codeckey]) === 'undefined')
									Codec[Codeckey]= new Object();
								if (typeof(Codec[Codeckey]['codec']) === 'undefined')
									Codec[Codeckey]['configuration']= new Object();
								Codec[Codeckey]['codec'][param]=value;
							});
						break;
						case 'string':
							if (typeof(Codec[0]) === 'undefined')
								Codec[0]= new Object();
							if (typeof(Codec[0]['codec']) === 'undefined')
								Codec[0]['codec']= new Object();
							Codec[0]['codec'][param]=valeur;
						break;
					}
				});
				$.each(Codec, function(id,data){
					AddCodec($('#table_codec tbody'),data);
				});
			}
		}
	});
	$('#bt_AddCodec').on('click',function(){
		AddCodec($('#table_codec tbody'),'');
	});
	$('body').on('click','#bt_RemoveCodec',function(){
		$(this).closest('tr').remove();
	});
	function AddCodec(_el,data){
		var tr=$('<tr>');
		tr.append($('<td>')
			.append($('<div class="input-group">')
				.append($('<span class="input-group-btn">')
					.append($('<a class="btn btn-default btn-sm bt_RemoveCodec">')
						.append($('<i class="fa fa-minus-circle">'))))
				.append($('<select class="configKey form-control input-sm "data-l1key="codec" data-l2key="type">')
					.append($('<option value="ulaw">').text('u-law'))
					.append($('<option value="alaw">').text('a-law'))
					.append($('<option value="gsm">').text('GSM'))
					.append($('<option value="ilbc">').text('ILBC'))
					.append($('<option value="speex">').text('SPEEX'))
					.append($('<option value="g726">').text('G.726'))
					.append($('<option value="adpcm">').text('ADPCM'))
					.append($('<option value="lpc10">').text('LPC10'))
					.append($('<option value="g729">').text('G.729'))
					.append($('<option value="g723">').text('G.723'))
					.append($('<option value="h263">').text('H.263'))
					.append($('<option value="h263p">').text('H.263p'))
					.append($('<option value="h264">').text('H.264')))));
		tr.append($('<td>')
			.append($('<input class="configKey form-control input-sm" data-l1key="codec" data-l2key="port">')));
		_el.append(tr);
		_el.find('tr:last').setValues(data, '.configKey');
	}
</script>
