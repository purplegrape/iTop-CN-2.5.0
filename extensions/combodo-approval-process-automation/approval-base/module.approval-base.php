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

SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'approval-base/2.5.3',
	array(
		// Identification
		//
		'label' => 'Approval prerequisites',
		'category' => 'feature',

		// Setup
		//
		'dependencies' => array(
		),
		'mandatory' => false,
		'visible' => false,
		//'installer' => 'MyInstaller',

		// Components
		//
		'datamodel' => array(
			'main.approval-base.php',
			'model.approval-base.php'
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
			'email_sender' => '',
			'email_reply_to' => '',
			'comment_attcode' => '',
			'list_last_first' => false,
			'enable_reminder' => true,
		),
	)
);
