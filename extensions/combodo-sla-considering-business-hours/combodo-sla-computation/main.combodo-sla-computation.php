<?php
// Copyright (C) 2010-2013 Combodo SARL
//


/**
 * Module combodo-sla-computation
 *
 * @author      Erwan Taloc <erwan.taloc@combodo.com>
 * @author      Romain Quetiez <romain.quetiez@combodo.com>
 * @author      Denis Flaven <denis.flaven@combodo.com>
 */

/**
 * Extension to the SLA computation mechanism
 * This class implements a behavior based on:
 * - Open hours for each day of the week
 * - An explicit list of holidays
 */
class EnhancedSLAComputation extends SLAComputationAddOnAPI
{
	/**
	 * Called when the module is loaded, used for one time initialization (if needed)
	 */
	public function Init()
	{
	}	

	/**
	 * Get the date/time corresponding to a given delay in the future from the present,
	 * considering only the valid (open) hours for a specified ticket
	 * @param $oTicket Ticket The ticket for which to compute the deadline
	 * @param $iDuration integer The duration (in seconds) in the future
	 * @param $oStartDate DateTime The starting point for the computation
	 * @return DateTime The date/time for the deadline
	 */
	public static function GetDeadline($oTicket, $iDuration, DateTime $oStartDate)
	{
		if (class_exists('WorkingTimeRecorder'))
		{
			WorkingTimeRecorder::Trace(WorkingTimeRecorder::TRACE_DEBUG, __class__.'::'.__function__);
		}
		$sCoverageOQL = MetaModel::GetModuleSetting('combodo-sla-computation', 'coverage_oql', '');
		$oCoverage = null;

		$sHolidaysOQL = MetaModel::GetModuleSetting('combodo-sla-computation', 'holidays_oql', '');
		if ($sHolidaysOQL != '')
		{
			$oHolidaysSet = new DBObjectSet(DBObjectSearch::FromOQL($sHolidaysOQL), array(), array('this' => $oTicket));
		}
		else
		{
			$oHolidaysSet = DBObjectSet::FromScratch('Holiday'); // Build an empty set
		}

		if ($sCoverageOQL != '')
		{
			$oCoverageSet = new DBObjectSet(DBObjectSearch::FromOQL($sCoverageOQL), array(), array('this' => $oTicket));
		}
		else
		{
			$oCoverageSet = DBObjectSet::FromScratch('CoverageWindow');
		}
		switch($oCoverageSet->Count())
		{
			case 0:
			if (class_exists('WorkingTimeRecorder'))
			{
				WorkingTimeRecorder::Trace(WorkingTimeRecorder::TRACE_INFO, 'No coverage window');
			}
			// No coverage window: 24x7 computation
			$oDeadline = clone $oStartDate;
			$oDeadline->modify( '+'.$iDuration.' seconds');			
			break;
			
			case 1:
			$oCoverage = $oCoverageSet->Fetch();
			$oDeadline = self::GetDeadlineFromCoverage($oCoverage, $oHolidaysSet, $iDuration, $oStartDate);
			break;
			
			default:
			if (class_exists('WorkingTimeRecorder'))
			{
				WorkingTimeRecorder::Trace(WorkingTimeRecorder::TRACE_INFO, 'Several coverage windows: use the one that gives the stricter deadline');
			}
			$oDeadline = null;
			// Several coverage windows found, use the one that gives the stricter deadline
			while($oCoverage = $oCoverageSet->Fetch())
			{
				$oTmpDeadline = self::GetDeadlineFromCoverage($oCoverage, $oHolidaysSet, $iDuration, $oStartDate);
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
	
	/**
	 * Get duration (considering only open hours) elapsed bewteen two given DateTimes
	 * @param $oTicket Ticket The ticket for which to compute the duration
	 * @param $oStartDate DateTime The starting point for the computation (default = now)
	 * @param $oEndDate DateTime The ending point for the computation (default = now)
	 * @return integer The duration (number of seconds) of open hours elapsed between the two dates
	 */
	public static function GetOpenDuration($oTicket, DateTime $oStartDate, DateTime $oEndDate)
	{
		if (class_exists('WorkingTimeRecorder'))
		{
			WorkingTimeRecorder::Trace(WorkingTimeRecorder::TRACE_DEBUG, __class__.'::'.__function__);
		}
		$sCoverageOQL = MetaModel::GetModuleSetting('combodo-sla-computation', 'coverage_oql', '');
		$oCoverage = null;

		$sHolidaysOQL = MetaModel::GetModuleSetting('combodo-sla-computation', 'holidays_oql', '');
		if ($sHolidaysOQL != '')
		{
			$oHolidaysSet = new DBObjectSet(DBObjectSearch::FromOQL($sHolidaysOQL), array(), array('this' => $oTicket));
		}
		else
		{
			$oHolidaysSet = DBObjectSet::FromScratch('Holiday'); // Build an empty set
		}

		if ($sCoverageOQL != '')
		{
			$oCoverageSet = new DBObjectSet(DBObjectSearch::FromOQL($sCoverageOQL), array(), array('this' => $oTicket));
		}
		else
		{
			$oCoverageSet = DBObjectSet::FromScratch('CoverageWindow');
		}
		switch($oCoverageSet->Count())
		{
			case 0:
			if (class_exists('WorkingTimeRecorder'))
			{
				WorkingTimeRecorder::Trace(WorkingTimeRecorder::TRACE_INFO, 'No coverage window');
			}
			// No coverage window: 24x7 computation.. what about holidays ??
			$iDuration = parent::GetOpenDuration($oTicket, $oStartDate, $oEndDate);			
			break;
			
			case 1:
			$oCoverage = $oCoverageSet->Fetch();
			$iDuration = self::GetOpenDurationFromCoverage($oCoverage, $oHolidaysSet, $oStartDate, $oEndDate);		
			break;
			
			default:
			if (class_exists('WorkingTimeRecorder'))
			{
				WorkingTimeRecorder::Trace(WorkingTimeRecorder::TRACE_INFO, 'Several coverage windows: use the one that gives the stricter deadline, thus the longer elapsed duration');
			}
			$iDuration = null;
			// Several coverage windows found, use the one that gives the stricter deadline, thus the longer elasped duration
			while($oCoverage = $oCoverageSet->Fetch())
			{
				$iTmpDuration = self::GetOpenDurationFromCoverage($oCoverage, $oHolidaysSet, $oStartDate, $oEndDate);
				// Retain the longer duration
				if ( ($iDuration == null) || ($iTmpDuration > $iDuration))
				{
					$iDuration = $iTmpDuration;
				}			
			}
		}
		return $iDuration;
	}
	
	/**
	 * Helper function to get the date/time corresponding to a given delay in the future from the present,
	 * considering only the valid (open) hours as specified by the supplied CoverageWindow object and the given
	 * set of Holiday objects.
	 * @param $oCoverage CoverageWindow The coverage window defining the open hours
	 * @param $oHolidaysSet DBObjectSet The list of holidays to take into account
	 * @param $iDuration integer The duration (in seconds) in the future
	 * @param $oStartDate DateTime The starting point for the computation
	 * @return DateTime The date/time for the deadline
	 */
	public static function GetDeadlineFromCoverage(CoverageWindow $oCoverage, DBObjectSet $oHolidaysSet, $iDuration, DateTime $oStartDate)
	{
		if (class_exists('WorkingTimeRecorder'))
		{
			WorkingTimeRecorder::Trace(WorkingTimeRecorder::TRACE_DEBUG, __class__.'::'.__function__);
		}
		if (is_null($oCoverage))
		{
			// 24x7
			$oDeadline = clone $oStartDate;
			$oDeadline->modify( '+'.$iDuration.' seconds');
		}
		else
		{			
			$oDeadline = $oCoverage->GetDeadline($oHolidaysSet, $iDuration, $oStartDate);
		}
		return $oDeadline;
	}

	/**
	 * Helper function to get the date/time corresponding to a given delay in the future from the present,
	 * considering only the valid (open) hours as specified by the supplied CoverageWindow object and the given
	 * set of Holiday objects.
	 * @param $oCoverage CoverageWindow The coverage window defining the open hours
	 * @param $oHolidaysSet DBObjectSet The list of holidays to take into account
	 * @param $oStartDate DateTime The starting point for the computation (default = now)
	 * @param $oEndDate DateTime The ending point for the computation (default = now)
	 * @return integer The duration (number of seconds) of open hours elapsed between the two dates
	 */
	public static function GetOpenDurationFromCoverage($oCoverage, $oHolidaysSet, $oStartDate, $oEndDate)
	{
		if (class_exists('WorkingTimeRecorder'))
		{
			WorkingTimeRecorder::Trace(WorkingTimeRecorder::TRACE_DEBUG, __class__.'::'.__function__);
		}
		if (is_null($oCoverage))
		{
			// 24x7
			return abs($oEndDate->format('U') - $oStartDate->format('U'));
		}
		else
		{			
			return $oCoverage->GetOpenDuration($oHolidaysSet, $oStartDate, $oEndDate);
		}
	}

	public static function IsInsideCoverage($oCurDate, $oCoverage, $oHolidaysSet = null)
	{
		if (is_null($oCoverage))
		{
			// 24x7
			return true;
		}
		else
		{
			return $oCoverage->IsInsideCoverage($oCurDate, $oHolidaysSet);
		}
	}

	protected static function DumpInterval($oStart, $oEnd)
	{
		$iDuration = $oEnd->format('U') - $oStart->format('U');
		echo "<p>Interval: [ ".$oStart->format('Y-m-d H:i:s (D - w)')." ; ".$oEnd->format('Y-m-d H:i:s')." ], duration  $iDuration s</p>";
	}
}

// By default, since this extension is present, let's use it !
SLAComputation::SelectModule('EnhancedSLAComputation');
?>
