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
 * Module approval-base
 *
 * @author      Erwan Taloc <erwan.taloc@combodo.com>
 * @author      Romain Quetiez <romain.quetiez@combodo.com>
 * @author      Denis Flaven <denis.flaven@combodo.com>
 * @license     http://www.opensource.org/licenses/gpl-3.0.html LGPL
 */

if (is_dir(MODULESROOT.'itop-portal-base'))
{
	// If the portal is installed, then it may use the approval brick
	require_once(MODULESROOT.'approval-base/approvalbrick.class.inc.php');
	require_once(MODULESROOT.'approval-base/approvalbrickcontroller.class.inc.php');
	require_once(MODULESROOT.'approval-base/approvalbrickrouter.class.inc.php');
}

/**
 * An approval process associated to an object
 * Derive this class to implement an approval process
 * - A few abstract functions have to be defined to implement parallel and/or serialize approvals
 * - Advanced behavior can be implemented by overloading some of the methods (e.g. GetDisplayStatus to change the way it is displayed) 
 *    
 **/ 
abstract class _ApprovalScheme_ extends DBObject
{
	/**
	 * Can be overriden for simulation purposes (troubleshooting, tutorial)
	 */
	public function Now()
	{
		return time();
	}

	/**
	 * Helper to decode the approval sequences (steps)
	 */
	public function GetSteps()
	{
		$sStepsRaw = $this->Get('steps');
		if (empty($sStepsRaw))
		{
			$aSteps = array();
		}
		else
		{
			$aSteps = unserialize($sStepsRaw);
		}
		return $aSteps;
	}

	/**
	 * Helper to encode the approval sequences (steps)
	 */
	protected function SetSteps($aSteps)
	{
		$this->Set('steps', serialize($aSteps));
	}

	/**
	 * Official mean to declare a new step at the end of the existing sequence
	 * 	 
	 * @param array $aContact An array of array('class' => ..., 'id' => ...)
	 * @param integer $iTimeoutSec The timeout duration if (0 to disable the timeout feature)
	 * @param boolean $bApproveOnTimeout Set to true to approve in case of timeout for the current step
	 * @param integer $iExitCondition EXIT_ON_... _FIRST_REJECT, _FIRST_APPROVE, _FIRST_REPLY defaults to the legacy behavior
	 * @param boolean $bReusePreviousAnswers Set to true to recycle an answer given by an approver at a previous step (if any)
	 * @return void
	 */
	public function AddStep($aContacts, $iTimeoutSec = 0, $bApproveOnTimeout = true, $iExitCondition = self::EXIT_ON_FIRST_REJECT, $bReusePreviousAnswers = true)
	{
		$aApprovers = array();
		foreach($aContacts as $aApproverData)
		{
			if (!MetaModel::IsValidClass($aApproverData['class']))
			{
				throw new Exception("Approval plugin: Wrong class ".$aApproverData['class']." for the approver");
			}
			$aApproverStatus = array(
				'class' => $aApproverData['class'],
				'id' => $aApproverData['id'],
				'passcode' => mt_rand(11111,99999),
			);
			if (array_key_exists('forward', $aApproverData))
			{
				$aApproverStatus['forward'] = array();
				foreach($aApproverData['forward'] as $aSubstituteData)
				{
					if (!MetaModel::IsValidClass($aSubstituteData['class']))
					{
						throw new Exception("Approval plugin: Wrong class ".$aApproverData['class']." for the approver");
					}
					$aSubstituteStatus = array(
						'class' => $aSubstituteData['class'],
						'id' => $aSubstituteData['id'],
						'passcode' => mt_rand(11111,99999),
						'timeout_percent' => $aSubstituteData['timeout_percent'],
					);
					if (array_key_exists('role', $aSubstituteData))
					{
						$aSubstituteStatus['role'] = $aSubstituteData['role'];
					}
					$aApproverStatus['forward'][] = $aSubstituteStatus;
				}
			}
			$aApprovers[] = $aApproverStatus;
		}

		$aNewStep = array(
			'timeout_sec' => $iTimeoutSec,
			'timeout_approve' => $bApproveOnTimeout,
			'exit_condition' => $iExitCondition,
			'reuse_previous_answers' => $bReusePreviousAnswers,
			'status' => 'idle', 
			'approvers' => $aApprovers,
		);

		$aSteps = $this->GetSteps();
		$aSteps[] = $aNewStep;
		$this->SetSteps($aSteps);
	}

	/**
	 * Alternative to AddStep: Adds a step IIF the query returns at least one approver
	 *
	 * @param DBObject $oObject The source object (query arguments :this->attcode)
	 * @param string $sApproversQuery OQL giving the approvers
	 * @param integer $iTimeoutSec The timeout duration if (0 to disable the timeout feature)
	 * @param boolean $bApproveOnTimeout Set to true to approve in case of timeout for the current step
	 * @param integer $iExitCondition EXIT_ON_... _FIRST_REJECT, _FIRST_APPROVE, _FIRST_REPLY defaults to the legacy behavior
	 * @param boolean $bReusePreviousAnswers Set to true to recycle an answer given by an approver at a previous step (if any)
	 * @return bool true if a step has been added
	 */
	public function AddStepFromQuery(DBObject $oObject, $sApproversQuery, $iTimeoutSec = 0, $bApproveOnTimeout = true, $iExitCondition = self::EXIT_ON_FIRST_REJECT, $bReusePreviousAnswers = true)
	{
		$bRet = false;
		if ($sApproversQuery != '')
		{
			$oApproverSet = new DBObjectSet(DBObjectSearch::FromOQL($sApproversQuery), array(), $oObject->ToArgs('this'));
			if ($oApproverSet->count() != 0)
			{
				$bRet = true;
				$aContacts = array();
				while ($oApprover = $oApproverSet->Fetch())
				{
					$sType = get_class($oApprover);
					$aContacts[] = array(
						'class' => $sType,
						'id' => $oApprover->GetKey(),
					);
				}
				$this->AddStep($aContacts, $iTimeoutSec, $bApproveOnTimeout, $iExitCondition, $bReusePreviousAnswers);
			}
		}
		return $bRet;
	}

	/**
	 * Helper to build the button and associated dialog, if relevant, enabled, etc.
	 */	 	
	protected function GetReminderButton($oPage, $aStepData)
	{
		$sRet = '';
		if (MetaModel::GetModuleSetting('approval-base', 'enable_reminder', true))
		{
			if (($aStepData['status'] == 'ongoing') && ($this->Get('status') == 'ongoing'))
			{
				$aAwaited = $this->GetAwaitedReplies();
				if (count($aAwaited) > 0)
				{
					$aReminders = array();
					foreach ($aAwaited as $aData)
					{
						$oTarget = MetaModel::GetObject($aData['class'], $aData['id'], false);
						if ($oTarget)
						{
							$aReminders[] = $oTarget->Get('friendlyname').' ('.$this->GetApproverEmailAddress($oTarget).')';
						}
					}
					$sRet = '<button id="send_reminder" >'.Dict::S('Approval:Remind-Btn').'</button>';
					$sRet .= '<div id="send_reminder_dlg">'.Dict::S('Approval:Remind-DlgBody').'<ul><li>'.implode('</li><li>', $aReminders).'</li></ul></div>';
					$sRet .= '<div id="send_reminder_reply"></div>';
					$sDialogTitle = addslashes(Dict::S('Approval:Remind-DlgTitle'));
					$sOkButtonLabel = addslashes(Dict::S('UI:Button:Ok'));
					$sCancelButtonLabel = addslashes(Dict::S('UI:Button:Cancel'));
					$iApproval = $this->GetKey();
					$iCurrentStep = $this->Get('current_step');
					$oPage->add_ready_script(
<<<EOF
$('#send_reminder_dlg').dialog({
	width: 400,
	modal: true,
	title: '$sDialogTitle',
	autoOpen: false,
	buttons: [
	{ text: '$sOkButtonLabel', click: function(){
		var me = $(this);
		var oDialog = $(this).closest('.ui-dialog');
		var oParams = {
			'operation': 'send_reminder',
			'approval_id': $iApproval,
			'step': $iCurrentStep,
		};
		oDialog.block();
		$.post(GetAbsoluteUrlModulesRoot()+'approval-base/ajax.approval.php', oParams, function(data) {
			me.dialog( "close" );
			$('#send_reminder_reply').html(data);
			oDialog.unblock();
		});
	} },
	{ text: '$sCancelButtonLabel', click: function() {
		$(this).dialog( "close" );
	} }
	],
});

$('#send_reminder').bind('click', function () {
	$('#send_reminder_dlg')
		.dialog('open');
	return false;
});
EOF
					);
				}
			}
		}
		return $sRet;
	}

	/**
	 * Render the status in HTML
	 */	 	
	public function GetDisplayStatus($oPage)
	{
		$sImgOngoing = utils::GetAbsoluteUrlModulesRoot().'approval-base/waiting-reply.png';
		$sImgApproved = utils::GetAbsoluteUrlModulesRoot().'approval-base/approve.png';
		$sImgRejected = utils::GetAbsoluteUrlModulesRoot().'approval-base/reject.png';
		$sImgArrow = utils::GetAbsoluteUrlModulesRoot().'approval-base/arrow-next.png';
		$sImgBubbleTriangle = utils::GetAbsoluteUrlModulesRoot().'approval-base/bubble-triangle.png';

		$oPage->add_style(
<<<EOF
.approval-step-idle {
	background-color: #F6F6F1;
	opacity: 0.4;
	border-style: dashed;
	border-width: 1px;
	padding:10px;	
}
.approval-step-start {
	background-color: #F6F6F1;
	border-style: solid;
	border-width: 1px;
	padding:10px;	
}
.approval-step-ongoing {
	background-color: #F6F6F1;
	border-style: double;
	border-width: 5px;
	padding:10px;	
}
.approval-step-done-ok {
	background-color: #F6F6F1;
	border-style: solid;
	border-width: 2px;
	padding:10px;	
	border-color: #69BB69;
}
.approval-step-done-ko {
	background-color: #F6F6F1;
	border-style: solid;
	border-width: 2px;
	padding:10px;
	border-color: #BB6969;
}
.approval-idle{
	opacity: 0.4;
}
.approval-timelimit {
	font-weight: bolder;
}
.approval-theoreticallimit {
	opacity: 0.4;
}
.approval-step-header {
	margin: 5px;
	font-weight: bolder;
}
div.approver-label {
	padding: 10px;
	padding-left: 16px;
	margin: 5px;
	margin-right: 0;
	background-color: #A5CAFF;
	-moz-border-radius: 6px;
	-webkit-border-radius: 6px;
	border-radius: 6px;
}
div.approver-answer {
	padding: 10px;
	padding-left: 0px;
	padding-top: 13px;
}
div.approver-with-substitutes {
	background: url(../images/minus.gif) no-repeat left;
	cursor: pointer;	
	padding-left: 15px;
}
div.approver-with-substitutes-closed {
	background: url(../images/plus.gif) no-repeat left;
}
tr.approval-substitutes td div{
	padding-left: 15px;
}
.approval-substitutes.closed {
	display: none;
}
#send_reminder {
	margin-top: 5px;
	width: 100%;
}
EOF
		);

		$sHtml = '';
		// Add a header message in case the process has been aborted
		$iAbortUser = $this->Get('abort_user_id');
		if ($iAbortUser != 0)
		{
			if ($oUser = MetaModel::GetObject('User', $iAbortUser, false))
			{ 
				$sUserInfo = $oUser->GetFriendlyName();
			}
			else
			{
				$sUserInfo = 'User::'.$iAbortUser;
			}
			if (method_exists('AttributeDateTime', 'GetFormat'))
			{
				// Requires iTop >= 2.3.0
				$sAbortDate = AttributeDateTime::GetFormat()->Format($this->Get('abort_date'));
			}
			else
			{
				// Compatibility with iTop < 2.3.0
				$sAbortDate = $this->Get('abort_date');
			}
			$sAbortInfo = '<p>'.Dict::Format('Approval:Tab:End-Abort', $sUserInfo, $sAbortDate).'</p>';
			$sAbortInfo .= '<p><quote>'.str_replace(array("\r\n", "\n", "\r"), "<br/>", htmlentities($this->Get('abort_comment'), ENT_QUOTES, 'UTF-8')).'</quote></p>';

			$sHtml .= "<div id=\"abort_info\" class=\"header_message message_info\" style=\"vertical-align:middle;\">\n";
			$sHtml .= $sAbortInfo."\n";
			$sHtml .= "</div>\n";
		}

		// Build the list of display information
		$sArrow = "<img src=\"$sImgArrow\" style=\"vertical-align:middle;\">";
		$aDisplayData = array();

		$aDisplayData[] = array(
			'date_html' => null,
			'time_html' => null,
			'content_html' => "<div class=\"approval-step-start\">".Dict::S('Approval:Tab:Start')."</div>\n",
		);

		$iStarted = AttributeDateTime::GetAsUnixSeconds($this->Get('started'));
		$iLastEnd = $iStarted;

		$sStarted = $this->GetDisplayTime($iStarted);
		$sCurrDay = $this->GetDisplayDay($iStarted);
		$aDisplayData[] = array(
			'date_html' => $sCurrDay,
			'time_html' => $sStarted,
			'content_html' => $sArrow,
		);

		foreach($this->GetSteps() as $iStep => $aStepData)
		{
			switch ($aStepData['status'])
			{
			case 'done':
			case 'timedout':
				$iStepEnd = $aStepData['ended'];
				$sTimeClass = '';
				$sTimeInfo = '';

				if ($aStepData['approved'])
				{
					$sDivClass = "approval-step-done-ok";
					if ($aStepData['status'] == 'timedout')
					{
						$sStepSumary = Dict::S('Approval:Tab:StepSumary-OK-Timeout');
					}
					else
					{
						$sStepSumary = Dict::S('Approval:Tab:StepSumary-OK');
					}
				}
				else
				{
					$sDivClass = "approval-step-done-ko";
					if ($aStepData['status'] == 'timedout')
					{
						$sStepSumary = Dict::S('Approval:Tab:StepSumary-KO-Timeout');
					}
					else
					{
						$sStepSumary = Dict::S('Approval:Tab:StepSumary-KO');
					}
				}
				$sArrowDivClass = "";
				break;

			case 'ongoing':
				if ($iLastEnd && $aStepData['timeout_sec'] > 0)
				{
					$iStepEnd = $this->ComputeDeadline($iLastEnd, $aStepData['timeout_sec']);
					$sTimeClass = 'approval-timelimit';
					$sTimeInfo = Dict::S('Approval:Tab:StepEnd-Limit');
				}
				else
				{
					// The limit cannot be determined
					$iStepEnd = 0;
					$sTimeClass = '';
					$sTimeInfo = '';
				}

				$sStepSumary = Dict::S('Approval:Tab:StepSumary-Ongoing');
				$sDivClass = "approval-step-ongoing";
				$sArrowDivClass = "approval-idle";
				break;

			case 'idle':
			default:
				if ($this->Get('status') == 'ongoing')
				{			
					if ($iLastEnd && $aStepData['timeout_sec'] > 0)
					{
						$iStepEnd = $this->ComputeDeadline($iLastEnd, $aStepData['timeout_sec']);
						$sTimeClass = 'approval-theoreticallimit';
						$sTimeInfo = Dict::Format('Approval:Tab:StepEnd-Theoretical', round($aStepData['timeout_sec'] / 60));
					}
					else
					{
						// The limit cannot be determined
						$iStepEnd = 0;
						$sTimeClass = '';
						$sTimeInfo = '';
					}
				}
				else
				{
					// The process has been terminated before this step
					$iStepEnd = 0;
					$sTimeClass = '';
					$sTimeInfo = '';
				}

				if ($this->Get('status') == 'ongoing')
				{
					$sStepSumary = Dict::S('Approval:Tab:StepSumary-Idle');
					$sDivClass = "approval-step-idle";
					$sArrowDivClass = "approval-idle";
				}
				else
				{
					$sStepSumary = Dict::S('Approval:Tab:StepSumary-Skipped');
					$sDivClass = "approval-step-idle";
					$sArrowDivClass = "approval-idle";
				}
				break;
			}
			$iLastEnd = $iStepEnd;

			$sStepHtml = '<div class="approval-step-header">'.$sStepSumary.'</div>';
			$sStepHtml .= '<table style="border-collapse: collapse;">';
			foreach($aStepData['approvers'] as $aApproverData)
			{
				$oApprover = MetaModel::GetObject($aApproverData['class'], $aApproverData['id'], false);
				if ($oApprover)
				{
					//$sApprover = $oApprover->GetHyperLink();
					$sApprover = $oApprover->GetName();
				}
				else
				{
					$sApprover = $aApproverData['class'].'::'.$aApproverData['id'];
				}
				if (array_key_exists('approval', $aApproverData))
				{
                    $bApproved = $aApproverData['approval'];
					$sAnwserTimestamp = $aApproverData['answer_time'];
					$sTitleHtml = $this->GetDisplayTime($aApproverData['answer_time']);
					if (isset($aApproverData['comment']) && $aApproverData['comment'] != '')
					{
						$sTitleHtml .= '<br/>'.str_replace(array("\r\n", "\n", "\r"), "<br/>", htmlentities($aApproverData['comment'], ENT_QUOTES, 'UTF-8'));
					}
					if ($bApproved)
					{
						$sAnswer = "<img src=\"$sImgApproved\">";
					}
					else
					{
						$sAnswer = "<img src=\"$sImgRejected\">";
					}
					$sTitleEsc = addslashes($sTitleHtml);
					// Not working in iTop <= 2.0.1
					//$oPage->add_ready_script("$('#answer_$iStep"."_".$aApproverData['id']."').tooltip({items: 'div>img', content: '$sTitleEsc'});");
					$oPage->add_ready_script("$('#answer_$iStep"."_".$aApproverData['id']."_".$sAnwserTimestamp."').qtip( { content: '$sTitleEsc', show: 'mouseover', hide: 'mouseout', style: { name: 'dark', tip: 'leftTop' }, position: { corner: { target: 'rightMiddle', tooltip: 'leftTop' }} } );");
				}
				else
				{
                    $sAnswer = "<img src=\"$sImgOngoing\">";
					$sAnwserTimestamp = '0';
					if (($aStepData['status'] == 'ongoing') && !array_key_exists('forward', $aApproverData))
					{
						// Surround the icon with some meta data to allow a reply here
						$sAnswer = "<span class=\"approval-replier\" approver_class=\"{$aApproverData['class']}\" approver_id=\"{$aApproverData['id']}\">$sAnswer</span>";
					}
				}
				if (array_key_exists('forward', $aApproverData))
				{
					$bShowClosed = true;
					static $iId = 0;
					$sId = "substitutes_".$iId++;

					if (array_key_exists('replier_index', $aApproverData))
					{
						$sApproverAnswer = "<img src=\"$sImgOngoing\">";
					}
					else
					{
						// The answer is the one of the main approver
						$sApproverAnswer = $sAnswer;
					}
					if (($aStepData['status'] == 'ongoing') && !array_key_exists('approval', $aApproverData))
					{
						// Surround the icon with some meta data to allow a reply here
						$sApproverAnswer = "<span class=\"approval-replier\" approver_class=\"{$aApproverData['class']}\" approver_id=\"{$aApproverData['id']}\">$sApproverAnswer</span>";
					}

					$sApprover = "<div class=\"approver-with-substitutes\" id=\"{$sId}\">".$sApprover.'</div>';
					$sSubstitutes = "<table id=\"content_$sId\">";
					$sSubstitutes .= '<tr>';
					$sSubstitutes .= '<td>'.$sApprover.'</td>';
					$sSubstitutes .= '<td class="approval-substitutes">'.$sApproverAnswer.'</td>';
					$sSubstitutes .= '</tr>';

					foreach ($aApproverData['forward'] as $iReplierIndex => $aForwardData)
					{
						$oSubstitute = MetaModel::GetObject($aForwardData['class'], $aForwardData['id'], false);
						if ($oSubstitute)
						{
							//$sSubstitute = $oSubstitute->GetHyperLink();
							$sSubstitute = $oSubstitute->GetName();
						}
						else
						{
							$sSubstitute = $aForwardData['class'].'::'.$aForwardData['id'];
						}
						$sRole = isset($aForwardData['role']) ? ' ('.$aForwardData['role'].')' : '';

						if (array_key_exists('replier_index', $aApproverData) && ($iReplierIndex == $aApproverData['replier_index']))
						{
							// The result is known and this replier is the one who did answer
							$sSubstituteAnswer = $sAnswer;
							$sSubstituteClass = "";
							$bShowClosed = false;
						}
						elseif(array_key_exists('sent_time', $aForwardData))
						{
							$sSubstituteAnswer = "<img src=\"$sImgOngoing\">";
							$sSubstituteClass = "";
							$bShowClosed = false;
							if (($aStepData['status'] == 'ongoing') && !array_key_exists('approval', $aApproverData))
							{
								// Surround the icon with some meta data to allow a reply here
								$sSubstituteAnswer = "<span class=\"approval-replier\" approver_class=\"{$aApproverData['class']}\" approver_id=\"{$aApproverData['id']}\" substitute_class=\"{$aForwardData['class']}\" substitute_id=\"{$aForwardData['id']}\">$sSubstituteAnswer</span>";
							}
						}
						else
						{
							$sSubstituteAnswer = '';
							$sSubstituteClass = "approval-idle";
						}
						$sSubstitutes .= '<tr class="approval-substitutes">';
						//$sSubstitutes .= '<td>'.$aForwardData['timeout_percent'].'%: '.$sSubstitute.$sRole.'</td>';
						$sSubstitutes .= "<td><div class=\"$sSubstituteClass\">".$sSubstitute.$sRole.'</div></td>';
						$sSubstitutes .= '<td>'.$sSubstituteAnswer.'</td>';
						$sSubstitutes .= '</tr>';
					}		
					$sSubstitutes .= '</table>';

					$sApprover = $sSubstitutes;
					$oPage->add_ready_script("$('#{$sId}').click( function() { $('#content_{$sId} .approval-substitutes').toggleClass('closed'); } );\n");
					$oPage->add_ready_script("$('#{$sId}').click( function() { $(this).toggleClass('approver-with-substitutes-closed'); } );\n");
					if ($bShowClosed)
					{
						// Close it for the first display
						$oPage->add_ready_script("$('#content_{$sId} .approval-substitutes').toggleClass('closed');");
						$oPage->add_ready_script("$('#{$sId}').toggleClass('approver-with-substitutes-closed');");
					}
				}
				$sStepHtml .= '<tr>';
				$sStepHtml .= '<td style="vertical-align: top;"><div class="approver-label">'.$sApprover.'</div></td>';
				if (strlen($sAnswer) > 0)
				{
					$sTriangle = "<img src=\"$sImgBubbleTriangle\">";
					$sStepHtml .= '<td style="vertical-align: top;"><div class="approver-answer" id="answer_'.$iStep.'_'.$aApproverData['id'].'_'.$sAnwserTimestamp.'">'.$sTriangle.$sAnswer.'</div></td>';
				}
				else
				{
					$sStepHtml .= '<td>&nbsp;</td>';
				}
				$sStepHtml .= '</tr>';
			}

			// Add a button to send a reminder for the current step (if relevant)
			//
			$sReminderHtml = $this->GetReminderButton($oPage, $aStepData);
			if (strlen($sReminderHtml) > 0)
			{
				$sStepHtml .= '<tr>';
				$sStepHtml .= '<td colspan="2" align="center">'.$sReminderHtml.'</td>';
				$sStepHtml .= '</tr>';
			}
			$sStepHtml .= '</table>';

			$aDisplayData[] = array(
				'date_html' => null,
				'time_html' => null,
				'content_html' => "<div class=\"$sDivClass\">$sStepHtml</div>\n",
			);

			// New feature: the array entry 'exit_condition' might be missing
			$iExitCondition = isset($aStepData['exit_condition']) ? $aStepData['exit_condition'] : self::EXIT_ON_FIRST_REJECT;
			switch($iExitCondition)
			{
				case self::EXIT_ON_FIRST_REPLY:
				$sExplainCondition = Dict::S('Approval:Tab:StepEnd-Condition-FirstReply');
				break;
	
				case self::EXIT_ON_FIRST_APPROVE:
				$sExplainCondition = Dict::S('Approval:Tab:StepEnd-Condition-FirstApprove');
				break;
	
				case self::EXIT_ON_FIRST_REJECT:
				default:
				$sExplainCondition = Dict::S('Approval:Tab:StepEnd-Condition-FirstReject');
				break;
			}
			if ($iStepEnd)
			{
				// Display the date iif it has changed
				//
				if ($this->GetDisplayDay($iStepEnd) != $sCurrDay)
				{
					$sStepEndDate = $this->GetDisplayDay($iStepEnd);
					$sCurrDay = $sStepEndDate;
				}
				else
				{
					// Same day
					$sStepEndDate = '&nbsp;';
				}
	
				$aDisplayData[] = array(
					'date_html' => '<span class="'.$sTimeClass.'" title="'.$sTimeInfo.'">'.$sStepEndDate.'</span>',
					'time_html' => '<span class="'.$sTimeClass.'" title="'.$sTimeInfo.'">'.$this->GetDisplayTime($iStepEnd).'</span>',
					'content_html' => "<div class=\"$sArrowDivClass\" title=\"$sExplainCondition\">$sArrow</div>\n",
				);
			}
			else
			{
				$aDisplayData[] = array(
					'date_html' => '',
					'time_html' => '',
					'content_html' => "<div class=\"$sArrowDivClass\" title=\"$sExplainCondition\">$sArrow</div>\n",
				);
			}
		}

		switch ($this->Get('status'))
		{
		case 'ongoing':
			$sFinalStatus = "<img style=\"display: inline-block; vertical-align:middle;\" src=\"$sImgOngoing\">";
			$sDivClass = "approval-step-idle";
			break;
		case 'accepted':
			$sFinalStatus = "<img style=\"display: inline-block; vertical-align:middle;\" src=\"$sImgApproved\">";
			$sDivClass = "approval-step-done-ok";
			break;
		case 'rejected':
			$sFinalStatus = "<img style=\"display: inline-block; vertical-align:middle;\" src=\"$sImgRejected\">";
			$sDivClass = "approval-step-done-ko";
			break;
		}

		$aDisplayData[] = array(
			'date_html' => null,
			'time_html' => null,
			'content_html' => "<div id=\"final_result\" class=\"$sDivClass\"><div style=\"display: inline-block; vertical-align: middle;\">".Dict::S('Approval:Tab:End').": </div>&nbsp;$sFinalStatus</div>\n",
		);

		// Diplay the information
		//
		$sHtml .= "<table id=\"process_status_table\">\n";
		$sHtml .= "<tr>\n";
		$sHtml .= "<td colspan=\"2\"></td>\n";
		foreach($aDisplayData as $aDisplayEvent)
		{
			if (!is_null($aDisplayEvent['date_html']))
			{
				if (strlen($aDisplayEvent['date_html']) > 0)
				{
					$sHtml .= "<td colspan=\"2\">".$aDisplayEvent['date_html']."</td>\n";
				}
				else
				{
					$sHtml .= "<td colspan=\"2\">&nbsp;</td>\n";
				}
			}
		}		
		$sHtml .= "</tr>\n";
		$sHtml .= "<tr>\n";
		$sHtml .= "<td colspan=\"2\"></td>\n";
		foreach($aDisplayData as $aDisplayEvent)
		{
			if (!is_null($aDisplayEvent['time_html']))
			{
				if (strlen($aDisplayEvent['time_html']) > 0)
				{
					$sHtml .= "<td colspan=\"2\">".$aDisplayEvent['time_html']."</td>\n";
				}
				else
				{
					$sHtml .= "<td>&nbsp;</td>\n";
				}
			}
		}		
		$sHtml .= "</tr>\n";
		$sHtml .= "<tr style=\"vertical-align:middle;\">\n";
		$sHtml .= "<td></td>\n";
		foreach($aDisplayData as $aDisplayEvent)
		{
			if ($aDisplayEvent['content_html'])
			{
				$sHtml .= "<td>".$aDisplayEvent['content_html']."</td>\n";
			}
		}		
		$sHtml .= "</tr>\n";
		$sHtml .= "</table>\n";

		$sLastError = $this->Get('last_error');
		if (strlen($sLastError) > 0)
		{
			$sHtml .= '<p>'.Dict::Format('Approval:Tab:Error', $sLastError).'</p>';
		}

		return $sHtml;
	}

	/** Helper to record the end of the process in several cases
	 * - normal termination
	 * - abort
	 */
	protected function RecordEnd($bApproved)
	{
		$this->Set('ended', $this->Now());
		$this->Set('status', $bApproved ? 'accepted' : 'rejected');
		$this->DBUpdate();

		if ($oObject = MetaModel::GetObject($this->Get('obj_class'), $this->Get('obj_key'), false))
		{
			if ($bApproved)
			{
				$this->DoApprove($oObject);
			}
			else
			{
				$this->DoReject($oObject);
			}
			if ($oObject->IsModified())
			{
				$oObject->DBUpdate();
			}
		}
	}

	/**
	 * Start the step <current_step>, or terminates if either...
	 * - the last step executed has been rejected
	 * - there is no more step to process
	 * 
	 * On termination: determines + records the final status
	 * 	 and invokes the relevant verb (DoApprove/DoReject)	 	 
	 */	 
	public function StartNextStep()
	{
		$aSteps = $this->GetSteps();
		$iCurrentStep = $this->Get('current_step');

		// Determine the status for the previous step (if any)
		//
		if (array_key_exists($iCurrentStep - 1, $aSteps))
		{
			$aPrevStep = $aSteps[$iCurrentStep - 1];
			$bPrevApproved = $aPrevStep['approved'];
		}
		else
		{
			// Starting...
			$bPrevApproved = true;
		}

		if ($bPrevApproved && array_key_exists($iCurrentStep, $aSteps))
		{
			// Actually continue with the next step
			//
			$aStepData = &$aSteps[$iCurrentStep];
			$aStepData['status'] = 'ongoing';
			$aStepData['started'] = $this->Now();
			$this->SetSteps($aSteps);
			$this->Set('timeout', $this->ComputeTimeout());
			$this->DBUpdate();

			// New capability that appeared in 2.5.0, thus could be missing in the data structure
			// in such a case the default is FALSE (though the default behavior for newly created schemes will by TRUE!)
			$bReusePreviousAnswers = array_key_exists('reuse_previous_answers', $aStepData) ? $aStepData['reuse_previous_answers'] : false;

			$oObject = MetaModel::GetObject($this->Get('obj_class'), $this->Get('obj_key'));

			if ($bReusePreviousAnswers)
			{
				foreach ($aStepData['approvers'] as &$aApproverData)
				{
					$oApprover = MetaModel::GetObject($aApproverData['class'], $aApproverData['id'], false);
					if ($oApprover)
					{
						list($iReplyStep, $bApproved, $sComment) = $this->FindAnswer($iCurrentStep, $aApproverData);
						if ($iReplyStep !== null)
						{
							// Note: the step must be 1-based
							$sNewComment = Dict::Format('Approval:Comment-Reused', $iReplyStep + 1, $sComment);
							$this->OnAnswer($iCurrentStep, $oApprover, $bApproved, null, $sNewComment);
							// Something may happen within OnAnswer (and already saved into the DB)
							if ($this->Get('current_step') != $iCurrentStep)
							{
								// The step has been concluded. Exit the prodedure ASAP!
								break;
							}
						}
					}
				}
				// Some step data might have changed... refresh the local variables (avoids sending an email when an answer has been reused)
				$aSteps = $this->GetSteps();
				$aStepData = &$aSteps[$iCurrentStep];
			}
			if ($this->Get('current_step') == $iCurrentStep)
			{
				foreach ($aStepData['approvers'] as &$aApproverData)
				{
					if (!array_key_exists('approval', $aApproverData))
					{
						$oApprover = MetaModel::GetObject($aApproverData['class'], $aApproverData['id'], false);
						if ($oApprover)
						{
							$this->SendApprovalRequest($oApprover, $oObject, $aApproverData['passcode']);
						}
					}
				}
			}
		}
		else
		{
			// Done !
			//
			$this->RecordEnd($bPrevApproved);
		}
	}

	/**
	 * Helper to determine the real approver behind a replier (himself or somebody else)
	 *
	 * @param $oReplier
	 * @return null
	 * @throws CoreException
	 */
	public function FindApprover($oReplier)
	{
		if ($this->Get('status') != 'ongoing')
		{
			return null;
		}

		$aSteps = $this->GetSteps();
		$iCurrentStep = $this->Get('current_step');
		if (!array_key_exists($iCurrentStep, $aSteps))
		{
			return null;
		}
		$aStepData = $aSteps[$iCurrentStep];
		foreach($aStepData['approvers'] as $aApproverData)
		{
			if (($aApproverData['class'] == get_class($oReplier)) && ($aApproverData['id'] == $oReplier->GetKey()))
			{
				return $oReplier; // The replier is the approver himself
			}
			else
			{
				// The answer may be originated by the approver or a substitute
				//
				if (array_key_exists('forward', $aApproverData))
				{
					foreach ($aApproverData['forward'] as $iIndex => $aSubstituteData)
					{
						if (($aSubstituteData['class'] == get_class($oReplier)) && ($aSubstituteData['id'] == $oReplier->GetKey()))
						{
							// The replier is a substitue: return the real approver
							$oApprover = MetaModel::GetObject($aApproverData['class'], $aApproverData['id']);
							return $oApprover;
						}
					}
				}
			}
		}
		return null;
	}

	/**
	 * Processes a vote given by an approver:
	 * - find the approver
	 * - record the answer
	 * Then, start the next step if the current one is over 
	 */	 
	public function OnAnswer($iStep, $oApprover, $bApprove, $oSubstitute = null, $sComment = '')
	{
		if ($this->Get('status') != 'ongoing')
		{
			return;
		}

		$aSteps = $this->GetSteps();
		$iCurrentStep = $this->Get('current_step');
		if (!array_key_exists($iCurrentStep, $aSteps))
		{
			return;
		}
		$aStepData = &$aSteps[$iCurrentStep];
		foreach($aStepData['approvers'] as &$aApproverData)
		{
			if (($aApproverData['class'] == get_class($oApprover)) && ($aApproverData['id'] == $oApprover->GetKey()))
			{
				// Record the approval result
				//
				$aApproverData['approval'] = $bApprove;
				$aApproverData['answer_time'] = $this->Now();
				if ($sComment != '')
				{
					$aApproverData['comment'] = $sComment;
				}
				// RecordComment does not solely record the comment... that's why it must be called anytime
				$this->RecordComment($sComment, $this->GetIssuerInfo($bApprove, $oApprover, $oSubstitute));

				// The answer may be originated by the approver or a substitute
				//
				if (!is_null($oSubstitute) && (array_key_exists('forward', $aApproverData)))
				{
					$iReplierIndex = null;
					foreach ($aApproverData['forward'] as $iIndex => $aSubstituteData)
					{
						if (($aSubstituteData['class'] == get_class($oSubstitute)) && ($aSubstituteData['id'] == $oSubstitute->GetKey()))
						{
							$iReplierIndex = $iIndex;
							break;
						}
					}
					if (!is_null($iReplierIndex))
					{
						$aApproverData['replier_index'] = $iReplierIndex;
					}
				}
			}
		}
		$this->SetSteps($aSteps);
		$this->Set('timeout', $this->ComputeTimeout());
		$this->DBUpdate();

		$bStepResult = $this->GetStepResult($aStepData);
		if (!is_null($bStepResult))
		{
			$aStepData['status'] = 'done';
			$aStepData['ended'] = $this->Now();
			$aStepData['approved'] = $bStepResult;
			$this->SetSteps($aSteps);
			$this->Set('timeout', null);
			$this->DBUpdate();

			$this->Set('current_step', $iCurrentStep + 1);
			$this->StartNextStep();
		}
	}

	/**
	 * Aborting means stopping definitively the ENTIRE process (not only the current step)
	 */	 
	public function OnAbort($bApprove, $sComment)
	{
		if ($this->Get('status') != 'ongoing')
		{
			return;
		}
		// The user friendly name should be formatted the same way as it is the case for the approvers
		$iContactId = UserRights::GetContactId();
		if ($iContactId == '')
		{
			$sUserFriendlyName = UserRights::GetUserFriendlyName();
		}
		else
		{
			$oContact = MetaModel::GetObject('Contact', $iContactId);
			$sUserFriendlyName = $oContact->Get('friendlyname');
		}
		
		if ($bApprove)
		{
			$sIssuerInfo = Dict::Format('Approval:Approved-By', $sUserFriendlyName);
		}
		else
		{
			$sIssuerInfo = Dict::Format('Approval:Rejected-By', $sUserFriendlyName);
		}
		// RecordComment does not solely record the comment... that's why it must be called even if the comment is empty
		$this->RecordComment($sComment, $sIssuerInfo);

		$this->Set('abort_user_id', UserRights::GetUserId());
		$this->Set('abort_date', $this->Now());
		$this->Set('abort_comment', $sComment);
		$this->RecordEnd($bApprove);
	}

	/**
	 * Helper to determine if a given user is expected to give her answer
	 */
	public function GetContactPassCode($sContactClass, $iContactId)
	{
		if ($this->Get('status') != 'ongoing')
		{
			return null;
		}

		$aSteps = $this->GetSteps();
		$iCurrentStep = $this->Get('current_step');
		if (!array_key_exists($iCurrentStep, $aSteps))
		{
			return null;
		}
		$aStepData = $aSteps[$iCurrentStep];
		foreach($aStepData['approvers'] as &$aApproverData)
		{
			if (isset($aApproverData['answer_time']))
			{
				// The answer has been given: skip
				continue;
			}
			if (($aApproverData['class'] == $sContactClass) && ($aApproverData['id'] == $iContactId))
			{
				return $aApproverData['passcode'];
			}
			if (array_key_exists('forward', $aApproverData))
			{
				foreach ($aApproverData['forward'] as $iIndex => $aSubstituteData)
				{
					if (($aSubstituteData['class'] == $sContactClass) && ($aSubstituteData['id'] == $iContactId))
					{
						return $aSubstituteData['passcode'];
					}
				}
			}
		}
		return null;
	}	  	

	/**
	 * Helper to compute current state start time - this information is not recorded
	 */
	public function ComputeLastStart()
	{
		$iStepStarted = AttributeDateTime::GetAsUnixSeconds($this->Get('started'));
		foreach($this->GetSteps() as $iStep => $aStepData)
		{
			switch ($aStepData['status'])
			{
			case 'done':
			case 'timedout':
				$iStepStarted = max($iStepStarted, $aStepData['ended']);
				break;
			}
		}
		return $iStepStarted;
	}
	 
	/**
	 * Helper to compute a target time, depending on the working hours
	 */
	protected function ComputeDeadline($iStartTime, $iDurationSec)
	{
		static $oComputer = null;
		if ($oComputer == null)
		{
			$sWorkingTimeComputer = $this->GetWorkingTimeComputer();
			if (!class_exists($sWorkingTimeComputer))
			{
				throw new CoreException("The provided working time computer is not a valid class: '$sWorkingTimeComputer'. Please, review the implementation of GetWorkingTimeComputer()");
			}
			$oComputer = new $sWorkingTimeComputer();
		}

		$oObject = MetaModel::GetObject($this->Get('obj_class'), $this->Get('obj_key'));
		$aCallSpec = array($oComputer, 'GetDeadline');
		if (!is_callable($aCallSpec))
		{
			throw new CoreException("Unknown class/verb '$sWorkingTimeComputer/GetDeadline'");
		}
		$oStartDate = new DateTime('@'.$iStartTime); // setTimestamp not available in PHP 5.2
		$oDeadline = call_user_func($aCallSpec, $oObject, $iDurationSec, $oStartDate);
		$iRet = $oDeadline->format('U');
		return $iRet;
	}

	/**
	 * Compute the next timeout (depends on the step and the eventual forwards)
	 */
	public function ComputeTimeout()
	{
		$aSteps = $this->GetSteps();
		$iCurrentStep = $this->Get('current_step');
		if (!array_key_exists($iCurrentStep, $aSteps))
		{
			return null;
		}
		$aStepData = $aSteps[$iCurrentStep];

		if ($aStepData['timeout_sec'] == 0)
		{
			// No timeout for the current step
			return null;
		}

		// Next timeout is the minimum amongst the overall timeout and the forward timeouts
		//
		$iStepStarted = $this->ComputeLastStart();
		$iMinTimeout = $aStepData['timeout_sec'];
		foreach($aStepData['approvers'] as $aApproverData)
		{
			// Skip this approver if the answer has been given (by the approver or any of the forwards)
			if (array_key_exists('approval', $aApproverData)) continue;
			// Skip this approver if no forwarding is planned
			if (!array_key_exists('forward', $aApproverData)) continue;

			foreach ($aApproverData['forward'] as $aForwardData)
			{
				// Skip this forward approver if already notified
				if (array_key_exists('sent_time', $aForwardData)) continue;

				$iMinTimeout = min($iMinTimeout, $aStepData['timeout_sec'] * $aForwardData['timeout_percent'] / 100);
			}
		}
		// Note: it is important to make sure that iMinTimeout is actually an integer (strange effects otherwise!) 
		return $this->ComputeDeadline($iStepStarted, floor($iMinTimeout));
	}

	/**
	 * A timeout can occur in two conditions:
	 * - The current step is running out of time: terminate it and start the next one
	 * - An forward has been declared for an approver who has not yet replied	 
	 */	 
	public function OnTimeout()
	{
		if ($this->Get('status') != 'ongoing')
		{
			return;
		}
		$iCurrentStep = $this->Get('current_step');

		$aSteps = $this->GetSteps();
		if (!array_key_exists($iCurrentStep, $aSteps))
		{
			return;
		}
		$aStepData = &$aSteps[$iCurrentStep];
		if ($aStepData['status'] != 'ongoing')
		{
			return;
		}

		$iStepStarted = $this->ComputeLastStart();
		if ($this->Now() >= $this->ComputeDeadline($iStepStarted, $aStepData['timeout_sec']))
		{
			// Time is over for the current step!
			//
			$aStepData['status'] = 'timedout';
			$aStepData['ended'] = $this->Now();
			$aStepData['approved'] = $aStepData['timeout_approve'];
			$this->SetSteps($aSteps);
			$this->Set('timeout', null);
			$this->DBUpdate();
	
			$this->Set('current_step', $iCurrentStep + 1);
			$this->StartNextStep();
		}
		else
		{
			// The time is over for some of the forward approvers
			//
			$oObject = MetaModel::GetObject($this->Get('obj_class'), $this->Get('obj_key'));
			foreach($aStepData['approvers'] as &$aApproverData)
			{
				// Skip this approver if the answer has been given (by the approver or any of the forwards)
				if (array_key_exists('approval', $aApproverData)) continue;
				// Skip this approver if no forwarding is planned
				if (!array_key_exists('forward', $aApproverData)) continue;

				foreach ($aApproverData['forward'] as &$aForwardData)
				{
					// Skip this forward approver if already notified
					if (array_key_exists('sent_time', $aForwardData)) continue;

					if ($this->Now() >= $this->ComputeDeadline($iStepStarted, $aStepData['timeout_sec'] * $aForwardData['timeout_percent'] / 100))
					{
						// Time is over for this approver: forward the notification
						//
						$aForwardData['sent_time'] = $this->Now();
						$oApprover = MetaModel::GetObject($aForwardData['class'], $aForwardData['id'], false);
						if ($oApprover)
						{
							$oSubstituteTo = MetaModel::GetObject($aApproverData['class'], $aApproverData['id'], false);
							$this->SendApprovalRequest($oApprover, $oObject, $aForwardData['passcode'], $oSubstituteTo);
						}
					}
				}
			}
			// Record the changes and reset the timer to the next timeout
			$this->SetSteps($aSteps);
			$this->Set('timeout', $this->ComputeTimeout());
			$this->DBUpdate();
		}
	}

	/**
	 * Helper to list the expected replies, and send a reminder
	 */
	public function GetAwaitedReplies()
	{
		if ($this->Get('status') != 'ongoing')
		{
			return array();
		}
		$iCurrentStep = $this->Get('current_step');

		$aSteps = $this->GetSteps();
		if (!array_key_exists($iCurrentStep, $aSteps))
		{
			return array();
		}
		$aStepData = &$aSteps[$iCurrentStep];
		if ($aStepData['status'] != 'ongoing')
		{
			return array();
		}

		$aRecipients = array();
		foreach($aStepData['approvers'] as $aApproverData)
		{
			// Skip this approver if the answer has been given (by the approver or any of the forwards)
			if (array_key_exists('approval', $aApproverData)) continue;
			$aRecipients[] = array(
				'class' => $aApproverData['class'],
				'id' => $aApproverData['id'],
				'passcode' => $aApproverData['passcode']
			);

			if (array_key_exists('forward', $aApproverData))
			{
				foreach ($aApproverData['forward'] as $aForwardData)
				{
					if (array_key_exists('sent_time', $aForwardData))
					{
						$aRecipients[] = array(
							'class' => $aForwardData['class'],
							'id' => $aForwardData['id'],
							'passcode' => $aForwardData['passcode'],
							'substitute_to' => array(
								'class' => $aApproverData['class'],
								'id' => $aApproverData['id'],
							)
						);
					}
				}
			}
		}
		return $aRecipients;
	}
		 	
	/**
	 * Legacy behavior (defaults to this value if the flag is omitted).
	 * Terminate the step with failure as soon as one rejection occurs.
	 * The step successes if everybody approves.
	 */	
	const EXIT_ON_FIRST_REJECT = 1;
	/**
	 * Terminate the step with success as soon as one approval occurs.
	 * The step fails if everybody rejects.
	 */	
	const EXIT_ON_FIRST_APPROVE = 2;
	/**
	 * Terminate the step with the first reply.
	 * Failure or success of the step depends solely on this unique reply.
	 */	
	const EXIT_ON_FIRST_REPLY = 3;

	/**
	 * Helper: do we consider that enough votes have been given?
	 */
	protected function GetStepResult($aStepData)
	{
		// New feature: the array entry 'exit_condition' might be missing
		$iExitCondition = isset($aStepData['exit_condition']) ? $aStepData['exit_condition'] : self::EXIT_ON_FIRST_REJECT;

		$bIsExpectingAnswers = false;
		$bLastAnswer = null;
		foreach($aStepData['approvers'] as &$aApproverData)
		{
			if (array_key_exists('approval', $aApproverData))
			{
				$bLastAnswer = $aApproverData['approval'];
				if ($iExitCondition == self::EXIT_ON_FIRST_REPLY)
				{
					// One single answer makes it
					return $bLastAnswer;
				}

				if ($bLastAnswer)
				{
					if ($iExitCondition == self::EXIT_ON_FIRST_APPROVE)
					{
						// One positive answer is enough
						return true;
					}
				}
				else
				{
					if ($iExitCondition == self::EXIT_ON_FIRST_REJECT)
					{
						// One negative answer is enough
						return false;
					}
				}
			}
			else
			{
				// This answer is still missing
				$bIsExpectingAnswers = true;
			}
		}
		if ($bIsExpectingAnswers)
		{
			// We are still waiting for some votes
			return null;
		}
		else
		{
			// 100% positive or 100% negative, or the latest reply (the latter is a nonsense and should never occur)
			return $bLastAnswer;
		}
	}

	/**
	 * Lookup for any existing answer (returns information on the first found)
	 *
	 * @param $iStrictlyBeforeStep Step before which the search will be made
	 * @param $aSearchedApproverData The approver which reply should be found
	 * @return array($iStep, $bApproved, $sComment)
	 */
	protected function FindAnswer($iStrictlyBeforeStep, $aSearchedApproverData)
	{
		$iFoundStep = null;
		$bApproved = null;
		$sComment = null;
		$sSearchApproverClass = $aSearchedApproverData['class'];
		$iSearchApproverId = $aSearchedApproverData['id'];

		foreach($this->GetSteps() as $iStep => $aStepData)
		{
			if ($iStep >= $iStrictlyBeforeStep) continue;
			foreach($aStepData['approvers'] as &$aApproverData)
			{
				if ($aApproverData['class'] != $sSearchApproverClass) continue;
				if ($aApproverData['id'] != $iSearchApproverId) continue;

				// We have a match, did it reply?
				if (array_key_exists('approval', $aApproverData))
				{
					$iFoundStep = $iStep;
					$bApproved = $aApproverData['approval'];
					$sComment = isset($aApproverData['comment']) ? $aApproverData['comment'] : '';
					break;
				}
			}
		}

		return array($iFoundStep, $bApproved, $sComment);
	}

	protected function MakeFormHeader($sFrom, $oPage, $oApprover, $oObject, $sToken, $oSubstitute = null)
	{
		$aParams = array_merge($oObject->ToArgs('object'), $oApprover->ToArgs('approver'));

		$sIntroduction = MetaModel::ApplyParams($this->GetFormBody(get_class($oApprover), $oApprover->GetKey()), $aParams);
		$oPage->add("<div id=\"form_approval_introduction\">".$sIntroduction."</div>\n");
	}

	protected function MakeFormInputs($sFrom, $oPage, $sInjectInForm = '')
	{
		$oPage->add("<div class=\"wizContainer\" id=\"form_approval\">\n");
		$oPage->add("<form action=\"\" id=\"form_approve\" method=\"post\">\n");
		$oPage->add("<input type=\"hidden\" id=\"my_operation\" name=\"operation\" value=\"_not_set_\">");
		$oPage->add($sInjectInForm);
		$oPage->add("<input type=\"hidden\" name=\"from\" value=\"$sFrom\">");
	
		$oPage->add('<div title="'.Dict::S('Approval:Comment-Tooltip').'">'.Dict::S('Approval:Comment-Label').'</div>');
		$oPage->add("<textarea type=\"textarea\" name=\"comment\" id=\"comment\" class=\"resizable\" cols=\"80\" rows=\"5\"></textarea>");
		$oPage->add("<input type=\"submit\" id=\"approval-button\" onClick=\"$('#my_operation').val('do_approve');\" value=\"".Dict::S('Approval:Action-Approve')."\">");
		$oPage->add("<input type=\"submit\" id=\"rejection-button\" onClick=\"$('#my_operation').val('do_reject');\" value=\"".Dict::S('Approval:Action-Reject')."\">");
		$oPage->add("<span id=\"comment_mandatory\">".Dict::S('Approval:Comment-Mandatory')."</span>");
		$oPage->add("</form>");
		$oPage->add("</div>");

		$oPage->add_ready_script(
<<<EOF
function RefreshRejectionButtonState()
{
	var sComment = $.trim($('#comment').val());
	if (sComment.length == 0)
	{
		$('#rejection-button').prop('disabled', true);
		$('#comment_mandatory').show();
	}
	else
	{
		$('#rejection-button').prop('disabled', false);
		$('#comment_mandatory').hide();
	}
}
$('#comment').bind('change keyup', function () {
	RefreshRejectionButtonState();
});
RefreshRejectionButtonState();
EOF
		);
	}

	protected function MakeFormFooter($sFrom, $oPage, $oApprover, $oObject, $sToken, $oSubstitute = null)
	{
		$aParams = array_merge($oObject->ToArgs('object'), $oApprover->ToArgs('approver'));

		// Object details
		//
		if ($this->IsAllowedToSeeObjectDetails($oApprover, $oObject))
		{
			$this->DisplayObjectDetails($oPage, $oApprover, $oObject, $oSubstitute);
		}
		else
		{
			$sIntroduction = MetaModel::ApplyParams($this->GetPublicObjectDetails(get_class($oApprover), $oApprover->GetKey()), $aParams);
			$oPage->add('<div class="email_body">'.$sIntroduction.'</div>');
		}
	}

	/**
	 * Build and output the approval form for a given user
	 **/	
	public function DisplayApprovalForm($sFrom, $oPage, $oApprover, $oObject, $sToken, $oSubstitute = null)
	{
		$this->MakeFormHeader($sFrom, $oPage, $oApprover, $oObject, $sToken, $oSubstitute);
		$this->MakeFormInputs($sFrom, $oPage, "<input type=\"hidden\" name=\"token\" value=\"$sToken\">");
		$this->MakeFormFooter($sFrom, $oPage, $oApprover, $oObject, $sToken, $oSubstitute);
	}

	/**
	 * Build and output the abort form for the current user
	 */
	public function DisplayAbortForm($sFrom, $oPage)
	{
		$oPage->p(Dict::S('Approval:Abort:Explain'));
	
		$this->MakeFormInputs($sFrom, $oPage, "<input type=\"hidden\" name=\"abort\" value=\"1\"><input type=\"hidden\" name=\"approval_id\" value=\"".$this->GetKey()."\">");
	}

	/**
	 * Overridable to change the display of days	
	 */	
	public function GetDisplayDay($iTime)
	{
		if (method_exists('AttributeDateTime', 'GetFormat'))
		{
			// Requires iTop >= 2.3.0
			$sDateFormat = (string)AttributeDate::GetFormat();
		}
		else
		{
			// Compatibility with iTop < 2.3.0
			$sDateFormat = 'Y-m-d';
		}
		return date($sDateFormat, $iTime);
	}

	/**
	 * Overridable to change the display of time	
	 */	
	public function GetDisplayTime($iTime)
	{
		if (method_exists('AttributeDateTime', 'GetFormat'))
		{
			// Requires iTop >= 2.3.0
			// Note: this code has been cut&pasted from AttributeDateTime. Make sure that it remains in sync with the version of iTop
			$aFormats = MetaModel::GetConfig()->Get('date_and_time_format');
			$sLang = Dict::GetUserLanguage();
			$sTimeFormat = isset($aFormats[$sLang]['time']) ? $aFormats[$sLang]['time'] : (isset($aFormats['default']['time']) ? $aFormats['default']['time'] : 'H:i:s');
		}
		else
		{
			// Compatibility with iTop < 2.3.0
			$sTimeFormat = 'H:i';
		}
		return date($sTimeFormat, $iTime);
	}

	static public function GetPopMenuItems($iMenuId, $param, $sClassFilter = 'UserRequest')
	{
		$aRet = array();
		if ($iMenuId == iPopupMenuExtension::MENU_OBJDETAILS_ACTIONS)
		{
			$oObject = $param;


			// Filter out the object out of scope of the approval processes
			if ($oObject instanceOf $sClassFilter)
			{
				// Is there an ongoing approval process for the object ?
				$oApprovSearch = DBObjectSearch::FromOQL('SELECT ApprovalScheme WHERE status = \'ongoing\' AND obj_class = :obj_class AND obj_key = :obj_key');
				$oApprovSearch->AllowAllData();
				$oApprovals = new DBObjectSet($oApprovSearch, array(), array('obj_class' => get_class($oObject), 'obj_key' => $oObject->GetKey()));
				if ($oApprovals->Count() > 0)
				{
					$oApproval = $oApprovals->Fetch();

					// Is the current user associated to a contact ?
					$iContactId = UserRights::GetContactId();
					if ($iContactId > 0)
					{
						// Does the approval concern the current user?
						$sReplyUrl = $oApproval->MakeReplyUrl('Person', $iContactId);
						if (!is_null($sReplyUrl))
						{
							// Here we are: add a menu to approve or reject the request
							$aRet[] = new URLPopupMenuItem('approval_reply_url', Dict::S('Approval:Action-ApproveOrReject'), $sReplyUrl);
						}
					}
					if ($oApproval->IsAllowedToAbort())
					{
						$sReplyUrl = $oApproval->MakeAbortUrl();
						$aRet[] = new URLPopupMenuItem('approval_abort_url', Dict::S('Approval:Action-Abort'), $sReplyUrl);
					}
				}
			}
		}
		return $aRet;
	}

	/**
	 * API to search for Approvals
	 *
	 * @param null $sApproverClass
	 * @param null $iApproverId
	 * @return array of ApprovalSheme objects
	 */
	static public function ListOngoingApprovals($sApproverClass = null, $iApproverId = null)
	{
		$oSearch = DBObjectSearch::FromOQL("SELECT ApprovalScheme WHERE status = 'ongoing'");
		$oSet = new DBObjectSet($oSearch, array('started' => true));
		$aApprovals = array();
		while ($oApproval = $oSet->Fetch())
		{
			if (is_null($sApproverClass) || $oApproval->IsActiveApprover($sApproverClass, $iApproverId))
			{
				$aApprovals[$oApproval->GetKey()] = $oApproval;
			}
		}
		return $aApprovals;
	}

	/**
	 * API - Approve
	 *
	 * @param $oReplier Main approver or a substitute
	 * @param string $sComment
	 */
	public function Approve($oReplier, $sComment = '')
	{
		$oApprover = $this->FindApprover($oReplier);
		$oSubstitute = ($oApprover->GetKey() == $oReplier->GetKey()) ? null : $oReplier;
		$this->OnAnswer(0, $oApprover, true, $oSubstitute, $sComment);
	}

	/**
	 * API - Reject
	 *
	 * @param $oReplier Main approver or a substitute
	 * @param string $sComment
	 */
	public function Reject($oReplier, $sComment)
	{
		$oApprover = $this->FindApprover($oReplier);
		$oSubstitute = ($oApprover->GetKey() == $oReplier->GetKey()) ? null : $oReplier;
		$this->OnAnswer(0, $oApprover, false, $oSubstitute, $sComment);
	}
}


/**
 * Add the approval status to the object details page, and delete approval schemes when deleting objects
 */
class ApprovalBasePlugin implements iApplicationUIExtension, iApplicationObjectExtension
{
	//////////////////////////////////////////////////
	// Implementation of iApplicationUIExtension
	//////////////////////////////////////////////////

	public function OnDisplayProperties($oObject, WebPage $oPage, $bEditMode = false)
	{
	}

	public function OnDisplayRelations($oObject, WebPage $oPage, $bEditMode = false)
	{
		$sClass = get_class($oObject);
		if (!$this->IsInScope($sClass))
		{
			// skip !
			return;
		}

		$bLastExecFirst = MetaModel::GetModuleSetting('approval-base', 'list_last_first', false);

		$oApprovSearch = DBObjectSearch::FromOQL('SELECT ApprovalScheme WHERE obj_class = :obj_class AND obj_key = :obj_key');
		$oApprovSearch->AllowAllData();
		// Get the approvals (for the current object)
		$oApprovals = new DBObjectSet($oApprovSearch, array('started' => !$bLastExecFirst), array('obj_class' => $sClass, 'obj_key' => $oObject->GetKey()));

		if ($oApprovals->Count() > 0)
		{
			$oPage->SetCurrentTab(Dict::S('Approval:Tab:Title'));

			$oPage->add_style(
<<<EOF
div.approval-exec-label {
	margin-top: 15px;
	margin-bottom: 5px;
	font-weight: bolder;
}
EOF
			);

			if ($oApprovals->Count() > 1)
			{
				$oPage->add_style(
<<<EOF
div.approval-exec-label {
	background: url(../images/minus.gif) no-repeat left;
	cursor: pointer;	
	padding-left: 15px;
}
div.approval-exec-label.status-closed {
	background: url(../images/plus.gif) no-repeat left;
}
div.approval-exec-status {
	border-left: 1px dashed;
	margin-left: 5px;
}
EOF
				);
			}

			while ($oScheme = $oApprovals->Fetch())
			{
				$sId = 'approval-exec-'.$oScheme->GetKey();
				$sLabel = trim($oScheme->Get('label'));
				if ((strlen($sLabel) == 0) && ($oApprovals->Count() > 1))
				{
					// A label is mandatory to have a place to click to toggle, let's give a default one
					$oStarted = new DateTime($oScheme->Get('started'));
					$sLabel = $oStarted->format('Y-m-d H:i');
				}
				if (strlen($sLabel) > 0)
				{
					$oPage->add('<div id="'.$sId.'" class="approval-exec-label">'.$sLabel.'</div>');
				}

				$oPage->add('<div id="'.$sId.'_status" class="approval-exec-status">');
				$oPage->add($oScheme->GetDisplayStatus($oPage));
				$oPage->add('</div>');

				if ($oApprovals->Count() > 1)
				{
					$oPage->add_ready_script("$('#{$sId}').click( function() { $('#{$sId}_status').slideToggle(); } );\n");
					$oPage->add_ready_script("$('#{$sId}').click( function() { $(this).toggleClass('status-closed'); } );\n");
					if ($oScheme->Get('status') != 'ongoing')
					{
						$oPage->add_ready_script("$('#{$sId}_status').slideToggle();");
						$oPage->add_ready_script("$('#{$sId}').toggleClass('status-closed');");
					}
				}
			}
		}
	}

	public function OnFormSubmit($oObject, $sFormPrefix = '')
	{
	}

	public function OnFormCancel($sTempId)
	{
	}

	public function EnumUsedAttributes($oObject)
	{
		return array();
	}

	public function GetIcon($oObject)
	{
		return '';
	}

	public function GetHilightClass($oObject)
	{
		// Possible return values are:
		// HILIGHT_CLASS_CRITICAL, HILIGHT_CLASS_WARNING, HILIGHT_CLASS_OK, HILIGHT_CLASS_NONE	
		return HILIGHT_CLASS_NONE;
	}

	public function EnumAllowedActions(DBObjectSet $oSet)
	{
		// No action
		return array();
	}

	//////////////////////////////////////////////////
	// Implementation of iApplicationObjectExtension
	//////////////////////////////////////////////////

	public function OnIsModified($oObject)
	{
		return false;
	}

	public function OnCheckToWrite($oObject)
	{
	}

	public function OnCheckToDelete($oObject)
	{
	}

	public function OnDBUpdate($oObject, $oChange = null)
	{
		$sReachingState = $oObject->GetState();
		if (!empty($sReachingState))
		{
			$this->OnReachingState($oObject, $sReachingState);
		}
	}

	public function OnDBInsert($oObject, $oChange = null)
	{
		$sReachingState = $oObject->GetState();
		if (!empty($sReachingState))
		{
			$this->OnReachingState($oObject, $sReachingState);
		}
	}

	public function OnDBDelete($oObject, $oChange = null)
	{
		if ($this->IsInScope(get_class($oObject)))
		{
			$oOrphans = DBObjectSearch::FromOQL("SELECT ApprovalScheme WHERE obj_class = '".get_class($oObject)."' AND obj_key = ".$oObject->GetKey());
			$oOrphans->AllowAllData();
			$oSet = new DBObjectSet($oOrphans);
			while ($oScheme = $oSet->Fetch())
			{
				$oScheme->DBDelete();
			}
		}
	}

	//////////////////////////////////////////////////
	// Helpers
	//////////////////////////////////////////////////

	protected function OnReachingState($oObject, $sReachingState)
	{
		foreach(self::EnumApprovalProcesses() as $sApprovClass)
		{
			$aCallSpec = array($sApprovClass, 'GetApprovalScheme');
			if(!is_callable($aCallSpec))
			{
				throw new Exception("Approval plugin: please implement the function GetApprovalScheme");
			}

			// Calling: GetApprovalScheme($oObject, $sReachingState)
			$oApproval = call_user_func($aCallSpec, $oObject, $sReachingState);
			if (!is_null($oApproval))
			{
				// Make sure that there is no ongoing approval for that object
				// (unfortunately the original state value is unknown at this point)
				//
				$oApprovSearch = DBObjectSearch::FromOQL('SELECT ApprovalScheme WHERE status = \'ongoing\' AND obj_class = :obj_class AND obj_key = :obj_key');
				$oApprovSearch->AllowAllData();
				$oApprovals = new DBObjectSet($oApprovSearch, array(), array('obj_class' => get_class($oObject), 'obj_key' => $oObject->GetKey()));
				if ($oApprovals->Count() == 0)
				{
					$oApproval->Set('obj_class', get_class($oObject));
					$oApproval->Set('obj_key', $oObject->GetKey());
					$oApproval->Set('started', $oApproval->Now());
					$oApproval->DBInsert();
	
					$oApproval->StartNextStep();
				}
			}
		}
	}

	public function IsInScope($sClass)
	{
		return true;
	}

	public static function EnumApprovalProcesses()
	{
		static $aProcesses = null;

		if (is_null($aProcesses))
		{
			$aProcesses = MetaModel::EnumChildClasses('ApprovalScheme', ENUM_CHILD_CLASSES_EXCLUDETOP);
		}
		return $aProcesses;
	}
}

/**
 * Hook to trigger the timeout on ongoing approvals
 */
class CheckApprovalTimeout implements iBackgroundProcess
{
	public function GetPeriodicity()
	{	
		return 60; // seconds
	}

	public function Process($iTimeLimit)
	{
		CMDBObject::SetTrackInfo("Automatic timeout");

      $aReport = array();

		$oSet = new DBObjectSet(DBObjectSearch::FromOQL('SELECT ApprovalScheme WHERE status = \'ongoing\' AND timeout <= NOW()'));
		while ((time() < $iTimeLimit) && ($oScheme = $oSet->Fetch()))
		{
			$oScheme->OnTimeout();
			$aReport[] = 'Timeout for approval #'.$oScheme->GetKey();
		}
		
		if (count($aReport) == 0)
		{
			return "No approval has timed out";
		}
		else
		{
			return implode('; ', $aReport);
		}
	}
}


class TriggerOnApprovalRequest extends TriggerOnObject
{
	public static function Init()
	{
		$aParams = array
		(
			"category" => "core/cmdb",
			"key_type" => "autoincrement",
			"name_attcode" => "description",
			"state_attcode" => "",
			"reconc_keys" => array('description'),
			"db_table" => "priv_trigger_onapprovalrequest",
			"db_key_field" => "id",
			"db_finalclass_field" => "",
			"display_template" => "",
		);
		MetaModel::Init_Params($aParams);
		MetaModel::Init_InheritAttributes();

		// Display lists
		MetaModel::Init_SetZListItems('details', array('description', 'target_class', 'filter', 'action_list')); // Attributes to be displayed for the complete details
		MetaModel::Init_SetZListItems('list', array('finalclass', 'target_class', 'description')); // Attributes to be displayed for a list
		// Search
		MetaModel::Init_SetZListItems('standard_search', array('target_class', 'description')); // Criteria of the std search form
	}
}

class ActionEmailApprovalRequest extends ActionEmail
{
	public static function Init()
	{
		$aParams = array
		(
			"category" => "core/cmdb,application",
			"key_type" => "autoincrement",
			"name_attcode" => "name",
			"state_attcode" => "",
			"reconc_keys" => array('name'),
			"db_table" => "priv_action_emailapprovalrequest",
			"db_key_field" => "id",
			"db_finalclass_field" => "",
			"display_template" => "",
		);
		MetaModel::Init_Params($aParams);
		MetaModel::Init_InheritAttributes();

		MetaModel::Init_AddAttribute(new AttributeTemplateString("subject_reminder", array("allowed_values"=>null, "sql"=>"subject_reminder", "default_value"=>null, "is_null_allowed"=>true, "depends_on"=>array())));

		// Display lists
		MetaModel::Init_SetZListItems('details', array('name', 'description', 'status', 'test_recipient', 'cc', 'bcc', 'subject', 'subject_reminder', 'body', 'trigger_list'));
		MetaModel::Init_SetZListItems('list', array('name', 'status', 'subject')); // Attributes to be displayed for a list
		// Search criteria
		MetaModel::Init_SetZListItems('standard_search', array('name','description', 'status', 'subject')); // Criteria of the std search form
	}

	// returns a the list of emails as a string, or a detailed error description
	protected function FindRecipients($sRecipAttCode, $aArgs)
	{
		return parent::FindRecipients($sRecipAttCode, $aArgs);
	}


	public function DoExecute($oTrigger, $aContextArgs)
	{
		if (!array_key_exists('approver->object()', $aContextArgs))
		{
			throw new Exception('Sorry, an action of type "'.MetaModel::GetName(__CLASS__).'" must be triggered by the mean of a trigger of type "'.MetaModel::GetName('TriggerOnApprovalRequest').'"');
		}
		// Hack the current object in memory, so that the email gets correctly prepared
		// by the standard implementation of ActionEmail
		// The current action MUST NOT be saved into the DB!!!
		$oScheme = $aContextArgs['approval_scheme->object()'];
		$oObject = $aContextArgs['this->object()'];
		$oApprover = $aContextArgs['approver->object()'];
		$sTo = 'SELECT '.get_class($oApprover).' WHERE id = '.$oApprover->GetKey();
		$this->Set('to', $sTo);
		$this->Set('from', $oScheme->GetEmailSender($oApprover, $oObject));
		$this->Set('reply_to', $oScheme->GetEmailReplyTo($oApprover, $oObject));
		if (($aContextArgs['message_type'] == 'reminder'))
		{
			$sReminderSubject = trim($this->Get('subject_reminder'));
			if ($this->Get('subject_reminder') !== '')
			{
				$this->Set('subject', $sReminderSubject);
			}
		}
		return parent::DoExecute($oTrigger, $aContextArgs);
	}

	public function ComputeValues()
	{
		// Initialize mandatory value(s)
		$this->Set('from', 'nobody@no.where.org');
		return parent::ComputeValues();
	}

}
