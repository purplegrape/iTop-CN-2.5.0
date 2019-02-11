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
	'Class:MailInboxBase' => '收件箱',
	'Class:MailInboxBase+' => 'Source of incoming eMails',

	'Class:MailInboxBase/Attribute:server' => '邮件服务器',
	'Class:MailInboxBase/Attribute:mailbox' => '收件箱(for IMAP)',
	'Class:MailInboxBase/Attribute:login' => '用户名',
	'Class:MailInboxBase/Attribute:password' => '密码',
	'Class:MailInboxBase/Attribute:protocol' => '协议',
	'Class:MailInboxBase/Attribute:protocol/Value:pop3' => 'POP3',
	'Class:MailInboxBase/Attribute:protocol/Value:imap' => 'IMAP',
	'Class:MailInboxBase/Attribute:port' => '端口',
	'Class:MailInboxBase/Attribute:active' => '启用',
	'Class:MailInboxBase/Attribute:active/Value:yes' => '是',
	'Class:MailInboxBase/Attribute:active/Value:no' => '否',

	'MailInbox:MailboxContent' => '收件箱内容',
	'MailInbox:EmptyMailbox' => '收件箱是空的',
	'MailInbox:Z_DisplayedThereAre_X_Msg_Y_NewInTheMailbox' => '显示了 %1$d 封邮件. 收件箱一共有 %2$d 封邮件(%3$d 封未读).',
	'MailInbox:Status' => '状态',
	'MailInbox:Subject' => '主题',
	'MailInbox:From' => '发件人',
	'MailInbox:Date' => '日期',
	'MailInbox:RelatedTicket' => '相关Ticket',
	'MailInbox:ErrorMessage' => 'Error Message',
	'MailInbox:Status/Processed' => 'Already Processed',
	'MailInbox:Status/New' => 'New',
	'MailInbox:Status/Error' => 'Error',

	'MailInbox:Login/ServerMustBeUnique' => 'The combination Login (%1$s) and Server (%2$s) is already configured for another Mail Inbox.',
	'MailInbox:Login/Server/MailboxMustBeUnique' => 'The combination Login (%1$s), Server (%2$s) and Mailbox (%3$s) is already configured for another Mail Inbox',
	'MailInbox:Display_X_eMailsStartingFrom_Y' => 'Display %1$s eMail(s), starting from %2$s.',
	'MailInbox:WithSelectedDo' => 'With the selected emails: ',
	'MailInbox:ResetStatus' => '重置状态',
	'MailInbox:DeleteMessage' => '删除邮件',

	'Class:TriggerOnMailUpdate' => '触发器 (when updated by mail)',
	'Class:TriggerOnMailUpdate+' => 'Trigger activated when a ticket is updated by processing an incoming email',
));
