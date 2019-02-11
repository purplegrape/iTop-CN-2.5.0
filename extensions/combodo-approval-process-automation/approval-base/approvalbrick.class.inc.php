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

namespace Combodo\iTop\Portal\Brick;

// todo: est-ce la bonne méthode pour gérer les includes, où se trouve l'autoload ?
require_once(MODULESROOT.'itop-portal-base/portal/src/entities/abstractbrick.class.inc.php');
require_once(MODULESROOT.'itop-portal-base/portal/src/entities/portalbrick.class.inc.php');

use \Combodo\iTop\DesignElement;
use \Combodo\iTop\Portal\Brick\PortalBrick;
use DOMFormatException;
use DBSearch;
use MetaModel;

/**
 * Description of ApprovalBrick
 * 
 * @author Romain Quetiez
 */
class ApprovalBrick extends PortalBrick
{
	const DEFAULT_HOME_ICON_CLASS = 'fa fa-check-circle';
	const DEFAULT_NAVIGATION_MENU_ICON_CLASS = 'fa fa-check-circle fa-2x';
	const DEFAULT_PAGE_TEMPLATE_PATH = 'approval-base/portal/views/main/layout.html.twig';
	//const DEFAULT_DATA_LOADING = self::ENUM_DATA_LOADING_LAZY;
	//const DEFAULT_LIST_LENGTH = 20;
	const DEFAULT_ZLIST_FIELDS = 'list';

	static $sRouteName = 'p_approval_brick';
	protected $aClasses; // class => array of fields

	public function __construct()
	{
		parent::__construct();

		$this->aClasses = array();
	}

	/**
	 * Returns the target classes to look for
	 *
	 * @return array
	 */
	public function GetClasses()
	{
		return $this->aClasses;
	}

	/**
	 * Helper
	 *
	 * @param string $sClass
	 * @param array $aFields
	 * @return void
	 */
	protected function AddClass($sClass, $aFields)
	{
		if (!in_array($sClass, $this->aClasses))
		{
			$this->aClasses[$sClass]['fields'] = $aFields;
		}

		return $this;
	}

	/**
	 * Load the brick's data from the xml passed as a ModuleDesignElement.
	 * This is used to set all the brick attributes at once.
	 *
	 * @param \Combodo\iTop\DesignElement $oMDElement
	 * @return ApprovalBrick
	 */
	public function LoadFromXml(DesignElement $oMDElement)
	{
		parent::LoadFromXml($oMDElement);

		// Checking specific elements
		foreach ($oMDElement->GetNodes('./*') as $oBrickSubNode)
		{
			switch ($oBrickSubNode->nodeName)
			{
			case 'classes':
				foreach ($oBrickSubNode->GetNodes('./class') as $oClassNode)
				{
					if (!$oClassNode->hasAttribute('id'))
					{
						throw new DOMFormatException('ApprovalBrick: missing id attribute', null, null, $oClassNode);
					}
					$sClass = $oClassNode->getAttribute('id');

					$aFields = array();
					foreach ($oClassNode->GetNodes('./fields/field') as $oFieldNode)
					{
						if (!$oFieldNode->hasAttribute('id'))
						{
							throw new DOMFormatException('ApprovalBrick: missing id attribute', null, null, $oFieldNode);
						}
						$aFields[] = $oFieldNode->getAttribute('id');
					}
					if (empty($aFields))
					{
						$aFields = MetaModel::FlattenZList(MetaModel::GetZListItems($sClass, static::DEFAULT_ZLIST_FIELDS));
					}
					$this->AddClass($sClass, $aFields);
				}
				break;
			}
		}

		return $this;
	}

}

?>