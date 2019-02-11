<?php
// Copyright (C) 2013-2017 Combodo SARL
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
 * Module approval-extended
 *
 * @author      Erwan Taloc <erwan.taloc@combodo.com>
 * @author      Romain Quetiez <romain.quetiez@combodo.com>
 * @author      Denis Flaven <denis.flaven@combodo.com>
 * @license     http://www.opensource.org/licenses/gpl-3.0.html LGPL
 */

class ApprovalComputeWorkingHours implements iWorkingTimeComputer
{
	public static function GetDescription()
	{
		return "Compute working hours for Approval rule on UserRequest";
	}

	public function GetDeadline($oObject, $iDuration, DateTime $oStartDate)
	{
		$sCoverageOQL = 'SELECT CoverageWindow AS cw JOIN ApprovalRule AS ar ON ar.coveragewindow_id=cw.id JOIN ServiceSubcategory AS sc ON sc.approvalrule_id = ar.id WHERE sc.id =:this->servicesubcategory_id';
		$oCoverage = null;

		$sHolidaysOQL = MetaModel::GetModuleSetting('combodo-sla-computation', 'holidays_oql', '');
		if ($sHolidaysOQL != '')
		{
			$oHolidaysSet = new DBObjectSet(DBObjectSearch::FromOQL($sHolidaysOQL), array(), array('this' => $oObject));
		}
		else
		{
			$oHolidaysSet = DBObjectSet::FromScratch('Holiday'); // Build an empty set
		}

		if ($sCoverageOQL != '')
		{
			$oCoverageSet = new DBObjectSet(DBObjectSearch::FromOQL($sCoverageOQL), array(), array('this' => $oObject));
		}
		else
		{
			$oCoverageSet = DBObjectSet::FromScratch('CoverageWindow');
		}
		switch($oCoverageSet->Count())
		{
			case 0:
			// No coverage window: 24x7 computation
			$oDeadline = clone $oStartDate;
			$oDeadline->modify( '+'.$iDuration.' seconds');			
			break;
			
			case 1:
			$oCoverage = $oCoverageSet->Fetch();
			$oDeadline = EnhancedSLAComputation::GetDeadlineFromCoverage($oCoverage, $oHolidaysSet, $iDuration, $oStartDate);
			break;
			
			default:
			$oDeadline = null;
			// Several coverage windows found, use the one that gives the stricter deadline
			while($oCoverage = $oCoverageSet->Fetch())
			{
				$oTmpDeadline = EnhancedSLAComputation::GetDeadlineFromCoverage($oCoverage, $oHolidaysSet, $iDuration, $oStartDate);
				// Retain the nearer deadline
				// According to the PHP documentation, the plain comparison operator between DateTime objects
				// (i.e $oTmpDeadline < $oDeadline) is only implemented in PHP 5.2.2
				if ( ($oDeadline == null) || ($oTmpDeadline->format('U') < $oDeadline->format('U')))
				{
					$oDeadline = $oTmpDeadline;
				}			
			}
		}

		return $oDeadline;
	}
	
	public function GetOpenDuration($oObject, DateTime $oStartDate, DateTime $oEndDate)
	{
		$sCoverageOQL = 'SELECT CoverageWindow AS cw JOIN ApprovalRule AS ar ON ar.coveragewindow_id=cw.id JOIN ServiceSubcategory AS sc ON sc.approvalrule_id = ar.id WHERE sc.id =:this->servicesubcategory_id';
		$oCoverage = null;

		$sHolidaysOQL = MetaModel::GetModuleSetting('combodo-sla-computation', 'holidays_oql', '');
		if ($sHolidaysOQL != '')
		{
			$oHolidaysSet = new DBObjectSet(DBObjectSearch::FromOQL($sHolidaysOQL), array(), array('this' => $oObject));
		}
		else
		{
			$oHolidaysSet = DBObjectSet::FromScratch('Holiday'); // Build an empty set
		}

		if ($sCoverageOQL != '')
		{
			$oCoverageSet = new DBObjectSet(DBObjectSearch::FromOQL($sCoverageOQL), array(), array('this' => $oObject));
		}
		else
		{
			$oCoverageSet = DBObjectSet::FromScratch('CoverageWindow');
		}

		switch($oCoverageSet->Count())
		{
			case 0:
			// No coverage window: 24x7 computation.. what about holidays ??
			$iDuration = EnhancedSLAComputation::GetOpenDuration($oObject, $oStartDate, $oEndDate);			
			break;
			
			case 1:
			$oCoverage = $oCoverageSet->Fetch();
			$iDuration = EnhancedSLAComputation::GetOpenDurationFromCoverage($oCoverage, $oHolidaysSet, $oStartDate, $oEndDate);
 	
			break;
			
			default:
			$iDuration = null;
			// Several coverage windows found, use the one that gives the stricter deadline, thus the longer elasped duration
			while($oCoverage = $oCoverageSet->Fetch())
			{
				$iTmpDuration = EnhancedSLAComputation::GetOpenDurationFromCoverage($oCoverage, $oHolidaysSet, $oStartDate, $oEndDate);
				// Retain the longer duration
				if (($iDuration == null) || ($iTmpDuration > $iDuration))
				{
					$iDuration = $iTmpDuration;
				}			
			}
		}
		return $iDuration;
	}
}


class HideButtonsPlugin implements iApplicationUIExtension
{
	public function OnDisplayProperties($oObject, WebPage $oPage, $bEditMode = false)
	{
		if ( (get_class($oObject) == 'UserRequest' ) && ( $oObject->IsNew()) )
		{
			$oSet = new DBObjectSet(new DBObjectSearch('ApprovalRule'));
			$iCount = $oSet->Count();
			if ($iCount > 0)
			{
				$oPage->add_ready_script(
<<<EOF
$('button.action[name="next_action"]').hide();
EOF
				);
			}
		}
	}


	public function OnDisplayRelations($oObject, WebPage $oPage, $bEditMode = false)
	{

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
}

class ApprovalFromUI implements iPopupMenuExtension
{
	/**
	 * Get the list of items to be added to a menu.
	 *
	 * This method is called by the framework for each menu.
	 * The items will be inserted in the menu in the order of the returned array.
	 * @param int $iMenuId The identifier of the type of menu, as listed by the constants MENU_xxx
	 * @param mixed $param Depends on $iMenuId, see the constants defined above
	 * @return object[] An array of ApplicationPopupMenuItem or an empty array if no action is to be added to the menu
	 */
	public static function EnumItems($iMenuId, $param)
	{
		return ApprovalScheme::GetPopMenuItems($iMenuId, $param);
	}
}
