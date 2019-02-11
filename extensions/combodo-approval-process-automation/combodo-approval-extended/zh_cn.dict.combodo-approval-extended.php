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
 * Localized data
 *
 * @author      Erwan Taloc <erwan.taloc@combodo.com>
 * @author      Romain Quetiez <romain.quetiez@combodo.com>
 * @author      Denis Flaven <denis.flaven@combodo.com>
 * @license     http://www.opensource.org/licenses/gpl-3.0.html LGPL
 */

Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	// Dictionary entries go here
	'Menu:Ongoing approval' => '等待审批的需求',
	'Menu:Ongoing approval+' => '等待审批的需求',
	'Approbation:PublicObjectDetails' => '<p>亲爱的 $approver->html(friendlyname)$, 麻烦花点时间审批工单 $object->html(ref)$</p>
				      <b>Caller</b>: $object->html(caller_id_friendlyname)$<br>
				      <b>标题</b>: $object->html(title)$<br>
				      <b>服务</b>: $object->html(service_name)$<br>
				      <b>子服务</b>: $object->html(servicesubcategory_name)$<br>
				      <b>描述</b>:<br>
				      $object->html(description)$<br>
				      <b>额外信息</b>:<br>
				      <div>$object->html(service_details)$</div>',
	'Approbation:FormBody' => '<p>亲爱的 $approver->html(friendlyname)$, 请花点时间进行工单审批</p>',
	'Approbation:ApprovalRequested' => '有工单等待您的审批',
	'Approbation:Introduction' => '<p>亲爱的 $approver->html(friendlyname)$, 请花点时间审批工单 $object->html(friendlyname)$</p>',


));

//
// Class: ApprovalRule
//

Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	'Class:ApprovalRule' => '审批规则',
	'Class:ApprovalRule+' => '',
	'Class:ApprovalRule/Attribute:name' => '名称',
	'Class:ApprovalRule/Attribute:name+' => '',
	'Class:ApprovalRule/Attribute:description' => '描述',
	'Class:ApprovalRule/Attribute:description+' => '',
	'Class:ApprovalRule/Attribute:level1_rule' => '1级审批',
	'Class:ApprovalRule/Attribute:level1_rule+' => '',
	'Class:ApprovalRule/Attribute:level1_default_approval' => '如无回复则自动批准',
	'Class:ApprovalRule/Attribute:level1_default_approval+' => '',
	'Class:ApprovalRule/Attribute:level1_default_approval/Value:no' => '否',
	'Class:ApprovalRule/Attribute:level1_default_approval/Value:no+' => '否',
	'Class:ApprovalRule/Attribute:level1_default_approval/Value:yes' => '是',
	'Class:ApprovalRule/Attribute:level1_default_approval/Value:yes+' => '是',
	'Class:ApprovalRule/Attribute:level1_timeout' => '审批超时 (小时)',
	'Class:ApprovalRule/Attribute:level1_timeout+' => '',
	'Class:ApprovalRule/Attribute:level1_exit_condition' => '结束审批的条件',
	'Class:ApprovalRule/Attribute:level1_exit_condition+' => '',
	'Class:ApprovalRule/Attribute:level1_exit_condition/Value:first_reply' => '一旦回复就结束',
	'Class:ApprovalRule/Attribute:level1_exit_condition/Value:first_reply+' => '第一个回复决定1级审批的结果',
	'Class:ApprovalRule/Attribute:level1_exit_condition/Value:first_reject' => '一旦驳回即结束',
	'Class:ApprovalRule/Attribute:level1_exit_condition/Value:first_reject+' => '必须所有人都批准',
	'Class:ApprovalRule/Attribute:level1_exit_condition/Value:first_approve' => '一旦批准就结束',
	'Class:ApprovalRule/Attribute:level1_exit_condition/Value:first_approve+' => '只需有一个人批准即可',
	'Class:ApprovalRule/Attribute:level2_rule' => '2级审批',
	'Class:ApprovalRule/Attribute:level2_rule+' => '',
	'Class:ApprovalRule/Attribute:level2_default_approval' => '如无回复则自动批准',
	'Class:ApprovalRule/Attribute:level2_default_approval+' => '',
	'Class:ApprovalRule/Attribute:level2_default_approval/Value:no' => '否',
	'Class:ApprovalRule/Attribute:level2_default_approval/Value:no+' => '否',
	'Class:ApprovalRule/Attribute:level2_default_approval/Value:yes' => '是',
	'Class:ApprovalRule/Attribute:level2_default_approval/Value:yes+' => '是',
	'Class:ApprovalRule/Attribute:level2_timeout' => '审批超时 (小时)',
	'Class:ApprovalRule/Attribute:level2_timeout+' => '',
	'Class:ApprovalRule/Attribute:level2_exit_condition' => '结束审批的条件',
	'Class:ApprovalRule/Attribute:level2_exit_condition+' => '',
	'Class:ApprovalRule/Attribute:level2_exit_condition/Value:first_reply' => '一旦回复就结束',
	'Class:ApprovalRule/Attribute:level2_exit_condition/Value:first_reply+' => '第一个回复决定2级审批的结果',
	'Class:ApprovalRule/Attribute:level2_exit_condition/Value:first_reject' => '一旦驳回即结束"',
	'Class:ApprovalRule/Attribute:level2_exit_condition/Value:first_reject+' => '必须所有人都批准',
	'Class:ApprovalRule/Attribute:level2_exit_condition/Value:first_approve' => '一旦批准就结束',
	'Class:ApprovalRule/Attribute:level2_exit_condition/Value:first_approve+' => '只需有一个人批准即可',
	'Class:ApprovalRule/Attribute:servicesubcategory_list' => '子服务',
	'Class:ApprovalRule/Attribute:servicesubcategory_list+' => '',
	'Class:ApprovalRule/Attribute:coveragewindow_id' => '服务时间',
	'Class:ApprovalRule/Attribute:coveragewindow_id+' => '',
	'Class:ApprovalRule/Attribute:coveragewindow_name' => '服务时间名称',
	'Class:ApprovalRule/Attribute:coveragewindow_name+' => '',
));

//
// Class: ServiceSubcategory
//

Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	'Class:ServiceSubcategory/Attribute:approvalrule_id' => '审批规则',
	'Class:ServiceSubcategory/Attribute:approvalrule_id+' => '',
	'Class:ServiceSubcategory/Attribute:approvalrule_name' => '审批规则名称',
	'Class:ServiceSubcategory/Attribute:approvalrule_name+' => '',
	'ApprovalRule:baseinfo' => '基本信息',
	'ApprovalRule:Level1' => '1级审批',
	'ApprovalRule:Level2' => '2级审批',
	'Menu:ApprovalRule' => '审批规则',
	'Menu:ApprovalRule+' => '所有的审批规则',

));
