<?php

// Copyright (C) 2010-2016 Combodo SARL
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

namespace Combodo\iTop\Portal\Router;

// todo: est-ce la bonne méthode pour gérer les includes, où se trouve l'autoload ?
require_once(MODULESROOT.'itop-portal-base/portal/src/routers/abstractrouter.class.inc.php');

use Silex\Application;

class ApprovalBrickRouter extends AbstractRouter
{
	static $aRoutes = array(
		array('pattern' => '/approval/{sBrickId}',
			'callback' => 'Combodo\\iTop\\Portal\\Controller\\ApprovalBrickController::DisplayAction',
			'bind' => 'p_approval_brick'
		),
		array('pattern' => '/approval/view/{sObjectClass}/{sObjectId}',
			'callback' => 'Combodo\\iTop\\Portal\\Controller\\ApprovalBrickController::ViewObjectAction',
			'bind' => 'p_approval_view_object'
		),
	);

}

?>