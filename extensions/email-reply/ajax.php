<?php
// Copyright (C) 2010-2015 Combodo SARL
//
//   This file is part of iTop.
//
//   iTop is free software; you can redistribute it and/or modify
//   it under the terms of the GNU Affero General Public License as published by
//   the Free Software Foundation, either version 3 of the License, or
//   (at your option) any later version.
//
//   iTop is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU Affero General Public License for more details.
//
//   You should have received a copy of the GNU Affero General Public License
//   along with iTop. If not, see <http://www.gnu.org/licenses/>

require_once(APPROOT.'/application/application.inc.php');
require_once(APPROOT.'/application/webpage.class.inc.php');
require_once(APPROOT.'/application/ajaxwebpage.class.inc.php');
require_once(APPROOT.'/application/wizardhelper.class.inc.php');
require_once(APPROOT.'/application/ui.linkswidget.class.inc.php');
require_once(APPROOT.'/application/ui.extkeywidget.class.inc.php');

try
{
	require_once(APPROOT.'/application/startup.inc.php');
	require_once(APPROOT.'/application/user.preferences.class.inc.php');
	
	require_once(APPROOT.'/application/loginwebpage.class.inc.php');
	LoginWebPage::DoLogin(false /* bMustBeAdmin */, false /* IsAllowedToPortalUsers */); // Check user rights and prompt if needed
	
	$oPage = new ajax_page("");
	$oPage->no_cache();

	$sOperation = utils::ReadParam('operation');

	switch($sOperation)
	{
		default:
		$aAttachments = utils::ReadParam('attachments', array(), false, 'raw_data');
		$aObjByClassAndId = array();
		foreach($aAttachments as $aData)
		{
			$sObjClass = $aData['sContainerClass'];
			if (!array_key_exists($sObjClass, $aObjByClassAndId))
			{
				$aObjByClassAndId[$sObjClass] = array();
			}
			$aObjByClassAndId[$sObjClass][$aData['sContainerId']] = null;
		}
		
		foreach($aObjByClassAndId as $sClass => $aObjById)
		{
			$oSearch = DBObjectSearch::FromOQL("SELECT $sClass WHERE id IN (".implode(',', array_keys($aObjById)).")");
			$oSet = new DBObjectSet($oSearch);
			while($oObj = $oSet->Fetch())
			{
				$aObjByClassAndId[$sClass][$oObj->GetKey()] = $oObj;
			}
		}
		
		foreach($aAttachments as $aData)
		{
			$sObjClass = $aData['sContainerClass'];
			$iObjId = $aData['sContainerId'];
			$oObj = $aObjByClassAndId[$sClass][$iObjId];
			if ($oObj !== null)
			{
				$oDoc = $oObj->Get($aData['sBlobAttCode']);
				$sFileName = $oDoc->GetFileName();
				$sIcon = utils::GetAbsoluteUrlAppRoot().AttachmentPlugIn::GetFileIcon($sFileName);
				$sPreview = $oDoc->IsPreviewAvailable() ? 'true' : 'false';
				$sChecked = ($aData['checked'] == 'true') ? ' checked' : '';
				$sFileDef = $sObjClass.'::'.$iObjId.'/'.$aData['sBlobAttCode'];
				$sDownloadLink = htmlentities(utils::GetAbsoluteUrlAppRoot().'pages/ajax.render.php?operation=download_document&class='.$sObjClass.'&field='.$aData['sBlobAttCode'].'&id='.$iObjId, ENT_QUOTES, 'UTF-8');
				$oPage->add('<div style="vertical-align:middle;"><input type="checkbox" data-fileref="'.$sFileDef.'" id="emry-pick-'.$sObjClass.'-'.$iObjId.'"'.$sChecked.'><label class="emry-attachment" data-href="'.$sDownloadLink.'" data-preview="'.$sPreview.'" for="emry-pick-'.$sObjClass.'-'.$iObjId.'">&nbsp;<img style="vertical-align:middle;" src="'.$sIcon.'" />&nbsp;'.htmlentities($sFileName, ENT_QUOTES, 'UTF-8').'</label></div>');
				$sPreviewNotAvailable = addslashes(Dict::S('Attachments:PreviewNotAvailable'));
				$iMaxWidth = MetaModel::GetModuleSetting('itop-attachments', 'preview_max_width', 290);
				$oPage->add_ready_script(
					<<<EOF
$(document).tooltip({ items: '.emry-attachment',  position: { my: 'left top', at: 'right top', using: function( position, feedback ) { $( this ).css( position ); }}, content: function() { if ($(this).attr('data-preview') == 'true') { return('<img style=\"max-width:{$iMaxWidth}px\" src=\"'+$(this).attr('data-href')+'\"></img>');} else { return '$sPreviewNotAvailable'; }}});
EOF
				);
			}				
		}		
	}
	
	$oPage->output();
}
catch (Exception $e)
{
	echo $e->GetMessage();
	IssueLog::Error($e->getMessage());
}
