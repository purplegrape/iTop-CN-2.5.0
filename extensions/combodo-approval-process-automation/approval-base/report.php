<?php
// Copyright (C) 2012 Combodo SARL
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
 * Execute the combodo-approbation actions
 *
 * @author      Erwan Taloc <erwan.taloc@combodo.com>
 * @author      Romain Quetiez <romain.quetiez@combodo.com>
 * @author      Denis Flaven <denis.flaven@combodo.com>
 * @license     http://www.opensource.org/licenses/gpl-3.0.html LGPL
 */

if (!defined('__DIR__')) define('__DIR__', dirname(__FILE__));
require_once(__DIR__.'/../../approot.inc.php');
require_once(APPROOT.'/application/application.inc.php');
require_once(APPROOT.'/application/itopwebpage.class.inc.php');



function DoShowAllStatuses($oP, $sClass)
{
	if (strlen($sClass) == 0)
	{
		throw new Exception("Missing mandatory param 'class'");
	}
	$oP->add('<h1>Helper tool for training and documentation</h1>');
	$oP->p('Showing statuses for all of the approvals (ongoing or not!)');

	$aClasses = MetaModel::EnumChildClasses($sClass, ENUM_CHILD_CLASSES_ALL); // Including the specified class itself
	$sClassList = implode(", ", CMDBSource::Quote($aClasses));
	$oSearch = DBObjectSearch::FromOQL("SELECT ApprovalScheme WHERE obj_class IN ($sClassList)");
	$oSet = new DBObjectSet($oSearch);
	while ($oScheme = $oSet->Fetch())
	{
		$oObject = MetaModel::GetObject($oScheme->Get('obj_class'), $oScheme->Get('obj_key'));
		$oP->p('Approval for '.$oScheme->GetHyperlink());
		$oP->add($oScheme->GetDisplayStatus($oP));
	}
}


function DoShowOngoing($oP, $sClass)
{
	if (strlen($sClass) == 0)
	{
		throw new Exception("Missing mandatory param 'class'");
	}
	$iCurrentUserContact = UserRights::GetContactId();

	$oP->add('<h1>'.Dict::Format('Approval:Ongoing-Title+', MetaModel::GetName($sClass)).'</h1>');

	if ($iCurrentUserContact > 0)
	{
		$bFilter = (utils::ReadParam('do_filter_my_approvals', '') == 'on');
		$sDisabled = '';
	}
	else
	{
		// No contact is associated to the current user: disable the filtering capability
		$bFilter = false;
		$sDisabled = 'DISABLED';
	}
	$oAppContext = new ApplicationContext();
	$sReport = utils::GetAbsoluteUrlModulePage('approval-base', 'report.php');
	$oP->add('<form id="filter_approvals" action="'.$sReport.'" method="post">');
	$oP->add($oAppContext->GetForForm());
	$oP->add('<input type="hidden" name="class" value="'.$sClass.'">');
	$sChecked = $bFilter ? 'CHECKED' : '';
	$oP->add('<input id="do_filter_my_approvals" name="do_filter_my_approvals" type="checkbox" '.$sChecked.' '.$sDisabled.'>');
	$oP->add('<label for="do_filter_my_approvals">'.Dict::S('Approval:Ongoing-FilterMyApprovals').'</label>');
	$oP->add('</form>');
	$oP->add_ready_script(
<<<EOF
$('#do_filter_my_approvals').bind('click', function() {
	$('form#filter_approvals').submit();
});
EOF
	);

	$aClasses = MetaModel::EnumChildClasses($sClass, ENUM_CHILD_CLASSES_ALL); // Including the specified class itself
	$sClassList = implode(", ", CMDBSource::Quote($aClasses));
	$oSearch = DBObjectSearch::FromOQL("SELECT ApprovalScheme WHERE status = 'ongoing' AND obj_class IN ($sClassList)");
	$oSet = new DBObjectSet($oSearch, array('started' => true));
	$aIds = array();
	while ($oApproval = $oSet->Fetch())
	{
		if (!$bFilter || $oApproval->IsActiveApprover('Person', $iCurrentUserContact))
		{
			$aIds[] = $oApproval->Get('obj_key');
		}
	}

	if (count($aIds) == 0)
	{
		$oP->p(Dict::S('Approval:Ongoing-NothingCurrently'));
	}
	else
	{
		$sIds = implode(", ", CMDBSource::Quote($aIds));
		$oObjSearch = DBObjectSearch::FromOQL("SELECT $sClass WHERE id IN ($sIds)");
	
		$oBlock = new DisplayBlock($oObjSearch, 'list', false);
		$oBlock->Display($oP, 'ongoing_appr', array('menu' => false));
	}
}

try
{
	require_once(APPROOT.'/application/startup.inc.php');
	require_once(MODULESROOT.'approval-base/approvalwebpage.class.inc.php');

	$oAppContext = new ApplicationContext();
	
	require_once(APPROOT.'/application/loginwebpage.class.inc.php');
	LoginWebPage::DoLogin(); // Check user rights and prompt if needed
	
	$oP = new iTopWebPage(Dict::S('Approval:Ongoing-Title'));

	$sOperation = utils::ReadParam('operation', '');
	$sClass = utils::ReadParam('class', null);

	switch($sOperation)
	{
		case 'show_all_statuses':
		// This is an helper for troubleshooting the display of statuses and/or document the module
		DoShowAllStatuses($oP, $sClass);
		break;

		case 'show_ongoing':
		default:
		DoShowOngoing($oP, $sClass);
		break;
	}

	$oP->output();
}
catch(CoreException $e)
{
	require_once(APPROOT.'/setup/setuppage.class.inc.php');
	$oP = new SetupPage(Dict::S('UI:PageTitle:FatalError'));
	$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>\n");	
	$oP->error(Dict::Format('UI:Error_Details', $e->getHtmlDesc()));	
	$oP->output();

	if (MetaModel::IsLogEnabledIssue())
	{
		if (MetaModel::IsValidClass('EventIssue'))
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

		IssueLog::Error($e->getMessage());
	}

	// For debugging only
	//throw $e;
}
catch(Exception $e)
{
	require_once(APPROOT.'/setup/setuppage.class.inc.php');
	$oP = new SetupPage(Dict::S('UI:PageTitle:FatalError'));
	$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>\n");	
	$oP->error(Dict::Format('UI:Error_Details', $e->getMessage()));	
	$oP->output();

	if (MetaModel::IsLogEnabledIssue())
	{
		if (MetaModel::IsValidClass('EventIssue'))
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

		IssueLog::Error($e->getMessage());
	}
}
?>