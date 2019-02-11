// (c) Combodo SARL 2012-2015

var aEmailReplyFiles = {};


function EmailReplyAddFile(sCaseLogAttCode, sContainerClass, sContainerId, sBlobAttCode, sFileName, bChecked)
{
	var sFileDef = sContainerClass+'::'+sContainerId+'/'+sBlobAttCode;
	if (bChecked)
	{
		var sId = 'emry_file_'+sCaseLogAttCode+'_'+sContainerClass+'_'+sContainerId+'_'+sBlobAttCode;
		var sHiddenInput = '<input type="hidden" name="emry_files_'+sCaseLogAttCode+'[]" id="'+sId+'" value="'+sFileDef+'"/>';
		$('#emry_form_extension').append(sHiddenInput);
	}

	if (!(sCaseLogAttCode in aEmailReplyFiles))
	{
		aEmailReplyFiles[sCaseLogAttCode] = {};
		aEmailReplyFiles[sCaseLogAttCode+'_file_count'] = 0;
	}
	aEmailReplyFiles[sCaseLogAttCode][sFileDef] = { sCaseLogAttCode: sCaseLogAttCode, sContainerClass: sContainerClass, sContainerId: sContainerId, sBlobAttCode: sBlobAttCode, sFileName: sFileName, checked: bChecked };
	if (bChecked)
	{
		aEmailReplyFiles[sCaseLogAttCode+'_file_count']++;		
	}
	EmailReplyUpdateFileCount(sCaseLogAttCode);
}


function EmailReplyRemoveFile(sCaseLogAttCode, sContainerClass, sContainerId, sBlobAttCode)
{
	var sFileDef = sContainerClass+'::'+sContainerId+'/'+sBlobAttCode;
	var sId = 'emry_file_'+sCaseLogAttCode+'_'+sContainerClass+'_'+sContainerId+'_'+sBlobAttCode;
	$('#'+sId).remove();

	if (aEmailReplyFiles[sCaseLogAttCode][sFileDef].checked)
	{
		aEmailReplyFiles[sCaseLogAttCode+'_file_count']--;	
	}
	delete aEmailReplyFiles[sCaseLogAttCode][sFileDef];
	EmailReplyUpdateFileCount(sCaseLogAttCode);
}


function EmailReplyUpdateFileCount(sCaseLogAttCode)
{
	var iCount  = aEmailReplyFiles[sCaseLogAttCode+'_file_count'];
	var bEmailDisabled = ($('#emry_enabled_'+sCaseLogAttCode+':checked').length == 0);

	if (bEmailDisabled)
	{
		$('#emry_file_count_'+sCaseLogAttCode).html('0');
		$('#emry_file_list_'+sCaseLogAttCode).attr('title', Dict.S('UI-emry-noattachment'));
	}
	else
	{
		$('#emry_file_count_'+sCaseLogAttCode).html(iCount);
		var sText = '';
		var idx = 0;
		var iNbAttachments = 0;
		for(var index in aEmailReplyFiles[sCaseLogAttCode])
		{
			iNbAttachments++;
			if (aEmailReplyFiles[sCaseLogAttCode][index].checked)
			{
				sText += aEmailReplyFiles[sCaseLogAttCode][index].sFileName;
				idx++;
				if (idx < iCount)
				{
					sText += ', ';
				}			
			}

		}
		if (sText == '')
		{
			sText = Dict.S('UI-emry-noattachment');
		}
		$('#emry_file_list_'+sCaseLogAttCode).attr('title', sText);		
	}
	
	if ((iNbAttachments == 0) || bEmailDisabled)
	{
		$('#emry_select_files_btn_'+sCaseLogAttCode).attr('disabled', 'disabled');
	}
	else
	{
		$('#emry_select_files_btn_'+sCaseLogAttCode).removeAttr('disabled');
	}
}

function EmailReplyTooltipContent(sCaseLogAttCode)
{
	var iCount  = aEmailReplyFiles[sCaseLogAttCode+'_file_count'];
	var bEmailDisabled = ($('#emry_enabled_'+sCaseLogAttCode+':checked').length == 0);

	if ((bEmailDisabled) || (iCount == 0))
	{
		sText = '<div style="padding:0;margin:0;font-size:12px;">'+Dict.S('UI-emry-noattachment')+'</div>';
	}
	else
	{
		var sText = '<div style="padding:0;margin:0;font-size:12px;">'+Dict.S('UI-emry-attachments-to-be-sent')+'</div>';
		var idx = 0;
		var iNbAttachments = 0;
		for(var index in aEmailReplyFiles[sCaseLogAttCode])
		{
			if (aEmailReplyFiles[sCaseLogAttCode][index].checked)
			{
				sText += '<div style="padding:0;margin:0;font-size:12px;">&bull;&nbsp;'+aEmailReplyFiles[sCaseLogAttCode][index].sFileName+'</div>';
				idx++;		
			}

		}
		sText += '</ul>';
	}
	return sText;
}


function EmailReplySelectAttachments(sCaseLogAttCode)
{
	var oDlg = $('<div id="emry-dlg"><div id="emry-dlg-content" style="max-height:25em;"><img src="../images/indicator.gif"</div></div>');
	$('body').append(oDlg);
	oDlg.dialog({
		modal:true,
		width: 500,
		height: 'auto',
		maxHeight: $(window).height()*0.9,
		title: Dict.S('UI-emry-select-attachments'),
		buttons:[{text: Dict.S('UI:Button:Ok'), click: function() { EmailReplyOnSelectAttachments(sCaseLogAttCode); }}, {text:  Dict.S('UI:Button:Cancel'), click: function() { $(this).dialog('close'); }}],
		close: function() { $(this).remove(); $('#emry_file_list_'+sCaseLogAttCode).trigger('blur'); $('#emry_select_files_btn_'+sCaseLogAttCode).trigger('blur'); }
	});
	sUrl = GetAbsoluteUrlModulePage('email-reply', 'ajax.php');
	$.post(sUrl, {operation: 'list_attachments', attachments: aEmailReplyFiles[sCaseLogAttCode] }, function(data) {
		$('#emry-dlg-content').html(data);
		oDlg.dialog('option', 'position', 'center');
	});
}

function EmailReplyOnSelectAttachments(sCaseLogAttCode)
{
	$('input[name^="emry_files_'+sCaseLogAttCode+'"]').remove();
	
	$iCount = 0;
	$('#emry-dlg-content :checkbox').each(function() {
		var sFileref = $(this).attr('data-fileref');
		var bChecked = $(this).prop('checked');
		if (bChecked)
		{
			var sContainerClass = aEmailReplyFiles[sCaseLogAttCode][sFileref].sContainerClass;
			var sContainerId = aEmailReplyFiles[sCaseLogAttCode][sFileref].sContainerId;
			var sBlobAttCode = aEmailReplyFiles[sCaseLogAttCode][sFileref].sBlobAttCode;
			aEmailReplyFiles[sCaseLogAttCode][sFileref].checked = true;
			
			var sId = 'emry_file_'+sCaseLogAttCode+'_'+sContainerClass+'_'+sContainerId+'_'+sBlobAttCode;
			var sHiddenInput = '<input type="hidden" name="emry_files_'+sCaseLogAttCode+'[]" id="'+sId+'" value="'+sFileref+'"/>';
			$('#emry_form_extension').append(sHiddenInput);
			$iCount++;
		}
		else
		{
			aEmailReplyFiles[sCaseLogAttCode][sFileref].checked = false;
		}
	});
	aEmailReplyFiles[sCaseLogAttCode+'_file_count'] = $iCount;
	EmailReplyUpdateFileCount(sCaseLogAttCode);
	$('#emry-dlg').dialog('close');
}

