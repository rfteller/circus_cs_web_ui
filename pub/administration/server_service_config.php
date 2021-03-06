<?php
require("../common.php");
Auth::checkSession();
Auth::purgeUnlessGranted(AUTH::PROCESS_MANAGE);

//------------------------------------------------------------------------------
// Make one-time ticket
//------------------------------------------------------------------------------
$_SESSION['ticket'] = md5(uniqid().mt_rand());
//------------------------------------------------------------------------------

//------------------------------------------------------------------------------
// Retrieve machine list
//------------------------------------------------------------------------------
$machines = ProcessMachine::select(array(), array('order'=>array('pm_id')));

$machineList = array();
foreach ($machines as $machine)
{
	$machineList[$machine->pm_id] = array(
		'id' => $machine->pm_id,
		'host_name' => $machine->host_name,
		'ip_address' => $machine->ip_address,
		'dicom_storage_server' => $machine->dicom_storage_server,
		'plugin_job_manager' => $machine->plugin_job_manager,
		'process_enabled' => $machine->process_enabled
	);
}

//------------------------------------------------------------------------------

//------------------------------------------------------------------------------
// Settings for Smarty
//------------------------------------------------------------------------------
$smarty = new SmartyEx();

$smarty->assign(array(
	'machineList' => $machineList,
	'storageServerName' => $DICOM_STORAGE_SERVICE,
	'managerServerName' => $PLUGIN_JOB_MANAGER_SERVICE,
	'ticket' => $_SESSION['ticket']
));

$smarty->display('administration/server_service_config.tpl');

