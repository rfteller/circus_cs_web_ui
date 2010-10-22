<?xml version="1.0" encoding="shift_jis"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"><!-- InstanceBegin template="/Templates/base.dwt" codeOutsideHTMLIsLocked="false" -->
<head>
<meta http-equiv="Content-Type" content="text/html; charset=shift_jis" />
<meta http-equiv="content-style-type" content="text/css" />
<meta http-equiv="content-script-type" content="text/javascript" />
<meta http-equiv="X-UA-Compatible" content="IE=EmulateIE7" />
<!-- InstanceBeginEditable name="doctitle" -->
<title>CIRCUS CS {$smarty.session.circusVersion}</title>
<!-- InstanceEndEditable -->

<link href="../css/import.css" rel="stylesheet" type="text/css" media="all" />
<script language="javascript" type="text/javascript" src="../jq/jquery-1.3.2.min.js"></script>
<script language="javascript" type="text/javascript" src="../jq/jq-btn.js"></script>
<script language="javascript" type="text/javascript" src="../js/hover.js"></script>
<script language="javascript" type="text/javascript" src="../js/viewControl.js"></script>
<link rel="shortcut icon" href="../favicon.ico" />

<script language="Javascript">;
<!--
{literal}

function UpdateConfig(ticket)
{
		
	if(confirm('Do you want to update configuration file?'))	
	{
		var address = 'dicom_storage_server_config.php?mode=update'
				    + '&oldAeTitle='      + encodeURIComponent($("#oldAETitle").val())
				    + '&oldPortNumber='   + encodeURIComponent($("#oldPortNumber").val())
				    + '&oldLogFname='     + encodeURIComponent($("#oldLogFname").val())
				    + '&oldErrLogFname='  + encodeURIComponent($("#oldErrLogFname").val())
				    + '&oldThumbnailFlg=' + $("#oldThumbnailFlg").val()
				    + '&oldCompressFlg='  + $("#oldCompressFlg").val()
				    + '&newAeTitle='      + encodeURIComponent($("#newAETitle").val())
				    + '&newPortNumber='   + encodeURIComponent($("#newPortNumber").val())
				    + '&newLogFname='     + encodeURIComponent($("#newLogFname").val())
				    + '&newErrLogFname='  + encodeURIComponent($("#newErrLogFname").val())
				    + '&newThumbnailFlg=' + $('input[name="newThumbnailFlg"]:checked').val()
				    + '&newCompressFlg='  + $('input[name="newCompressFlg"]:checked').val()
					+ '&ticket=' + ticket;
		location.replace(address);	
	}
}

function CancelConfig(ticket)
{
	$("#newAeTitle.value").val($("#oldAeTitle").val());
	$("#newPortNumber").val($("#oldPortNumber").val());
	$("#newLogFname").val($("#oldLogFname").val());
	$("#newErrLogFname").val($("#oldErrLogFname").val());
	$("input[name='newThumbnailFlg']").filter(function(){ return ($(this).val() == $("#oldThumbnailFlg").val()) }).attr("checked", true);
	$("input[name='newCompressFlg']").filter(function(){ return ($(this).val() == $("#oldCompressFlg").val()) }).attr("checked", true);
}

function RestartDICOMStorageSv(ticket)
{
	if(confirm('Do you restart DICOM storage server?'))
	{
		var address = 'dicom_storage_server_config.php?mode=restartSv&ticket=' + ticket;
		location.replace(address);
	}
}

{/literal}
-->
</script>


<!-- InstanceBeginEditable name="head" -->
<link href="../css/mode.{$smarty.session.colorSet}.css" rel="stylesheet" type="text/css" media="all" />
<link href="../css/popup.css" rel="stylesheet" type="text/css" media="all" />
<script language="javascript" type="text/javascript" src="../js/hover.js"></script>

<!-- InstanceEndEditable -->
</head>

<!-- InstanceParam name="class" type="text" value="home" -->
<body class="spot">
<div id="page">
	<div id="container" class="menu-back">
		<div id="leftside">
			{include file='menu.tpl'}
		</div><!-- / #leftside END -->
		<div id="content">
<!-- InstanceBeginEditable name="content" -->
			<h2>Configuration of DICOM storage server</h2>

			<form id="form1" name="form1">
				<input type="hidden" id="oldAETitle"      value="{$configData.aeTitle}">
				<input type="hidden" id="oldPortNumber"   value="{$configData.portNumber}">
				<input type="hidden" id="oldLogFname"     value="{$configData.logFname}">
				<input type="hidden" id="oldErrLogFname"  value="{$configData.errLogFname}">
				<input type="hidden" id="oldThumbnailFlg" value="{$configData.thumbnailFlg}">
				<input type="hidden" id="oldCompressFlg"  value="{$configData.compressFlg}">

				<div id="message" class="mt5 ml20">{$message}</div>

				<div class="mt20 ml20">
					<table class="detail-tbl">
						<tr>
							<th style="width: 15em;"><span class="trim01">AE title</th>
							<td><input id="newAETitle" size="20" type="text" value="{$configData.aeTitle}" /></td>
						</tr>

						<tr>
							<th><span class="trim01">Port number</th>
							<td><input id="newPortNumber" size="20" type="text" value="{$configData.portNumber}" /></td>
						</tr>

						<tr>
							<th><span class="trim01">Log file</th>
							<td><input id="newLogFname" size="60" type="text" value="{$configData.logFname}" disabled="disabled" /></td>
						</tr>


						<tr>
							<th><span class="trim01">Error log file</th>
							<td><input id="newErrLogFname" size="60" type="text" value="{$configData.errLogFname}" disabled="disabled" /></td>
						</tr>

						<tr>
							<th><span class="trim01">Create thumbnail images</th>
							<td>
								<input name="newThumbnailFlg" type="radio" value="1"{if $configData.thumbnailFlg ==1} checked="checked"{/if} />TRUE
								<input name="newThumbnailFlg" type="radio" value="0"{if $configData.thumbnailFlg ==0} checked="checked"{/if} />FALSE
							</td>
						</tr>

						<tr>
							<th><span class="trim01">Compress DICOM image with lossless JPEG</th>
							<td>
								<input name="newCompressFlg" type="radio" value="1"{if $configData.compressFlg ==1} checked="checked"{/if} />TRUE
								<input name="newCompressFlg" type="radio" value="0"{if $configData.compressFlg ==0} checked="checked"{/if} />FALSE
							</td>
						</tr>

					</table>

					<div class="pl20 mb20 mt10">
						<p>
							<input type="button" value="Update" onClick="UpdateConfig('{$ticket}');"
								class="form-btn{if $restartFlg==1} form-btn-disabled" disabled="disabled{/if}" />&nbsp;
							<input type="button" id="addBtn" class="form-btn" value="Cancel" onClick="CancelConfig();"
								class="form-btn{if $restartFlg==1} form-btn-disabled" disabled="disabled{/if}" />&nbsp;
							{if $restartFlg==1}
								<input type="button" id="cancelBtn" class="form-btn form-btn-disabled" value="Restart"
                                       onClick="RestartDICOMStorageSv('{$ticket}');" />
							{/if}
						</p>
					</div>
				</div>
			</form>
<!-- InstanceEndEditable -->
		</div><!-- / #content END -->
	</div><!-- / #container END -->
</div><!-- / #page END -->
</body>
<!-- InstanceEnd --></html>
