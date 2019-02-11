<?php
// Copyright (C) 2010-2017 Combodo SARL
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

SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'combodo-sla-computation/2.1.8',
	array(
		// Identification
		//
		'label' => 'Enhanced SLA Computation',
		'category' => 'sla',

		// Setup
		//
		'dependencies' => array(
			'itop-sla-computation/1.0.0',
			'itop-service-mgmt/2.0.0||itop-service-mgmt-provider/2.0.0', // Needed to place new menu entries
		),
		'mandatory' => true,
		'visible' => false,
		'installer' => 'CoverageWindowInstaller',

		// Components
		//
		'datamodel' => array(
			'model.combodo-sla-computation.php',
			'main.combodo-sla-computation.php'
		),
		'webservice' => array(
			
		),
		'data.struct' => array(
			// add your 'structure' definition XML files here,
		),
		'data.sample' => array(
			// add your sample data XML files here,
		),
		
		// Documentation
		//
		'doc.manual_setup' => '', // hyperlink to manual setup documentation, if any
		'doc.more_information' => '', // hyperlink to more information, if any 

		// Default settings
		//
		'settings' => array(
			'coverage_oql' => 'SELECT CoverageWindow', 	// How to retrive the Coverage object for a given ticket (:this)
			'holidays_oql' => 'SELECT Holiday', 	// How to retrive the list of Holidays for a given ticket (:this)
		),
	)
);

if (!class_exists('CoverageWindowInstaller'))
{
	// Module installation handler
	//
	class CoverageWindowInstaller extends ModuleInstallerAPI
	{
		public static function BeforeWritingConfig(Config $oConfiguration)
		{
			// If you want to override/force some configuration values, do it here
			return $oConfiguration;
		}

		/**
		 * Handler called before creating or upgrading the database schema
		 * @param $oConfiguration Config The new configuration of the application
		 * @param $sPreviousVersion string PRevious version number of the module (empty string in case of first install)
		 * @param $sCurrentVersion string Current version number of the module
		 */
		public static function BeforeDatabaseCreation(Config $oConfiguration, $sPreviousVersion, $sCurrentVersion)
		{
			// If you want to migrate data from one format to another, do it here
		}

		/**
		 * Handler called after the creation/update of the database schema
		 * @param $oConfiguration Config The new configuration of the application
		 * @param $sPreviousVersion string Previous version number of the module (empty string in case of first install)
		 * @param $sCurrentVersion string Current version number of the module
		 */
		public static function AfterDatabaseCreation(Config $oConfiguration, $sPreviousVersion, $sCurrentVersion)
		{
			if ($sPreviousVersion != '')
			{
				// Convert the previous format where all data were stored as fields: monday_start, monday_end, tuesday_start...
				// directly inside the CoverageWindow class, to the new format where the open hours are stored as "CoverageWindowInterval" objects
				
				// Check if the "old" column "start_monday" exists. If so, then the data needs to be migrated
				$sTableName = MetaModel::DBGetTable('CoverageWindow');
				
				$aFields = CMDBSource::QueryToArray("SHOW COLUMNS FROM `$sTableName`");
				// Note: without backticks, you get an error with some table names (e.g. "group")
				$bOldColumns = false;
				foreach ($aFields as $aFieldData)
				{
					if ($aFieldData["Field"] == 'monday_start')
					{
						$bOldColumns = true;
						break;
					}
				}
				
				if ($bOldColumns)
				{
					$aCoverageWindows = CMDBSource::QueryToArray("SELECT * FROM `$sTableName`");
					$iCount = 0;

					foreach($aCoverageWindows as $aCW)
					{
						$iId = $aCW['id'];
												
						foreach(array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') as $sWeekday)
						{
							if ($sWeekday == 'wednesday')
							{
								$sStartTime = $aCW['wendnesday_start']; // Arghhh!!!
							}
							else
							{
								$sStartTime = $aCW[$sWeekday.'_start'];
							}
							$sEndTime = $aCW[$sWeekday.'_end'];
							
							if ($sStartTime != $sEndTime)
							{
								// Non-empty interval
								$oInterval = new CoverageWindowInterval();
								$oInterval->Set('coverage_window_id', $iId);
								$oInterval->Set('weekday', $sWeekday);
								$oInterval->Set('start_time', self::FromDecimalIfNeeded($sStartTime));
								$oInterval->Set('end_time',  self::FromDecimalIfNeeded($sEndTime));
								$oInterval->DBInsert();
							}
						}
						$iCount++;
					}
					SetupPage::log_info("Conversion of open hours intervals: $iCount CoverageWindow instance(s) successfully processed.");
					// Be careful 'wendnesday_start !!!
					$sCleanup = "ALTER TABLE `$sTableName` DROP `monday_start`, DROP `monday_end`, DROP `tuesday_start`, DROP `tuesday_end`, DROP `wendnesday_start`, DROP `wednesday_end`, ";
					$sCleanup .= "DROP `thursday_start`, DROP `thursday_end`, DROP `friday_start`, DROP `friday_end`, DROP `saturday_start`, DROP `saturday_end`, DROP `sunday_start`, DROP `sunday_end`";
					CMDBSource::Query($sCleanup);
					
					SetupPage::log_info("CoverageWindow: cleanup of old columns: done.");
				}
			}
		}
		
		/**
		 * Convert (if needed) from the decimal format: e.g. 8.75 => 08:45
		 * Properly formatted strings (hh:mm) are not modified.
		 * @param string $sValue
		 * @return string
		 */
		protected static function FromDecimalIfNeeded($sValue)
		{
			if (!preg_match('/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$|^24:00$/', $sValue))
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
			}
			return $sValue;			
		}
	}
}
