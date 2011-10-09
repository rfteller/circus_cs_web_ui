<?php

require_once("../common.php");
Auth::checkSession();
Auth::purgeUnlessGranted(Auth::SERVER_SETTINGS);

$keys = array(
	array('value' => 'modality'),
	array('value' => 'manufacturer'),
	array('value' => 'model_name'),
	array('value' => 'station_name'),
	array('value' => 'sex'),
	array('value' => 'age'),
	array('value' => 'study_date'),
	array('value' => 'series_date'),
	array('value' => 'body_part'),
	array('value' => 'image_width'),
	array('value' => 'image_height'),
	array('value' => 'study_uid', 'label' => 'study instance UID'),
	array('value' => 'series_description'),
	array('value' => 'image_number', 'label' => 'number of images')
);

// Getting/setting the rulesets is done by Web API.
// See SeriesRulesetAction class.

display();

function display()
{
	global $keys;
	$params = array('toTopDir' => "../");
	$smarty = new SmartyEx();

	$dum = new Plugin();
	$plugin_list = $dum->find(array('type' => 1));
	$plugins = array();
	foreach ($plugin_list as $item)
	{
		$plugins[] = array(
			'id' => $item->plugin_id,
			'name' => $item->fullName()
		);
	}

	$smarty->assign(array(
		'plugins' => $plugins,
		'params' => $params,
		'keys' => $keys
	));
	$smarty->display('administration/series_ruleset_config.tpl');
}


?>