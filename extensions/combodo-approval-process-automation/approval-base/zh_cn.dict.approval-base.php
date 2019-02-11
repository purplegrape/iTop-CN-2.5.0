<?php
// Copyright (C) 2012-2014 Combodo SARL
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
 * Localized data
 *
 * @author      Erwan Taloc <erwan.taloc@combodo.com>
 * @author      Romain Quetiez <romain.quetiez@combodo.com>
 * @author      Denis Flaven <denis.flaven@combodo.com>
 * @license     http://www.opensource.org/licenses/gpl-3.0.html LGPL
 */

Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	'Approval:Tab:Title' => '审核状态',
	'Approval:Tab:Start' => '开始',
	'Approval:Tab:End' => '结束',
	'Approval:Tab:StepEnd-Limit' => '时间限制 (implicit result)',
	'Approval:Tab:StepEnd-Theoretical' => 'Theoretical time limit (duration limited to %1$s mn)',
	'Approval:Tab:StepSumary-Ongoing' => '等待回复',
	'Approval:Tab:StepSumary-OK' => '批准',
	'Approval:Tab:StepSumary-KO' => '已驳回',
	'Approval:Tab:StepSumary-OK-Timeout' => '已批准 (超时)',
	'Approval:Tab:StepSumary-KO-Timeout' => '已驳回 (超时)',
	'Approval:Tab:StepSumary-Idle' => '尚未开始',
	'Approval:Tab:StepSumary-Skipped' => '已跳过',
	'Approval:Tab:End-Abort' => '审批流程在 %2$s 的时候被 %1$s 绕开',

	'Approval:Tab:StepEnd-Condition-FirstReject' => 'This step finishes on the first rejection, or if 100% approved',
	'Approval:Tab:StepEnd-Condition-FirstApprove' => 'This step finishes on the first approval, or if 100% rejected',
	'Approval:Tab:StepEnd-Condition-FirstReply' => 'This step finishes on the first reply',
	'Approval:Tab:Error' => '审批流程发生错误: %1$s',

	'Approval:Comment-Label' => '您的回复意见',
	'Approval:Comment-Tooltip' => '驳回时必填, 批准时可选',
	'Approval:Comment-Mandatory' => '驳回时必须有说明',
	'Approval:Comment-Reused' => '已在第 %1$s 步时作出了回复, 回复是 "%2$s"',
	'Approval:Action-Approve' => '批准',
	'Approval:Action-Reject' => '驳回',
	'Approval:Action-ApproveOrReject' => '批准或驳回',
	'Approval:Action-Abort' => '跳过审批流程',

	'Approval:Form:Title' => '审批',
	'Approval:Form:Ref' => '%1$s 的审批流程',

	'Approval:Form:ApproverDeleted' => 'Sorry, the record corresponding to your identity has been deleted.',
	'Approval:Form:ObjectDeleted' => 'Sorry, the object of the approval has been deleted.',

	'Approval:Form:AnswerGivenBy' => 'Sorry, 流程已被 \'%1$s\' 回复过了', 
	'Approval:Form:AlreadyApproved' => 'Sorry, 流程已经完成: 已批准.',
	'Approval:Form:AlreadyRejected' => 'Sorry, 流程已经完成: 已驳回.',

	'Approval:Form:StepApproved' => 'Sorry, 此阶段已完成，结果是: 已批准. 审批流程将会进入下一阶段...',
	'Approval:Form:StepRejected' => 'Sorry, 此阶段已完成，结果是: 已驳回. 审批流程将会进入下一阶段...',

	'Approval:Abort:Explain' => '您已要求 <b>bypass</b> 审批流程. 这将会停止流程并且不再允许审批者回复.',

	'Approval:Form:AnswerRecorded-Continue' => '您的回复已被记录. 审批流程继续进行.',
	'Approval:Form:AnswerRecorded-Approved' => '您的回复已被记录: 审批流程现已结束,结果是审批通过.',
	'Approval:Form:AnswerRecorded-Rejected' => '您的回复已被记录: 审批流程现已结束,结果是审批被驳回.',

	'Approval:Approved-On-behalf-of' => '被 %1$s 以 %2$s 的身份批准',
	'Approval:Rejected-On-behalf-of' => '被 %1$s 以 %2$s 的身份驳回',
	'Approval:Approved-By' => '被 %1$s 批准',
	'Approval:Rejected-By' => '被 %1$s 驳回',

	'Approval:Ongoing-Title' => '正在进行的审批',
	'Approval:Ongoing-Title+' => '%1$s 的审批流程',
	'Approval:Ongoing-FilterMyApprovals' => '显示需要我审批的项目',
	'Approval:Ongoing-NothingCurrently' => '没有正在进行的审批.',

	'Approval:Remind-Btn' => '发送提醒...',
	'Approval:Remind-DlgTitle' => '发送提醒',
	'Approval:Remind-DlgBody' => '下列联系人将被再次通知:',
	'Approval:ReminderDone' => '已发送提醒给 %1$d 个人.',

	'Approval:Portal:Title' => '需要您审批的项目',
	'Approval:Portal:Title+' => '请选择要审批的项目，并用页面底部的按钮审批',
	'Approval:Portal:NoItem' => '目前暂时没有项目需要审批',
	'Approval:Portal:Btn:Approve' => '批准',
	'Approval:Portal:Btn:Reject' => '驳回',
	'Approval:Portal:CommentTitle' => '审批说明 (驳回时必填)',
	'Approval:Portal:CommentPlaceholder' => '',
	'Approval:Portal:Success' => '您的反馈已被记录.',
	'Approval:Portal:Dlg:Approve' => 'Please confirm that you want to approve <em><span class="approval-count">?</span></em> item(s)',
	'Approval:Portal:Dlg:ApproveOne' => 'Please confirm that you want to approve this item',
	'Approval:Portal:Dlg:Btn:Approve' => '批准!',
	'Approval:Portal:Dlg:Reject' => 'Please confirm that you want to reject <em><span class="approval-count">?</span></em> item(s)',
	'Approval:Portal:Dlg:RejectOne' => 'Please confirm that you want to reject this item',
	'Approval:Portal:Dlg:Btn:Reject' => '驳回!',

	'Class:TriggerOnApprovalRequest' => '触发器 (当需求提出时)',
	'Class:TriggerOnApprovalRequest+' => '批准需求时的触发器',
	'Class:ActionEmailApprovalRequest' => 'Email approval request',
	'Class:ActionEmailApprovalRequest/Attribute:subject_reminder' => '主题 (提醒)',
	'Class:ActionEmailApprovalRequest/Attribute:subject_reminder+' => '要发送的邮件提醒的主题',
));
