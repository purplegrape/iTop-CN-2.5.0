<?php
// Copyright (C) 2014 Combodo SARL
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

/**
 * Backup from an interactive session
 *
 * @copyright   Copyright (C) 2014 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

if (!defined('__DIR__')) define('__DIR__', dirname(__FILE__));
require_once(__DIR__.'/../../approot.inc.php');
require_once(APPROOT.'/application/application.inc.php');
require_once(APPROOT.'/application/webpage.class.inc.php');
require_once(APPROOT.'/application/ajaxwebpage.class.inc.php');

try
{
	$sOperation = utils::ReadParam('operation', '');

	switch($sOperation)
	{
		case 'send_reminder':
		require_once(APPROOT.'/application/startup.inc.php');
		require_once(APPROOT.'/application/loginwebpage.class.inc.php');
		LoginWebPage::DoLogin(); // Check user rights and prompt if needed

		$oPage = new ajax_page("");
		$oPage->no_cache();
		$oPage->SetContentType('text/html');

		try
		{
			$iSchemeId = utils::ReadParam('approval_id', 0);
			$iStep = utils::ReadParam('step', 0);

			$oScheme = MetaModel::GetObject('ApprovalScheme', $iSchemeId, false);
			if (!$oScheme)
			{
				throw new Exception(Dict::S('Approval:Form:ObjectDeleted'));
			}
			if ($oScheme->Get('status') == 'accepted')
			{
				throw new Exception(Dict::S('Approval:Form:AlreadyApproved'));
			}
			if ($oScheme->Get('status') == 'rejected')
			{
				throw new Exception(Dict::S('Approval:Form:AlreadyRejected'));
			}
			$aSteps = $oScheme->GetSteps();
			if ($iStep < $oScheme->Get('current_step'))
			{
				if ($aSteps[$iStep]['approved'])
				{
					throw new Exception(Dict::S('Approval:Form:StepApproved'));
				}
				throw new Exception(Dict::S('Approval:Form:StepRejected'));
			}

			$aAwaited = $oScheme->GetAwaitedReplies();
			$Sent = 0;
			if (count($aAwaited) > 0)
			{
				$oObject = MetaModel::GetObject($oScheme->Get('obj_class'), $oScheme->Get('obj_key'));
				$aReminders = array();
				foreach ($aAwaited as $aData)
				{
					$oTarget = MetaModel::GetObject($aData['class'], $aData['id'], false);
					if ($oTarget)
					{
						if (array_key_exists('substitute_to', $aData))
						{
							$oSubstituteTo = MetaModel::GetObject($aData['substitute_to']['class'], $aData['substitute_to']['id'], false);
						}
						else
						{
							$oSubstituteTo = null;
						}
						$oScheme->SendApprovalRequest($oTarget, $oObject, $aData['passcode'], $oSubstituteTo, true);
						$Sent++;
					}
				}
			}
			$oPage->add(Dict::Format('Approval:ReminderDone', $Sent));
			if ($Sent > 0)
			{
				// Reload the object details so as to refresh the notifications tab
				$oPage->add_script("window.location.reload();");
			}
		}
		catch (Exception $e)
		{
			$oPage->p('Error: '.$e->getMessage());
		}
		$oPage->output();
		break;
	}
}
catch (Exception $e)
{
	IssueLog::Error($e->getMessage());
}
