<?php
// Copyright (C) 2010 Combodo SARL
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
//
// Class: CoverageWindow
//

Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	'Menu:CoverageWindows' => '服务时间',
	'Menu:CoverageWindows+' => '服务时间',
	'Class:CoverageWindow' => '服务时间',
	'Class:CoverageWindow+' => '',
	'Class:CoverageWindow/Attribute:name' => '名称',
	'Class:CoverageWindow/Attribute:name+' => '',
	'Class:CoverageWindow/Attribute:description' => '描述',
	'Class:CoverageWindow/Attribute:description+' => '',
	'Class:CoverageWindow/Attribute:friendlyname' => '通用名称',
	'Class:CoverageWindow/Attribute:friendlyname+' => '',
	'Class:CoverageWindow/Attribute:interval_list' => '工作时间',
	'WorkingHoursInterval:StartTime' => '开始时间:',
	'WorkingHoursInterval:EndTime' => '结束时间:',
	'WorkingHoursInterval:WholeDay' => '全天:',
	'WorkingHoursInterval:RemoveIntervalButton' => '移除',
	'WorkingHoursInterval:DlgTitle' => '休息时间版本',
	'Class:CoverageWindowInterval' => '服务时间',
	'Class:CoverageWindowInterval/Attribute:coverage_window_id' => '服务时间窗口',
	'Class:CoverageWindowInterval/Attribute:weekday' => '工作日',
	'Class:CoverageWindowInterval/Attribute:weekday/Value:sunday' => '周日',
	'Class:CoverageWindowInterval/Attribute:weekday/Value:monday' => '周一',
	'Class:CoverageWindowInterval/Attribute:weekday/Value:tuesday' => '周二',
	'Class:CoverageWindowInterval/Attribute:weekday/Value:wednesday' => '周三',
	'Class:CoverageWindowInterval/Attribute:weekday/Value:thursday' => '周四',
	'Class:CoverageWindowInterval/Attribute:weekday/Value:friday' => '周五',
	'Class:CoverageWindowInterval/Attribute:weekday/Value:saturday' => '周六',
	'Class:CoverageWindowInterval/Attribute:start_time' => '开始时间',
	'Class:CoverageWindowInterval/Attribute:end_time' => '结束时间',

));

Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	// Dictionary entries go here
	'Menu:Holidays' => '节假日',
	'Menu:Holidays+' => '全部节假日',
	'Class:Holiday' => '节假日',
	'Class:Holiday+' => '非工作日',
	'Class:Holiday/Attribute:name' => '名称',
	'Class:Holiday/Attribute:date' => '日期',
	'Class:Holiday/Attribute:calendar_id' => '列表',
	'Class:Holiday/Attribute:calendar_id+' => 'The calendar to which this holiday is related (if any)',
	'Coverage:Description' => '描述',
	'Coverage:StartTime' => '开始时间',
	'Coverage:EndTime' => '结束时间',

));


Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	// Dictionary entries go here
	'Menu:HolidayCalendars' => '节假日清单',
	'Menu:HolidayCalendars+' => '节假日清单',
	'Class:HolidayCalendar' => '节假日清单',
	'Class:HolidayCalendar+' => '其他对象可以关联的节假日清单',
	'Class:HolidayCalendar/Attribute:name' => '名称',
	'Class:HolidayCalendar/Attribute:holiday_list' => '节假日',
));
?>
