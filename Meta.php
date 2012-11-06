<?php
return array(
	'version' => '2.0.0',
	'autor' => 'shopware AG',
	'copyright' => 'Copyright © 2012, shopware AG',
	'label' => 'SwagMigration',
	'description' => 'Import shop data from various third party shops',
	'support' => 'http://www.forum.shopware.de',
	'link' => 'http://www.shopware.de',
	'changes' =>array(
			'1.3.1'=>array('releasedate'=>'2010-01-18', 'lines' => array(
				'Solves some problems of gambio profile'
			)),
			'1.3.2'=>array('releasedate'=>'2010-01-20', 'lines' => array(
				'Some bug fixes in customer import'
			)),
			'1.3.3'=>array('releasedate'=>'2010-01-21', 'lines' => array(
				'Add fix for errors of long description'
			)),
			'1.3.4'=>array('releasedate'=>'2010-01-24', 'lines' => array(
				'Add better handling for category import',
				'Improved support for large databases'
			)),
			'1.3.5'=>array('releasedate'=>'2010-01-25', 'lines' => array(
				'Fix the problem of long category text',
				'Fix the problem if the country is not available'
			)),
	        '2.0.0'=>array('releasedate'=>'2012-11-10', 'lines' => array(
	      			'Prepared for Shopware 4'
	        ))
		),
	'revision' => '6'
);