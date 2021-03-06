<?php

require_once('../common.php');
Auth::checkSession();
Auth::purgeUnlessGranted(Auth::SERVER_SETTINGS);

$message = '';

$fields = array(
	'policy_name',
	'allow_result_reference',
	'allow_personal_fb',
	'allow_consensual_fb',
	'time_to_freeze_personal_fb',
	'max_personal_fb',
	'min_personal_fb_to_make_consensus',
	'automatic_consensus'
);

try {
	//--------------------------------------------------------------------------
	// Import $_REQUEST variables
	//--------------------------------------------------------------------------
	$validator = new FormValidator();
	$validator->addRules(array(
		'ticket' => array('type' => 'string'),
		'mode' => array(
			'type' => 'select',
			'options' => array('set','delete')
		),
		'target' => array('type' => 'int'),
		'policy_name' => array(
			'type' => 'string',
			'regex' => '/^[_A-Za-z0-9][\-_A-Za-z0-9]*$/',
			'errorMes' => 'Invalid policy name. Use only alphabets and numerals.'
		),
		'allow_result_reference' => array('type' => 'array'),
		'allow_personal_fb' => array('type' => 'array'),
		'allow_consensual_fb' => array('type' => 'array'),
		'time_to_freeze_personal_fb' => array('type' => 'int', 'min' => 0),
		'max_personal_fb' => array('type' => 'int', 'min' => 0),
		'min_personal_fb_to_make_consensus' => array('type' => 'int', 'min' => 0),
		'automatic_consensus' => array(
			'type' => 'int',
			'min' => 1,
			'max' => 1,
			'default' => 0
		)
	));

	if ($validator->validate($_POST))
	{
		$req = $validator->output;
		$req['allow_result_reference'] = implode(',', $req['allow_result_reference']);
		$req['allow_personal_fb'] = implode(',', $req['allow_personal_fb']);
		$req['allow_consensual_fb'] = implode(',', $req['allow_consensual_fb']);
	}
	else
		throw new Exception(implode(' ', $validator->errors));

	if ($req['mode'] && $req['ticket'] != $_SESSION['ticket'])
		throw new Exception('Invalid page transition detected. Try again.');

	if ($req['mode'] == 'set')
	{
		if ($req['target']) {
			$pol = new PluginResultPolicy($req['target']);
			if (!isset($pol->policy_id))
			{
				throw new Exception('Target policy does not exist.');
			}
			$is_default_policy = $pol->policy_name == PluginResultPolicy::DEFAULT_POLICY;
		}
		else
		{
			$pol = new PluginResultPolicy();
		}
		$data = array('PluginResultPolicy' => array());
		if ($is_default_policy && $req['policy_name'] != PluginResultPolicy::DEFAULT_POLICY)
		{
			throw new Exception('You cannot edit the name of the default policy.');
		}
		foreach ($fields as $column)
		{
			$data['PluginResultPolicy'][$column] = $req[$column];
		}
		$pol->save($data);
		$message = 'Policy "' . $pol->policy_name . '" updated.';
	}

	if ($_POST['mode'] == 'delete')
	{
		$pol = new PluginResultPolicy($req['target']);
		if (!isset($pol->policy_id))
		{
			throw new Exception('Target policy does not exist.');
		}
		if($pol->policy_name == PluginResultPolicy::DEFAULT_POLICY)
		{
			throw new Exception('You cannot delete the default policy.');
		}
		$pol->delete($pol->policy_id);
		$message = 'Policy "' . $pol->policy_name . '" deleted.';
	}
}
catch (Exception $e)
{
	$message = $e->getMessage();
}

//------------------------------------------------------------------------------
// Make one-time ticket
//------------------------------------------------------------------------------
$_SESSION['ticket'] = md5(uniqid().mt_rand());
//------------------------------------------------------------------------------

//------------------------------------------------------------------------------
// Retrieve policy lists
//------------------------------------------------------------------------------
$pols = PluginResultPolicy::select(array(), array('order' => array('policy_name'))); // fetch all policies
$policyList = array();
foreach ($pols as $pol)
{
	$item = array();
	$pol_id = $pol->policy_id;
	$item['policy_id'] = $pol_id;
	$item['delete_btn_flg'] = false;
	foreach ($fields as $column) $item[$column] = $pol->$column;

	// Set flag for enable/disable [delete] button
	$sqlStr = "SELECT COUNT(*) FROM executed_plugin_list"
			. " WHERE policy_id=?";

	if($item['policy_name'] != PluginResultPolicy::DEFAULT_POLICY)
	{
		$item['delete_btn_flg'] = (DBConnector::query($sqlStr, array($pol_id), 'SCALAR') == 0);
	}

	$policyList[$pol_id] = $item;
}

$gps = Group::select(array(), array('order' => array('group_id')));
$groups = array();
foreach ($gps as $grp)
	$groups[] = $grp->group_id;

$smarty = new SmartyEx();
$smarty->assign(array(
	'message' => $message,
	'ticket' => $_SESSION['ticket'],
	'groups' => $groups,
	'policyList' => $policyList
));
$smarty->display('administration/result_policy_config.tpl');
exit();

function parseUserList($input)
{
	$tokens = preg_split('/\\,\\s*/', $input);
	$users = array();
	$groups = array();
	$username_rgx = '[a-zA-Z_][a-zA-Z_0-9]*';
	foreach ($tokens as $token)
	{
		$token = trim($token);
		if (preg_match("/^($username_rgx)$/", $token, $match))
			$users[] = $match[1];
		if (preg_match("/^\[\s*($username_rgx)\s*\]", $token, $match))
			$groups[] = $match[1];
	}
	return array ('users' => $users, 'groups' => $groups);
}

