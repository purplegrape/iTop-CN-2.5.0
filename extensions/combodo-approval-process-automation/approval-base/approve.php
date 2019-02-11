<?php
// Copyright (C) 2012-2016 Combodo SARL
//
//   This program is free software; you can redistribute it and/or modify
//   it under the terms of the GNU General Public License as published by
//   the Free Software Foundation; version 3 of the License.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of the GNU General Public License
//   along with this program; if not, write to the Free Software
//   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

/**
 * Approval page
 *
 * @author      Erwan Taloc <erwan.taloc@combodo.com>
 * @author      Romain Quetiez <romain.quetiez@combodo.com>
 * @author      Denis Flaven <denis.flaven@combodo.com>
 * @license     http://www.opensource.org/licenses/gpl-3.0.html LGPL
 */
require_once('../../approot.inc.php');
require_once(APPROOT.'/application/application.inc.php');
require_once(APPROOT.'/application/nicewebpage.class.inc.php');
require_once(APPROOT.'/application/wizardhelper.class.inc.php');


class QuitException extends Exception
{}


function ReadMandatoryParam($sParam)
{
	$value = utils::ReadParam($sParam, null);
	if (is_null($value))
	{
		throw new Exception("Missing argument '$sParam'");
	}
	return $value; 
}


/**
 * Set the stage and that the approval is ongoing
 */
function CheckApprovalSchemeAndShowTitle($oP, $iSchemeId)
{
	$oScheme = MetaModel::GetObject('ApprovalScheme', $iSchemeId, false);
	if (!$oScheme)
	{
		$oP->p(Dict::S('Approval:Form:ObjectDeleted'));
		throw new QuitException();
	}

	$oObject = MetaModel::GetObject($oScheme->Get('obj_class'), $oScheme->Get('obj_key'));
	$oP->add('<h2>'.Dict::Format('Approval:Form:Ref', $oObject->GetHyperLink()).'</h2>');

	if ($oScheme->Get('status') == 'accepted')
	{
		$oP->p(Dict::S('Approval:Form:AlreadyApproved'));
		throw new QuitException();
	}
	elseif ($oScheme->Get('status') == 'rejected')
	{
		$oP->p(Dict::S('Approval:Form:AlreadyRejected'));
		throw new QuitException();
	}

	return array($oScheme, $oObject);
}

/**
 * Interpret the token to build the page arguments
 * Validate that the arguments are consistent altogether
 */
function GetContext($oP, $sToken)
{
	// For the moment, the token is made of <scheme_id>-<step>-<contact_class>-<contact_id>-<passcode>
	$aToken = explode('-', $sToken);
	if (count($aToken) != 5)
	{
		throw new CoreException("Unexpected value for token: '$sToken' does not have the required format");
	}

	$iSchemeId = $aToken[0];
	$iStep = $aToken[1];
	$sApproverClass = $aToken[2];
	$iApproverId = $aToken[3];
	$sPassCode = $aToken[4];

	$oApprover = MetaModel::GetObject($sApproverClass, $iApproverId, false);
	if (!$oApprover)
	{
		$oP->p(Dict::S('Approval:Form:ApproverDeleted'));
		throw new QuitException();
	}

	list($oScheme, $oObject) = CheckApprovalSchemeAndShowTitle($oP, $iSchemeId);
	$aSteps = $oScheme->GetSteps();

	if ($iStep < $oScheme->Get('current_step'))
	{
		if ($aSteps[$iStep]['approved'])
		{
			$oP->p(Dict::S('Approval:Form:StepApproved'));
			throw new QuitException();
		}
		else
		{
			$oP->p(Dict::S('Approval:Form:StepRejected'));
			throw new QuitException();
		}
	}

	if ($iStep > $oScheme->Get('current_step'))
	{
		throw new CoreException("Unexpected value for step: '$iStep' is not started or exceeds allowed values");
	}

	// Given the passcode, find the approver amongst the existing approvers for the given step...
	//
	$bFoundRecord = false;
	$oSubstitute = null;
	foreach($aSteps[$iStep]['approvers'] as $aApproverData)
	{
		if ($bFoundRecord)
		{
			break;
		}

		if ( ($aApproverData['class'] == get_class($oApprover))
				&& ($aApproverData['id'] == $oApprover->GetKey())
				&& ($aApproverData['passcode'] == $sPassCode) )
		{
			if (array_key_exists('answer_time', $aApproverData)
			   && array_key_exists('replier_index', $aApproverData))
			{
				// The replier is one of the substitutes (not the main approver)
				$iReplier = $aApproverData['replier_index'];
				$aReplierData = $aApproverData['forward'][$iReplier];
				$oReplier = MetaModel::GetObject($aReplierData['class'], $aReplierData['id'], false);
				$sReplierName = is_null($oReplier) ? $aReplierData['class'].'::'.$aReplierData['id'].' (deleted)'
																: $oReplier->GetName();
				$oP->p(Dict::Format('Approval:Form:AnswerGivenBy', $sReplierName));
				throw new QuitException();
			}

			$bFoundRecord = true;
		}
		elseif (array_key_exists('forward', $aApproverData))
		{
			foreach($aApproverData['forward'] as $iSubstitue => $aSubstituteData)
			{
				if ( ($aSubstituteData['class'] == get_class($oApprover))
						&& ($aSubstituteData['id'] == $oApprover->GetKey())
						&& ($aSubstituteData['passcode'] == $sPassCode) )
				{
					// Ultimate check: either nobody has answered (for this approver), or the existing answer is from the current replier
					if (array_key_exists('answer_time', $aApproverData))
					{
						if (array_key_exists('replier_index', $aApproverData))
						{
							$iReplier = $aApproverData['replier_index'];
							if ($iReplier != $iSubstitue)
							{
								// The replier is not the current subtitute
								$aReplierData = $aApproverData['forward'][$iReplier];
								$oReplier = MetaModel::GetObject($aReplierData['class'], $aReplierData['id'], false);
								$sReplierName = is_null($oReplier) ? $aReplierData['class'].'::'.$aReplierData['id'].' (deleted)'
																				: $oReplier->GetName();
								$oP->p(Dict::Format('Approval:Form:AnswerGivenBy', $sReplierName));
								throw new QuitException();
							}
						}
						else
						{
							// The replier is the main approver (not the current substitute)
							$oReplier = MetaModel::GetObject($aApproverData['class'], $aApproverData['id'], false);
							$sReplierName = is_null($oReplier) ? $aApproverData['class'].'::'.$aApproverData['id'].' (deleted)'
																			: $oReplier->GetName();
							$oP->p(Dict::Format('Approval:Form:AnswerGivenBy', $sReplierName));
							throw new QuitException();
						}
					}
					$oApprover = MetaModel::GetObject($aApproverData['class'], $aApproverData['id'], false);
					$oSubstitute = MetaModel::GetObject($aSubstituteData['class'], $aSubstituteData['id'], false);
					if (!$oApprover || !$oSubstitute)
					{
						$oP->p(Dict::S('Approval:Form:ApproverDeleted'));
						throw new QuitException();
					}
					$bFoundRecord = true;
					break;
				}
			}			
		}
	}

	if (!$bFoundRecord)
	{
		throw new CoreException("Unexpected approver for this step");
	}

	return array($oScheme, $iStep, $oApprover, $oObject, $oSubstitute);
}


function ShowApprovalForm($sFrom, $oP, $sToken)
{
	list($oScheme, $iStep, $oApprover, $oObject, $oSubstitute) = GetContext($oP, $sToken);

	$oScheme->DisplayApprovalForm($sFrom, $oP, $oApprover, $oObject, $sToken, $oSubstitute);
}


function AfterSubmit($oP, $oScheme, $bReturnToObjectDetails)
{
	if ($oScheme->Get('status') == 'accepted')
	{
		$sOutcome = Dict::S('Approval:Form:AnswerRecorded-Approved');
	}
	elseif ($oScheme->Get('status') == 'rejected')
	{
		$sOutcome = Dict::S('Approval:Form:AnswerRecorded-Rejected');
	}
	else
	{
		$sOutcome = Dict::S('Approval:Form:AnswerRecorded-Continue');
	}
	$oP->p($sOutcome);

	if ($bReturnToObjectDetails)
	{
		cmdbAbstractObject::SetSessionMessage($oScheme->Get('obj_class'), $oScheme->Get('obj_key'), 'approval-result', $sOutcome, 'info', 0, true /* must not exist */);
		$oAppContext = new ApplicationContext();
		$oP->add_header('Location: '.utils::GetAbsoluteUrlAppRoot().'pages/UI.php?operation=details&class='.$oScheme->Get('obj_class').'&id='.$oScheme->Get('obj_key').'&'.$oAppContext->GetForLink());
	}
}


function SubmitAnswer($sFrom, $oP, $sToken, $bApprove, $sComment)
{
	list($oScheme, $iStep, $oApprover, $oObject, $oSubstitute) = GetContext($oP, $sToken);

	// As the current user is not necessarily logged in, keep track of her name
	$sTrackInfo = $oScheme->GetIssuerInfo($bApprove, $oApprover, $oSubstitute);
	CMDBObject::SetTrackInfo($sTrackInfo);

	// Record the approval/rejection
	//
	$oScheme->OnAnswer($iStep, $oApprover, $bApprove, $oSubstitute, $sComment);

	$bReturnToObjectDetails = ($sFrom == 'object_details');
	AfterSubmit($oP, $oScheme, $bReturnToObjectDetails);
}


function ShowAbortForm($sFrom, $oP, $iApprovalId)
{
	list($oScheme, $oObject) = CheckApprovalSchemeAndShowTitle($oP, $iApprovalId);
	if (!$oScheme->IsAllowedToAbort())
	{
		throw new Exception("Only administrators are allowed to abort an approval process");
	}

	$oScheme->DisplayAbortForm($sFrom, $oP);
}


function SubmitAbort($sFrom, $oP, $iApprovalId, $bApprove, $sComment)
{
	list($oScheme, $oObject) = CheckApprovalSchemeAndShowTitle($oP, $iApprovalId);
	if (!$oScheme->IsAllowedToAbort())
	{
		throw new Exception("Only administrators are allowed to abort an approval process");
	}

	$oScheme->OnAbort($bApprove, $sComment);

	$bReturnToObjectDetails = ($sFrom == 'object_details');
	AfterSubmit($oP, $oScheme, $bReturnToObjectDetails);
}

/////////////////////////////
//
// Main
//


try
{
	require_once(APPROOT.'/application/startup.inc.php');
	$sOperation = utils::ReadParam('operation', '');
	$sFrom = utils::ReadParam('from', '');
	$bAbort = (utils::ReadParam('abort', '') == 1);

	if ($sFrom == 'object_details')
	{
		require_once(APPROOT.'/application/loginwebpage.class.inc.php');
		LoginWebPage::DoLoginEx(); // Check user rights and prompt if needed

		require_once(APPROOT.'application/itopwebpage.class.inc.php');
		$oP = new iTopWebPage(Dict::S('Approval:Form:Title'));
		$sModule = utils::GetAbsoluteUrlModulesRoot().'approval-base';
		$oP->add_style(
<<<EOF
#approval-button {
    background: url("$sModule/approve.png") no-repeat scroll 10px center rgba(0, 0, 0, 0);
}
#rejection-button {
    background: url("$sModule/reject.png") no-repeat scroll 10px center rgba(0, 0, 0, 0);
}
#approval-button, #rejection-button {
    margin: 0 10px 10px;
    padding: 5px 10px 5px 35px;
}
EOF
		);
	}
	else
	{
		require_once(MODULESROOT.'approval-base/approvalwebpage.class.inc.php');
		$oP = new ApprovalWebPage(Dict::S('Approval:Form:Title'));
	}
	$oP->set_base(utils::GetAbsoluteUrlAppRoot().'pages/');

	switch ($sOperation)
	{
		case 'do_approve':
		case 'do_reject':
		$bApprove = ($sOperation == 'do_approve');
		$sComment = trim(utils::ReadParam('comment', '', false, 'raw_data'));
		if (!$bApprove && ($sComment == ''))
		{
			throw new Exception('Empty comment not authorized!'); // bug: should be protected in the form
		}
		if ($bAbort)
		{
			$iApprovalId = ReadMandatoryParam('approval_id');
			SubmitAbort($sFrom, $oP, $iApprovalId, $bApprove, $sComment);
		}
		else
		{
			$sToken = ReadMandatoryParam('token');
			SubmitAnswer($sFrom, $oP, $sToken, $bApprove, $sComment);
		}
		break;

		case 'reject': // legacy: emails were created with such an option
		case 'approve': // legacy: emails were created with such an option
		default:
		if ($bAbort)
		{
			$iApprovalId = ReadMandatoryParam('approval_id');
			ShowAbortForm($sFrom, $oP, $iApprovalId);
		}
		else
		{
			$sToken = ReadMandatoryParam('token');
			ShowApprovalForm($sFrom, $oP, $sToken);
		}

	}

	$oP->output();
}
catch(QuitException $e)
{
	$oP->output();
}
catch(CoreException $e)
{
	require_once(APPROOT.'/setup/setuppage.class.inc.php');
	$oP = new SetupPage(Dict::S('UI:PageTitle:FatalError'));
	$oP->set_base(utils::GetAbsoluteUrlAppRoot().'pages/');
	$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>\n");	
	$oP->error(Dict::Format('UI:Error_Details', $e->getHtmlDesc()));	
	$oP->output();

	if (MetaModel::IsLogEnabledIssue())
	{
		if (MetaModel::IsValidClass('EventIssue'))
		{
			try
			{
				$oLog = new EventIssue();
	
				$oLog->Set('message', $e->getMessage());
				$oLog->Set('userinfo', '');
				$oLog->Set('issue', $e->GetIssue());
				$oLog->Set('impact', 'Page could not be displayed');
				$oLog->Set('callstack', $e->getTrace());
				$oLog->Set('data', $e->getContextData());
				$oLog->DBInsertNoReload();
			}
			catch(Exception $e)
			{
				IssueLog::Error("Failed to log issue into the DB");
			}
		}

		IssueLog::Error($e->getMessage());
	}

	// For debugging only
	//throw $e;
}
catch(Exception $e)
{
	require_once(APPROOT.'/setup/setuppage.class.inc.php');
	$oP = new SetupPage(Dict::S('UI:PageTitle:FatalError'));
	$oP->set_base(utils::GetAbsoluteUrlAppRoot().'pages/');
	$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>\n");	
	$oP->error(Dict::Format('UI:Error_Details', $e->getMessage()));	
	$oP->output();

	if (MetaModel::IsLogEnabledIssue())
	{
		if (MetaModel::IsValidClass('EventIssue'))
		{
			try
			{
				$oLog = new EventIssue();
	
				$oLog->Set('message', $e->getMessage());
				$oLog->Set('userinfo', '');
				$oLog->Set('issue', 'PHP Exception');
				$oLog->Set('impact', 'Page could not be displayed');
				$oLog->Set('callstack', $e->getTrace());
				$oLog->Set('data', array());
				$oLog->DBInsertNoReload();
			}
			catch(Exception $e)
			{
				IssueLog::Error("Failed to log issue into the DB");
			}
		}

		IssueLog::Error($e->getMessage());
	}
}
?>
