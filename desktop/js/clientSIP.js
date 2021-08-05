$("#div_InCallEvents").sortable({axis: "y", cursor: "move", items: ".InCallEventConf", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_OutCallEvents").sortable({axis: "y", cursor: "move", items: ".OutCallEventConf", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$('.eqLogicAction[data-action=gotoMoniteur]').off().on('click', function () {	
  	bootbox.dialog({
		title: "{{Moniteur}}",
		size: "large",
		message: $('<div>').load('index.php?v=d&modal=clientSIP.monitor&plugin=clientSIP&type=clientSIP'),
		
	});
});
function addCmdToTable(_cmd) {
	if (!isset(_cmd)) {
		var _cmd = {configuration: {}};
	}
	var tr =$('<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">');
  	tr.append($('<td>')
		.append($('<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove">'))
		.append($('<i class="fa fa-arrows-v pull-left cursor bt_sortable" style="margin-top: 9px;">')));
	tr.append($('<td>')
		.append($('<input type="hidden" class="cmdAttr form-control input-sm" data-l1key="id">'))
		.append($('<input class="cmdAttr form-control input-sm" data-l1key="name" value="' + init(_cmd.name) + '" placeholder="{{Name}}" title="Name">')));	
	tr.append($('<td>')	
			.append($('<span class="type" type="' + init(_cmd.type) + '">')
				.append(jeedom.cmd.availableType()))
			.append($('<span class="subType" subType="'+init(_cmd.subType)+'">')));
		var parmetre=$('<td>');
	if (is_numeric(_cmd.id)) {
		parmetre.append($('<a class="btn btn-default btn-xs cmdAction" data-action="test">')
			.append($('<i class="fa fa-rss">')
				.text('{{Tester}}')));
	}
	parmetre.append($('<a class="btn btn-default btn-xs cmdAction tooltips" data-action="configure">')
		.append($('<i class="fa fa-cogs">')));
	parmetre.append($('<a class="btn btn-default btn-xs cmdAction tooltips" data-action="copy" title="{{Dupliquer}}">')
		.append($('<i class="fa fa-files-o">')));
		parmetre.append($('<div>')
			.append($('<span>')
				.append($('<label class="checkbox-inline">')
					.append($('<input type="checkbox" class="cmdAttr checkbox-inline" data-size="mini" data-label-text="{{Historiser}}" data-l1key="isHistorized" checked/>'))
					.append('{{Historiser}}')
					.append($('<sup>')
						.append($('<i class="fa fa-question-circle tooltips" style="font-size : 1em;color:grey;">')
						.attr('title','Souhaitez vous Historiser les changements de valeur'))))));
		parmetre.append($('<div>')
			.append($('<span>')
				.append($('<label class="checkbox-inline">')
					.append($('<input type="checkbox" class="cmdAttr checkbox-inline" data-size="mini" data-label-text="{{Afficher}}" data-l1key="isVisible" checked/>'))
					.append('{{Afficher}}')
					.append($('<sup>')
						.append($('<i class="fa fa-question-circle tooltips" style="font-size : 1em;color:grey;">')
						.attr('title','Souhaitez vous afficher cette commande sur le dashboard'))))));
	tr.append(parmetre);
	$('#table_cmd tbody').append(tr);
	$('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
}
function saveEqLogic(_eqLogic) {
	_eqLogic.configuration.InCallEvent=new Array();
	_eqLogic.configuration.OutCallEvent=new Array();
	$('#div_InCallEvents .InCallEventConf').each(function () {
		var InCallEventConf = $(this).getValues('.InCallEventAttr')[0];
		_eqLogic.configuration.InCallEvent.push(InCallEventConf);
	});
	$('#div_OutCallEvents .OutCallEventConf').each(function () {
		var OutCallEventConf = $(this).getValues('.OutCallEventAttr')[0];
		_eqLogic.configuration.OutCallEvent.push(OutCallEventConf);
	});
   	return _eqLogic;
}
function printEqLogic(_eqLogic) {
	$('#div_InCallEvents .InCallEvent').remove();
	$('#div_OutCallEvents .InCallEventConf').remove();
	for (var i in _eqLogic.configuration.InCallEvent) {
		addInCallEventAct(_eqLogic.configuration.InCallEvent[i]);
	}
	for (var i in _eqLogic.configuration.OutCallEvent) {
		addOutCallEventAct(_eqLogic.configuration.OutCallEvent[i]);
	}
}
$('.InCallEventAct[data-action=add]').off().on('click',function(){
	bootbox.prompt("{{Nom du groupe de message ?}}", function (result) {
		if (result !== null && result != '') {
			addInCallEventAct({name: result});
		}
	});
});
$('.OutCallEventAct[data-action=add]').off().on('click',function(){
	bootbox.prompt("{{Nom du groupe de message ?}}", function (result) {
		if (result !== null && result != '') {
			addOutCallEventAct({name: result});
		}
	});
});
function addInCallEventAct(_action) {
    if (init(_action.name) == '') {
        return;
    }
    var random = Math.floor((Math.random() * 1000000) + 1);
    var div = $('<div class="InCallEventConf panel panel-default">')
    	.append($('<div class="panel-heading">')
    		.append($('<h4 class="panel-title">')
    			.append($('<a data-toggle="collapse" data-parent="#div_InCallEvents" href="#collapse' + random + '">')
    				.append($('<span class="name">')
					  .append(_action.name)))))
    	.append($('<div id="collapse' + random + '" class="panel-collapse collapse in">')
    		.append($('<div class="panel-body">')
		    	.append($('<div class="well">')
    				.append($('<form class="form-horizontal" role="form">')
    					.append($('<div class="form-group">')
    						.append($('<label class="col-sm-2 control-label">')
								.append('{{Nom du message d\'appel}}'))
    						.append($('<div class="col-sm-2">')
    							.append($('<span class="InCallEventAttr label label-info rename cursor" data-l1key="name" style="font-size : 1em;" >')))
    						.append($('<div class="col-sm-6">')
							.append($('<div class="btn-group pull-right" role="group">')
								.append($('<a class="btn btn-sm InCallEventAct btn-primary" data-action="remove">')
									.append($('<i class="fa fa-minus-circle">'))
									.append('{{Supprimer}}')))))
						.append($('<div class="form-group">')
							.append($('<label class="col-sm-2 control-label">')
								.append('{{Numéro de téléphone}}'))
							.append($('<div class="col-sm-12">')
								.append($('<input type="number" class="InCallEventAttr form-control roundedRight" data-l1key="Numero"  placeholder="{{Saisir le numero du correspondant ou laisser vide pour un message a tous les contactes}}"/>'))))
						.append($('<div class="form-group">')
							.append($('<label class="col-sm-2 control-label">')
								.append('{{Message a délivrer}}'))
							.append($('<div class="col-sm-12">')
								.append($('<textarea class="InCallEventAttr form-control roundedRight" data-l1key="Message"  placeholder="{{Saisir le message a transmettre}}">'))))
						.append($('<hr/>'))))));
	$('#div_InCallEvents').append(div);
	$('#div_InCallEvents .InCallEventConf:last').setValues(_action, '.InCallEventAttr');
	$('.collapse').collapse();

	$('.InCallEventAct[data-action=remove]').off().on( 'click',function () {
		$(this).closest('.InCallEventConf').remove();
	});
 }
function addOutCallEventAct(_action) {
    if (init(_action.name) == '') {
        return;
    }
    var random = Math.floor((Math.random() * 1000000) + 1);
    var div = $('<div class="OutCallEventConf panel panel-default">')
    	.append($('<div class="panel-heading">')
    		.append($('<h4 class="panel-title">')
    			.append($('<a data-toggle="collapse" data-parent="#div_OutCallEvents" href="#collapse' + random + '">')
    				.append($('<span class="name">')
					  .append(_action.name)))))
    	.append($('<div id="collapse' + random + '" class="panel-collapse collapse in">')
    		.append($('<div class="panel-body">')
		    	.append($('<div class="well">')
    				.append($('<form class="form-horizontal" role="form">')
    					.append($('<div class="form-group">')
    						.append($('<label class="col-sm-2 control-label">')
								.append('{{Nom du message d\'appel}}'))
    						.append($('<div class="col-sm-2">')
    							.append($('<span class="OutCallEventAttr label label-info rename cursor" data-l1key="name" style="font-size : 1em;" >')))
    						.append($('<div class="col-sm-6">')
							.append($('<div class="btn-group pull-right" role="group">')
								.append($('<a class="btn btn-sm OutCallEventAct btn-primary" data-action="remove">')
									.append($('<i class="fa fa-minus-circle">'))
									.append('{{Supprimer}}')))))
						.append($('<div class="form-group">')
							.append($('<label class="col-sm-2 control-label">')
								.append('{{Quand faut il que jeedom appel le numero}}'))
							.append($('<div class="col-sm-12">')
								.append('Ajouter ici une liste d\'objet déclancheur')))
						.append($('<div class="form-group">')
							.append($('<label class="col-sm-2 control-label">')
								.append('{{Numéro de téléphone}}'))
							.append($('<div class="col-sm-12">')
								.append($('<input type="number" class="OutCallEventAttr form-control roundedRight" data-l1key="Numero"  placeholder="{{Saisir le numero du correspondant ou laisser vide pour un message a tous les contactes}}"/>'))))
						.append($('<div class="form-group">')
							.append($('<label class="col-sm-2 control-label">')
								.append('{{Message a délivrer}}'))
							.append($('<div class="col-sm-12">')
								.append($('<textarea class="OutCallEventAttr form-control roundedRight" data-l1key="Message"  placeholder="{{Saisir le message a transmettre}}">'))))
						.append($('<hr/>'))))));
	$('#div_OutCallEvents').append(div);
	$('#div_OutCallEvents .OutCallEventConf:last').setValues(_action, '.OutCallEventAttr');
	$('.collapse').collapse();

	$('.OutCallEventAct[data-action=remove]').off().on( 'click',function () {
		$(this).closest('.OutCallEventConf').remove();
	});
 }
