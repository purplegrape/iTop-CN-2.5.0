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
 * Module combodo-autoclose-ticket
 *
 * @author      Erwan Taloc <erwan.taloc@combodo.com>
 * @author      Romain Quetiez <romain.quetiez@combodo.com>
 * @author      Denis Flaven <denis.flaven@combodo.com>
 * @license     http://www.opensource.org/licenses/gpl-3.0.html LGPL
 */


class AutoCloseTicket implements iBackgroundProcess
{
	public function GetPeriodicity()
	{	
		return 86400; // Once a day
	}

	public function Process($iTimeLimit)
	{

      		$aReport = array();
		if (MetaModel::IsValidClass('UserRequest'))
		{
			// Get Resolved user request to be closed automatically according to request_closure_delay set for the customer
			$iAutoCloseDelay = MetaModel::GetModuleSetting('combodo-autoclose-ticket', 'userrequest_autoclose_delay', '');
			$oSetUserRequest = new DBObjectSet(DBObjectSearch::FromOQL("SELECT UserRequest AS u WHERE u.status = 'resolved' AND u.resolution_date <= DATE_SUB(NOW(), INTERVAL $iAutoCloseDelay DAY)"));
			$oSetUserRequest->OptimizeColumnLoad(array()); // Optimize the query, we'll do a reload for each ticket to actually process but it's peanuts
			while ((time() < $iTimeLimit) && $oToClose = $oSetUserRequest->Fetch())
			{
				$oToClose->ApplyStimulus('ev_close');
				//$oToEscalate->Set('tto_escalation_deadline', null);
				$oToClose->DBUpdate();
				$aReport['reached Auto close deadline'][] = $oToClose->Get('ref');
			}
		}

		if (MetaModel::IsValidClass('Incident'))
		{
			// Get Resolved incident to be closed automatically according to incident_closure_delay set for the customer
			$iAutoCloseDelay = MetaModel::GetModuleSetting('combodo-autoclose-ticket', 'incident_autoclose_delay', '');
			$oSetIncident = new DBObjectSet(DBObjectSearch::FromOQL("SELECT Incident AS i WHERE i.status = 'resolved' AND i.resolution_date <= DATE_SUB(NOW(), INTERVAL $iAutoCloseDelay DAY)"));
			$oSetIncident->OptimizeColumnLoad(array()); // Optimize the query, we'll do a reload for each ticket to actually process but it's peanuts
			while ((time() < $iTimeLimit) && $oToClose = $oSetIncident->Fetch())
			{
				$oToClose->ApplyStimulus('ev_close');
				//$oToEscalate->Set('tto_escalation_deadline', null);
				$oToClose->DBUpdate();
				$aReport['reached Auto close deadline'][] = $oToClose->Get('ref');
			}
		}
		

		$aStringReport = array();
		foreach ($aReport as $sOperation => $aTicketRefs)
		{
			if (count($aTicketRefs) > 0)
			{
				$aStringReport[] = $sOperation.': '.count($aTicketRefs).' {'.implode(', ', $aTicketRefs).'}';
			}
		}
		if (count($aStringReport) == 0)
		{
			return "No ticket to process";
			echo "No ticket to process";
		}
		else
		{

			return "Some tickets were closed - ".implode('; ', $aStringReport);
			echo "Some tickets were closed - ".implode('; ', $aStringReport);

		}
	}
}


?>
