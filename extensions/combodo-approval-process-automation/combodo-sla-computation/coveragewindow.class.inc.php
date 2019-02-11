<?php
// Copyright (C) 2012-2017 Combodo SARL
//
//   This file is part of iTop.
//
//   iTop is free software; you can redistribute it and/or modify
//   it under the terms of the GNU Affero General Public License as published by
//   the Free Software Foundation, either version 3 of the License, or
//   (at your option) any later version.
//
//   iTop is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU Affero General Public License for more details.
//
//   You should have received a copy of the GNU Affero General Public License
//   along with iTop. If not, see <http://www.gnu.org/licenses/>
/**
 * Processing of AJAX calls for the CalendarView
 *
 * @copyright   Copyright (C) 2012-2017 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

/**
 * Open hours definition: start time and end time for each day of the week
 */
class _CoverageWindow_ extends cmdbAbstractObject
{
	protected $aIntervalsPerWeekday; // Local cache to speedup computations
	
	public function __construct($aRow = null, $sClassAlias = '', $aAttToLoad = null, $aExtendedDataSpec = null)
	{
		parent::__construct($aRow, $sClassAlias, $aAttToLoad, $aExtendedDataSpec);
		$this->aIntervalsPerWeekday = null;
	}
	
	public function GetBareProperties(WebPage $oPage, $bEditMode = false, $sPrefix, $aExtraParams = array())
	{
		$aFieldsMap = parent::GetBareProperties($oPage, $bEditMode, $sPrefix, $aExtraParams);
		$oPage->add_linked_stylesheet(utils::GetAbsoluteUrlModulesRoot().'combodo-sla-computation/css/fullcalendar.css?v='.ITOP_BUILD_DATE);
		$oPage->add_linked_script(utils::GetAbsoluteUrlModulesRoot().'combodo-sla-computation/js/fullcalendar.js?v='.ITOP_BUILD_DATE);
		$oPage->add_linked_script(utils::GetAbsoluteUrlModulesRoot().'combodo-sla-computation/js/cwcalendar.js?v='.ITOP_BUILD_DATE);
		$oPage->add('<div style="max-width:800px;"><div style="text-align:center;">'.Dict::S('Class:CoverageWindow/Attribute:interval_list').'</div>');
		$oPage->add('<div id="cwcalendar"></div></div>');
		
		$sInitialDate = '2010-11-01'; // it's a Monday
		$aWeekdaysOffset = array('monday' => 0, 'tuesday' => 1, 'wednesday' => 2, 'thursday' => 3, 'friday' => 4, 'saturday' => 5, 'sunday' => 6);
		
		$oIntervalsSet = $this->get('interval_list');
		$aEvents = array();
		while($oInterval = $oIntervalsSet->Fetch())
		{
			$oDate = new DateTime($sInitialDate, new DateTimeZone('UTC'));
			$iOffset = $aWeekdaysOffset[$oInterval->Get('weekday')];
			if ($iOffset != 0)
			{
				$oDate->modify('+'.$iOffset.' day');
			}
			
			$oStart = clone $oDate;
			preg_match('/^([0-9]+):([0-9]+)$/', $oInterval->Get('start_time'), $aMatches);
			$oStart->setTime((int)$aMatches[1], (int)$aMatches[2], 0);

			$oEnd = clone $oDate;
			preg_match('/^([0-9]+):([0-9]+)$/', $oInterval->Get('end_time'), $aMatches);
			$oEnd->setTime((int)$aMatches[1], (int)$aMatches[2], 0);
			$aEvents[] = array('id' => $oInterval->GetKey(), 'allDay' => false, 'start' => (int)$oStart->format('U'), 'end' => (int)$oEnd->format('U'));
		}
		$aLabels = array(
			'start' => Dict::S('WorkingHoursInterval:StartTime'),
			'end' => Dict::S('WorkingHoursInterval:EndTime'),
			'day_of_the_week' => Dict::S('WorkingHoursInterval:DayOfTheWeek'),
			'whole_day' => Dict::S('WorkingHoursInterval:WholeDay'),
			'ok' => Dict::S('UI:Button:Ok'),
			'cancel' => Dict::S('UI:Button:Cancel'),
			'remove' => Dict::S('WorkingHoursInterval:RemoveIntervalButton'),
			'weekdays' => array(
				Dict::S('DayOfWeek-Sunday'),
				Dict::S('DayOfWeek-Monday'),
				Dict::S('DayOfWeek-Tuesday'),
				Dict::S('DayOfWeek-Wednesday'),
				Dict::S('DayOfWeek-Thursday'),
				Dict::S('DayOfWeek-Friday'),
				Dict::S('DayOfWeek-Saturday')
			),
			'dialog_title' => Dict::S('WorkingHoursInterval:DlgTitle')
		);
		$sJSLabels = json_encode($aLabels);			
		$sJSEvents = json_encode($aEvents);
		$sEditMode = $bEditMode ? 'true' : 'false';
		$oPage->add_ready_script("$('#cwcalendar').cwcalendar({edit_mode: $sEditMode, initial_date: '$sInitialDate', intervals: $sJSEvents, labels: $sJSLabels});");
		$oPage->add('<input type="hidden" name="calendar_json_intervals" id="calendar_json_intervals" value="'.htmlentities(trim($sJSEvents, '"\''), ENT_QUOTES, 'UTF-8').'" />');
		return $aFieldsMap;
	}
	
	public function UpdateObjectFromPostedForm($sFormPrefix = '', $aAttList = null, $sTargetState = '')
	{
		$aErrors = parent::UpdateObjectFromPostedForm($sFormPrefix, $aAttList, $sTargetState);
		
		// Update the list of (related) intervals from the posted JSON string
		$aDays = array(
			0 => 'sunday',
			1 => 'monday',
			2 => 'tuesday',
			3 => 'wednesday',
			4 => 'thursday',
			5 => 'friday',
			6 => 'saturday',
		);
		$aIntervals = json_decode(utils::ReadPostedParam('calendar_json_intervals', '{}', 'raw_data'), true);
		$oSet = $this->Get('interval_list');
		$aExistingIntervals = array();
		while($oInterval = $oSet->Fetch())
		{
			$aExistingIntervals[$oInterval->GetKey()] = $oInterval;
		}
		$aNewIntervals = array();
		foreach($aIntervals as $aIntervalData)
		{
			if (array_key_exists($aIntervalData['id'], $aExistingIntervals))
			{
				// Update an existing interval
				$oInterval = $aExistingIntervals[$aIntervalData['id']];
			}
			else
			{
				$oInterval = new CoverageWindowInterval();
			}
			
			// Check that the interval is not "upside down"
			if ((int)$aIntervalData['start'] > (int)$aIntervalData['end'])
			{
				// Hmm, let's swap start & end
				$iEnd = (int)$aIntervalData['start'];
				$aIntervalData['start'] = (int)$aIntervalData['end'];
				$aIntervalData['end'] = $iEnd;
			}
			
			$iStart = (int)$aIntervalData['start'];
			$oDate = new DateTime('@'.$iStart, new DateTimeZone('UTC'));
			$oInterval->Set('start_time', $oDate->format('H:i'));
			$oInterval->Set('weekday', $aDays[(int)$oDate->format('w')]);
			$iEnd = (int)$aIntervalData['end'];
			$oDate = new DateTime('@'.$iEnd, new DateTimeZone('UTC'));
			$sEndDate = $oDate->format('H:i');
			if ($sEndDate == '00:00')
			{
				$sEndDate = '24:00';
			}
			$oInterval->Set('end_time', $sEndDate);
			$aNewIntervals[] = $oInterval;
		}
			
		$oNewSet = DBObjectSet::FromArray('CoverageWindowInterval', $this->RemoveOverlappingIntervals($aNewIntervals));
		$this->Set('interval_list', $oNewSet);
		
		return $aErrors;
	}
	
	/**
	 * Merge overlapping intervals by updating the existing intervals and discarding the uneeded ones
	 * @param WorkingTimeInterval[] $aIntervals
	 * @return WorkingTimeInterval[]
	 */
	function RemoveOverlappingIntervals($aIntervals)
	{
		// Important: sort the intervals on their start date
		usort($aIntervals, array(__class__, 'SortIntervalOnStartTime'));
		
		$aIntervalsPerDay = array();
		$aResult = array();
		foreach($aIntervals as $oInterval)
		{
			if (!array_key_exists($oInterval->Get('weekday'), $aIntervalsPerDay))
			{
				$aIntervalsPerDay[$oInterval->Get('weekday')] = array();
			}
			
			$bOverlap = false;
			foreach($aIntervalsPerDay[$oInterval->Get('weekday')] as $oPrevInterval)
			{
				if ( (($oInterval->Get('start_time') > $oPrevInterval->Get('start_time')) && ($oInterval->Get('start_time') < $oPrevInterval->Get('end_time'))) ||
					 (($oInterval->Get('end_time') > $oPrevInterval->Get('start_time')) && ($oInterval->Get('end_time') < $oPrevInterval->Get('end_time'))) )
				{
					// The intervals do overlap.
					// Let's merge the two intervals into the already existing 'prev' one instead of adding an extra interval
					if ($oInterval->Get('start_time') < $oPrevInterval->Get('start_time'))
					{
						// Retain the smaller start time
						$oPrevInterval->Set('start_time', $oInterval->Get('start_time'));
					}
					if ($oInterval->Get('end_time') > $oPrevInterval->Get('end_time'))
					{
						// Retain the bigger end time
						$oPrevInterval->Set('end_time', $oInterval->Get('end_time'));
					}
					$bOverlap = true;
					// No other collision is possible since the intervals are sorted on their start time
					// So let's break here
					break;
				}
			}
			
			if (!$bOverlap)
			{
				// No overlap, let's add this interval to the list
				$aIntervalsPerDay[$oInterval->Get('weekday')][] = $oInterval;
			}
		}
		
		// Put back the results in one flat array (not per weekday)
		$aResult = array();
		foreach($aIntervalsPerDay as $weekday => $aDaysIntervals)
		{
			foreach($aDaysIntervals as $oInterval)
			{
				$aResult[] = $oInterval;
			}
		}
		
		return $aResult;
	}

	static function SortIntervalOnStartTime(CoverageWindowInterval $oInterval1, CoverageWindowInterval $oInterval2)
	{
		return ($oInterval1->Get('start_time') > $oInterval2->Get('start_time')) ? +1 : -1;
	}
	
	/**
	 * Convert the old format (decimal) to the new mandatory format NN:NN	
	 */
	public function Get($sAttCode)
	{
		static $sAttToConvert = '|monday_start|monday_end|tuesday_start|tuesday_end|wednesday_start|wednesday_end|thursday_start|thursday_end|friday_start|friday_end|saturday_start|saturday_end|sunday_start|sunday_end|';

		$sValue = parent::Get($sAttCode);
		if (strstr($sAttToConvert, $sAttCode) !== false)
		{
			// The requested attribute is one of the conversion candidates
			if (!preg_match('/'.COVERAGE_TIME_REGEXP.'/', $sValue))
			{
				// The format does not match the new convention
				// => Convert the decimal value into "hh:mm"
				$fTime = (float) $sValue;
				if ($sValue != '')
				{
					$iHour = floor($fTime);
					$iMin = floor(60 * ($fTime - $iHour));
					if ($iHour > 23)
					{
						$sValue = '24:00';
					}
					else
					{
						$sValue = sprintf('%02d:%02d', $iHour, $iMin);
					}
				}
				else
				{
					$sValue = '00:00';
				}
				$this->Set($sAttCode, $sValue); // so that it gets recorded
			}
		}
		return $sValue;
	}

	/**
	 * Whatever the format in DB, Get as a decimal value	
	 */
	public function GetAsDecimal($sAttCode)
	{
		$sTime = $this->Get($sAttCode);
		return self::ToDecimal($sTime);
	}
	
	/**
	 * Convert an hour (as a string) in format hh:ss into a decimal hour (as a float)
	 * @param unknown $sTime
	 * @return number
	 */
	static public function ToDecimal($sTime)
	{
		$iHour = (int) substr($sTime, 0, 2);
		$iMin = (int) substr($sTime, -2);
		$fTime = (float) $iHour + $iMin / 60;
		return $fTime;
	}

	/**
	 * Get the date/time corresponding to a given delay in the future from the present,
	 * considering only the valid (open) hours as specified by the CoverageWindow object and the given
	 * set of Holiday objects.
	 * @param $oHolidaysSet DBObjectSet The list of holidays to take into account
	 * @param $iDuration integer The duration (in seconds) in the future
	 * @param $oStartDate DateTime The starting point for the computation
	 * @return DateTime The date/time for the deadline
	 */
	public function GetDeadline(DBObjectSet $oHolidaysSet, $iDuration, DateTime $oStartDate)
	{
		if (class_exists('WorkingTimeRecorder'))
		{
			WorkingTimeRecorder::Trace(WorkingTimeRecorder::TRACE_DEBUG, __class__.'::'.__function__);
		}
		$aHolidays2 = array();
		while($oHoliday = $oHolidaysSet->Fetch())
		{
			$aHolidays2[$oHoliday->Get('date')] = $oHoliday->GetKey();
		}

		$oCurDate = clone $oStartDate;
		$iCurDuration = 0;
		$idx = 0;
		do
		{
			// Move forward by one interval and check if we meet the expected duration
			$aInterval = $this->GetNextInterval2($oCurDate, $aHolidays2);
			$idx++;
			if ($aInterval != null)
			{
				$iIntervalDuration = $aInterval['end']->format('U') - $aInterval['start']->format('U'); // TODO: adjust for Daylight Saving Time change !
				if ($oStartDate > $aInterval['start'])
				{
					$iIntervalDuration = $iIntervalDuration - ($oStartDate->format('U') - $aInterval['start']->format('U')); // TODO: adjust for Daylight Saving Time change !
				}
				$iCurDuration += $iIntervalDuration;
				$oCurDate = $aInterval['end'];
			}
			else
			{
				$iIntervalDuration = null; // No more interval, means that the interval extends infinitely... (i.e 24*7)
			}
		}
		while( ($iIntervalDuration !== null) && ($iDuration > $iCurDuration) );
		
		$oDeadline = clone $oCurDate;
		$oDeadline->modify( '+'.($iDuration - $iCurDuration).' seconds');			
		return $oDeadline;		
	}

	/**
	 * Helper function to get the date/time corresponding to a given delay in the future from the present,
	 * considering only the valid (open) hours as specified by the CoverageWindow and the given
	 * set of Holiday objects.
	 * @param $oHolidaysSet DBObjectSet The list of holidays to take into account
	 * @param $oStartDate DateTime The starting point for the computation (default = now)
	 * @param $oEndDate DateTime The ending point for the computation (default = now)
	 * @return integer The duration (number of seconds) of open hours elapsed between the two dates
	 */
	public function GetOpenDuration($oHolidaysSet, $oStartDate, $oEndDate)
	{
		if (class_exists('WorkingTimeRecorder'))
		{
			WorkingTimeRecorder::Trace(WorkingTimeRecorder::TRACE_DEBUG, __class__.'::'.__function__);
		}
		$aHolidays2 = array();
		while($oHoliday = $oHolidaysSet->Fetch())
		{
			$aHolidays2[$oHoliday->Get('date')] = $oHoliday->GetKey();
		}

		$oCurDate = clone $oStartDate;
		$iCurDuration = 0;
		$idx = 0;
		do
		{
			// Move forward by one interval and check if we reach the end date
			$aInterval = $this->GetNextInterval2($oCurDate, $aHolidays2);
			if ($aInterval != null)
			{
				if ($aInterval['start']->format('U') > $oEndDate->format('U'))
				{
					// Interval starts after the end of the period, finished
					$oCurDate = clone $aInterval['start'];
				}
				else
				{
					if ($aInterval['start']->format('U') < $oStartDate->format('U'))
					{
						// First interval, starts before the specified period
						$iStart = $oStartDate->format('U');
					}
					else
					{
						// Not the first interval, starts within the specified period
						$iStart = $aInterval['start']->format('U');
					}
					if ($aInterval['end']->format('U') > $oEndDate->format('U'))
					{
						// Last interval, ends after the specified period
						$iEnd = $oEndDate->format('U');
					}
					else
					{
						// Not the last interval, ends within the specified period
						$iEnd = $aInterval['end']->format('U');
					}
					
					$iCurDuration += $iEnd - $iStart;
					$oCurDate = clone $aInterval['end'];
				}
			}
			else
			{
					$oCurDate = clone $oEndDate;
			}
			$idx++;
		}
		while( ($aInterval != null) && ($oCurDate->format('U') < $oEndDate->format('U')));
		return $iCurDuration;		
	}

	/////////////////////////////////////////////////////////////////////////////
		
	/**
	 * Helper to compute GetDeadline and GetOpenDuration	
	 */	
	protected function GetNextInterval2($oStartDate, $aHolidays)
	{
		$oStart = clone $oStartDate;
		$sPHPTimezone = MetaModel::GetConfig()->Get('timezone');
		if ($sPHPTimezone != '')
		{
			$oTZ = new DateTimeZone($sPHPTimezone);
			$oStartDate->SetTimeZone($oTZ); // Needed since the supplied DateTime is (or may be) expressed in UTC timezone and we call ->format('H:i') below
			$oStart->SetTimeZone($oTZ);
		}
		$oStart->SetTime(0, 0, 0);
		
		$oEnd = clone $oStart;
		if ($this->IsHoliday($oStart, $aHolidays))
		{
			// do nothing, start = end: the interval is of no duration... will be skipped

			// Report the holiday
			if (class_exists('WorkingTimeRecorder'))
			{
				$iHoliday = $this->GetHoliday($oStart, $aHolidays);
				$oEndOfTheHoliday = clone $oStart;
				$oEndOfTheHoliday->SetTime(0, 0, 0);
				$oEndOfTheHoliday->modify('+1 day');
				WorkingTimeRecorder::AddInterval($oStart->format('U'), $oEndOfTheHoliday->format('U'), true, 'Holiday', $iHoliday);
			}
		}
		else
		{
			$iWeekDay = $oStart->format('w');
			$aData = $this->GetOpenHours($iWeekDay, $oStartDate->format('H:i'));
			$this->ModifyDate($oStart, $aData['start']);
			$this->ModifyDate($oEnd, $aData['end']);
		}

		if ($oStartDate->format('U') >= $oEnd->format('U'))
		{
			// Next day
			$oStart = clone $oStartDate;
			if ($sPHPTimezone != '')
			{
				$oTZ = new DateTimeZone($sPHPTimezone);
				$oStart->SetTimeZone($oTZ);
			}
			$oStart->SetTime(0, 0, 0);
			$oStart->modify('+1 day');
			$oEnd = clone $oStart;
			if ($this->IsHoliday($oStart, $aHolidays))
			{
				// do nothing, start = end: the interval is of no duration... will be skipped

				// Report the holiday
				if (class_exists('WorkingTimeRecorder'))
				{
					$iHoliday = $this->GetHoliday($oStart, $aHolidays);
					$oEndOfTheHoliday = clone $oStart;
					$oEndOfTheHoliday->SetTime(0, 0, 0);
					$oEndOfTheHoliday->modify('+1 day');
					WorkingTimeRecorder::AddInterval($oStart->format('U'), $oEndOfTheHoliday->format('U'), true, 'Holiday', $iHoliday);
				}
			}
			else
			{
				$oStart = clone $oStartDate;
				if ($sPHPTimezone != '')
				{
					$oTZ = new DateTimeZone($sPHPTimezone);
					$oStart->SetTimeZone($oTZ);
				}
				$oStart->SetTime(0, 0, 0);
				$oStart->modify('+1 day');
				$oEnd = clone $oStart;
				$iWeekDay = $oStart->format('w');
				$aData = $this->GetOpenHours($iWeekDay, '00:00');
				$this->ModifyDate($oStart, $aData['start']);
				$this->ModifyDate($oEnd, $aData['end']);
			}
		}
		if (class_exists('WorkingTimeRecorder'))
		{
			WorkingTimeRecorder::AddInterval($oStart->format('U'), $oEnd->format('U'), false, get_class($this), $this->GetKey());
		}
		return array('start' => $oStart, 'end' => $oEnd);
	}
	
	/**
	 * Modify a date by a (floating point) number of hours (e.g. 11.5 hours for 11 hours and 30 minutes)
	 * @param $oDate DateTime The date to modify
	 * @param $fHours number Number of hours to offset the date
	 */
	protected function ModifyDate(DateTime $oDate, $fHours)
	{
		$iStartHour = floor($fHours);
		if ($iStartHour != $fHours)
		{
			$iStartMinutes = (int)round(($fHours - $iStartHour)*60); // Beware: floor( (12+(10/60) - 12)*60 ) => 9 !! Use round() instead of floor() to avoid the propagation of an error of -3E-14
			$oDate->modify("+ $iStartMinutes minutes");
		}
		$oDate->modify("+ $iStartHour hours");
	}
	
	/**
	 * Get the first interval of open hours which ends after the given time, for the given day of the week
	 * @param int $iDayIndex zero based index for the day of the week (0 = Sunday)
	 * @param string $sTime The time expressed as a string in 24 hours format: hh:mm
	 * @return multitype:number An array like ('start' => 8.5, 'end' => 19.25 )
	 */
	protected function GetOpenHours($iDayIndex, $sTime)
	{
		static $aWeekDayNames = array(0 => 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
				
		$aResult = array(
			'start' => 0,
			'end' => 0,
		);
		
		$sDayName = $aWeekDayNames[$iDayIndex];
		$oMatchingInterval = null;
		
		if ($this->aIntervalsPerWeekday == null)
		{
			$oSet = $this->Get('interval_list');
			$oSet->Rewind();
			while($oInterval = $oSet->Fetch())
			{
				$this->aIntervalsPerWeekday[$oInterval->Get('weekday')][] = $oInterval;
			}
		}
		
		// Let's find the first (earliest) interval which ends after the given time
		$sMinEndTime = '24:00';
		if (array_key_exists($sDayName, $this->aIntervalsPerWeekday))
		{
			foreach($this->aIntervalsPerWeekday[$sDayName] as $oInterval)
			{
				if (($oInterval->Get('end_time') > $sTime) && ($oInterval->Get('end_time') <= $sMinEndTime))
				{
					$oMatchingInterval = $oInterval;
					$sMinEndTime = $oInterval->Get('end_time');
				}
			}
		}
		
		if ($oMatchingInterval)
		{
			$aResult = array(
				'start' => self::ToDecimal($oMatchingInterval->Get('start_time')),
				'end' => self::ToDecimal($oMatchingInterval->Get('end_time')),
			);
		}
		
		return $aResult;
	}
	
	/**
	 * Is the given date a holiday?
	 * @param DateTime $oDate
	 * @param hash $aHolidays
	 * @return boolean
	 */
	protected function IsHoliday(DateTime $oDate, $aHolidays)
	{
		$sDate = $oDate->format('Y-m-d');
		
		if (isset($aHolidays[$sDate]))
		{
			// Holiday found in the calendar
			return true;
		}
		else
		{
			// No such holiday in the calendar
			return false;
		}
	}

	/**
	 * Purpose: for reporting (if present)	
	 */	
	protected function GetHoliday($oDate, $aHolidays)
	{
		$sDate = $oDate->format('Y-m-d');
		
		if (isset($aHolidays[$sDate]))
		{
			// Holiday found in the calendar
			return $aHolidays[$sDate];
		}
		else
		{
			// No such holiday in the calendar
			return null;
		}
	}

	/**
	 * Check if the given date & time is within the open hours of the coverage window
	 * @param DateTime $oCurDate
	 * @param DBObjectSet $oHolidaysSet
	 * @return boolean
	 */
	public function IsInsideCoverage(DateTime $oCurDate, $oHolidaysSet = null)
	{
		if ($oHolidaysSet != null)
		{
			$aHolidays = array();
			while($oHoliday = $oHolidaysSet->Fetch())
			{
				$aHolidays[$oHoliday->Get('date')] = $oHoliday->GetKey();
			}
			// Today's holiday! Not considered inside the coverage
			if ($this->IsHoliday($oCurDate, $aHolidays)) return false;
		}
		
		// compute today's limits for the coverage
		$aData = $this->GetOpenHours($oCurDate->format('w'), $oCurDate->format('H:i'));
		
		$oStart = clone $oCurDate;
		$sPHPTimezone = MetaModel::GetConfig()->Get('timezone');
		if ($sPHPTimezone != '')
		{
			$oTZ = new DateTimeZone($sPHPTimezone);
			$oStart->SetTimeZone($oTZ);
		}
		$oStart->SetTime(0, 0, 0);
		$oEnd = clone $oStart;
		$this->ModifyDate($oStart, $aData['start']);
		$this->ModifyDate($oEnd, $aData['end']);
		
		// Check if the given date is inside the limits
		$iCurDate = $oCurDate->format('U');
		if( ($iCurDate > $oStart->format('U')) && ($iCurDate <= $oEnd->format('U')) ) return true;
		
		// Outside of the coverage
		return false;
	}
}
