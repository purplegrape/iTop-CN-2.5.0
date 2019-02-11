<?php
// Copyright (C) 2010-2013 Combodo SARL
//
//   This program is free software; you can redistribute it and/or modify
//   it under the terms of the GNU Lesser General Public License as published by
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

Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	// Dictionary entries go here
	'Class:MailInboxStandard' => '标准收件箱',
	'Class:MailInboxStandard+' => 'Source of incoming eMails',
	'Class:MailInboxStandard/Attribute:behavior' => '动作',
	'Class:MailInboxStandard/Attribute:behavior/Value:create_only' => '新建工单',
	'Class:MailInboxStandard/Attribute:behavior/Value:update_only' => '更新已存在的工单',
	'Class:MailInboxStandard/Attribute:behavior/Value:both' => '新建或更新Ticket',

	'Class:MailInboxStandard/Attribute:email_storage' => '邮件处理完成后',
	'Class:MailInboxStandard/Attribute:email_storage/Value:keep' => '保留在邮件服务器',
	'Class:MailInboxStandard/Attribute:email_storage/Value:delete' => '立即删除',

	'Class:MailInboxStandard/Attribute:target_class' => '工单类别',
	'Class:MailInboxStandard/Attribute:target_class/Value:Incident' => '事件',
	'Class:MailInboxStandard/Attribute:target_class/Value:UserRequest' => '用户需求',

	'Class:MailInboxStandard/Attribute:ticket_default_values' => '工单默认值',
	'Class:MailInboxStandard/Attribute:ticket_default_title' => '默认标题(如果邮件主题为空)',
	'Class:MailInboxStandard/Attribute:title_pattern+' => '邮件主题匹配样式',
	'Class:MailInboxStandard/Attribute:title_pattern' => '标题样式',
	'Class:MailInboxStandard/Attribute:title_pattern?' => '使用PCRE 语法,包括起始分隔符',

	'Class:MailInboxStandard/Attribute:stimuli' => 'Stimuli to apply',
	'Class:MailInboxStandard/Attribute:stimuli+' => 'Apply a stimulus when the ticket is in a given state',
	'Class:MailInboxStandard/Attribute:stimuli?' => 'Use the format <state_code>:<stimulus_code>',

	'Class:MailInboxStandard/Attribute:unknown_caller_behavior' => '发件人未知时的动作',
	'Class:MailInboxStandard/Attribute:unknown_caller_behavior/Value:create_contact' => '添加联系人',
	'Class:MailInboxStandard/Attribute:unknown_caller_behavior/Value:reject_email' => '拒收邮件',

	'Class:MailInboxStandard/Attribute:trace' => '调试跟踪',
	'Class:MailInboxStandard/Attribute:trace/Value:yes' => '是',
	'Class:MailInboxStandard/Attribute:trace/Value:no' => '否',

	'Class:MailInboxStandard/Attribute:import_additional_contacts' => '添加更多联系人(发件人, 抄送人)',
	'Class:MailInboxStandard/Attribute:import_additional_contacts/Value:never' => '从不',
	'Class:MailInboxStandard/Attribute:import_additional_contacts/Value:only_on_creation' => '在新建工单时',
	'Class:MailInboxStandard/Attribute:import_additional_contacts/Value:only_on_update' => '在更新工单时',
	'Class:MailInboxStandard/Attribute:import_additional_contacts/Value:always' => '是',

	'Class:MailInboxStandard/Attribute:caller_default_values' => "新联系人的默认值",
	'Class:MailInboxStandard/Attribute:debug_log' => '调试日志',
	'Class:MailInboxStandard/Attribute:error_behavior' => '动作',
	'Class:MailInboxStandard/Attribute:error_behavior/Value:delete' => '从收件箱删除',
	'Class:MailInboxStandard/Attribute:error_behavior/Value:mark_as_error' => '保留在收件箱',
	'Class:MailInboxStandard/Attribute:notify_errors_to' => '转发邮件到',
	'Class:MailInboxStandard/Attribute:notify_errors_from' => '(发件人)',
	'MailInbox:Server' => '邮箱配置',
	'MailInbox:Behavior' => '收到新邮件时的动作',
	'MailInbox:Caller' => '发件人未知时的动作',
	'MailInbox:Errors' => '邮件错误时的动作',
	'MailInbox:OtherContacts' => '新建联系人时的动作',
	'Menu:MailInboxes' => '收件箱',
	'Menu:MailInboxes+' => '配置收件箱，然后扫描邮件',
	'MailInboxStandard:DebugTrace' => '调试跟踪',
	'MailInboxStandard:DebugTraceNotActive' => 'Activate the debug on this Inbox to see the debug trace here.',
	'MailInbox:NoSubject' => '无主题',
));
