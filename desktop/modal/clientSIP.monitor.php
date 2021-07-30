<?php
if (!isConnect('admin')) {
    throw new Exception('401 Unauthorized');
}
?>
<legend><a class="btn btn-danger btn-xs MonitorAction" data-action="remove"><i class="fa fa-minus-circle"></i> {{Nettoyer}}</a></legend>
<div style="height: 500px;overflow: auto;">
	<table id="table_Monitor" class="table table-bordered table-condensed tablesorter">
		<thead>
			<tr>
				<th>{{Date}}</th>
				<th>{{Type}}</th>
				<th>{{Message}}</th>
			</tr>
		</thead>
		<tbody></tbody>
	</table>
	<script>
	initTableSorter();
	$('.MonitorAction[data-action=remove]').off().on('click', function () {
		$('#table_Monitor tbody tr').remove();
	});	
	$('body').off('clientSIP::monitor').on('clientSIP::monitor', function (_event,_options) {
		var monitors=jQuery.parseJSON(_options);
		var message = monitors.Message.split("\r\n");
		var html = $('<div>');
		$.each(message,function(key,line){
			html.append(line).append('<br>');
		});
		$('#table_Monitor tbody').prepend($("<tr>")
			.append($("<td>").text(monitors.Time))
			.append($("<td>").text(monitors.Mode))
			.append($("<td>").append(html)));		
		if($('#table_Monitor tbody tr').length >= 255)
			$('#table_Monitor tbody tr:last').remove();
		$('#table_Monitor').trigger('update');
	});	   
	</script>
</div>
