<?php

class ExecutePluginAction extends ApiAction
{
	static $job_status_list = array(
		"PLUGIN_FAILED"        => -1,
		"PLUGIN_NOT_ALLOCATED" =>  1,
		"PLUGIN_ALLOCATED"     =>  2,
		"PLUGIN_PROCESSING"    =>  3,
		"PLUGIN_SUCESSED"      =>  4
	);

	protected $rule;

	protected static $required_privileges = array(
		Auth::API_EXEC,
		Auth::CAD_EXEC
	);


	function requiredPrivileges()
	{
		return self::$required_privileges;
	}


	function execute($api_request)
	{
		$action = $api_request['action'];
		$params = $api_request['params'];

		if(self::check_params($params) == FALSE) {
			throw new ApiException("Invalid parameter.", ApiResponse::STATUS_ERR_OPE);
		}

		// Get rule
		$this->rule = self::check_register($params);
		if (count($this->rule) != count($params['seriesUID'])) {
			throw new ApiException("ruleset matching error.", ApiResponse::STATUS_ERR_SYS);
		}

		$jobID = self::register_job($params);

		$res = new ApiResponse();
		$result = QueryJobAction::query_job(array($jobID));
		$res->setResult($action, $result[0]);

		return $res;
	}


	private function check_params($params)
	{
		$name      = $params['pluginName'];
		$version   = $params['pluginVersion'];
		$seriesUID = $params['seriesUID'];

		if (!isset($name) || !isset($version) || !is_array($seriesUID)) {
			return FALSE;
		}

		return TRUE;
	}


	private function check_register($params)
	{
		$name         = $params['pluginName'];
		$version      = $params['pluginVersion'];
		$seriesUIDArr = $params['seriesUID'];

		// Check plugin exists
		$dummy = new Plugin();
		$plugin = $dummy->find(array('plugin_name' => $name, 'version' => $version));

		if (count($plugin) != 1) {
			throw new ApiException("Plugin('$name'_v'$version') is not found.", ApiResponse::STATUS_ERR_OPE);
		}
		$plugin = $plugin[0];
		if (!$plugin->exec_enabled) {
			throw new ApiException("Plugin(".$plugin->fullName().") is not executable.", ApiResponse::STATUS_ERR_OPE);
		}

		// Get plugin id
		$plugin_id = $plugin->plugin_id;

		// Connect to SQL Server
		$pdo = DBConnector::getConnection();

		// Check ruleset
		$filter = new SeriesFilter();
		$retrule = array();

		for ($vid = 0; $vid < count($seriesUIDArr); $vid++)
		{
			// Get joined series data
			$s = new SeriesJoin();
			$sdata = $s->find(array("series_instance_uid" => $seriesUIDArr[$vid]));
			if (count($sdata) != 1) {
				throw new ApiException("Series not found.", ApiResponse::STATUS_ERR_OPE);
			}
			$series_data = $sdata[0]->getData();

			// Get ruleset
			$sqlStr = "SELECT * FROM plugin_cad_series"
					. " WHERE plugin_id=?"
					. " AND volume_id=?";

			$result = DBConnector::query($sqlStr, array($plugin_id, $vid), 'ALL_ASSOC');
			if (count($result) <= 0) {
				throw new ApiException("ruleset not found.", ApiResponse::STATUS_ERR_OPE);
			}

			foreach ($result as $r)
			{
				$ruleset = $r['ruleset'];
				$rulearr = json_decode($ruleset, true);

				if (is_array($rulearr))
				{
					if (count($rulearr) > 0)
						$ret = $filter->processRuleSets($series_data, $rulearr);
					else
						$ret = array('start_img_num' => 0, 'end_img_num' => 0, 'required_private_tags' => '');
				}
				else
					$ret = null;

				if ($ret) {
					$retrule[] = $ret;
				} else {
					break;
					//$retrule = false;
				}
			}
		}

		return $retrule;
	}


	private function register_job($plugin)
	{
		//------------------------------------------------------------------------------------------------------------------
		// Import request variables
		//------------------------------------------------------------------------------------------------------------------
		$seriesUIDArr = $plugin['seriesUID'];
		$cadName      = $plugin['pluginName'];
		$version      = $plugin['pluginVersion'];
		$priority     = $plugin['priority'];
		$resultPolicy = $plugin['resultPolicy'];

		if (!isset($priority)) {
			$priority = 1;
		}
		if (!isset($resultPolicy)) {
			$resultPolicy = "default";
		}

		$seriesNum = count($seriesUIDArr);
		$userID = ApiExec::currentUser()->user_id;

		$dstData = array('message'      => "",
				         'registeredAt' => date("Y-m-d H:i:s"),
				         'executedAt'   => "");
		$sidArr = array();
		//------------------------------------------------------------------------------------------------------------------

		// Connect to SQL Server
		$pdo = DBConnector::getConnection();

		// Get plugin ID
		$sqlStr = "SELECT plugin_id FROM plugin_master WHERE plugin_name=? AND version=?";
		$pluginID = DBConnector::query($sqlStr, array($cadName, $version), 'SCALAR');

		// Get series sid
		$sqlStr = "SELECT sl.series_sid, sl.series_description"
				. " FROM series_join_list sl"
				. " WHERE sl.series_instance_uid=?";

		foreach($seriesUIDArr as $item)
		{
			$sidArr[] = DBConnector::query($sqlStr, $item, 'ARRAY_NUM');
		}

		// Get current storage ID for plugin result
		$sqlStr = "SELECT storage_id FROM storage_master WHERE type=2 AND current_use='t'";
		$storageID =  DBConnector::query($sqlStr, NULL, 'SCALAR');

		// jobID duplication check
		$colArr = array();

		$sqlStr = "SELECT * FROM executed_plugin_list el, executed_series_list es"
				. " WHERE el.plugin_id=? AND el.job_id=es.job_id AND el.status>0"
				. " AND (";

		$colArr[] = $pluginID;

		for($i = 0; $i < count($seriesUIDArr); $i++)
		{
			if($i > 0)  $sqlStr .= " OR ";

			$sqlStr .= "(es.volume_id=? AND es.series_sid=?)";

			$colArr[] = $i;
			$colArr[] = $sidArr[$i][0];
		}
		$sqlStr .= ");";

		$stmt = $pdo->prepare($sqlStr);
		$stmt->execute($colArr);

		if($stmt->rowCount() == $seriesNum)
		{
			$result = $stmt->fetch(PDO::FETCH_ASSOC);

			// job status check
			if($result['status'] != self::$job_status_list['PLUGIN_SUCESSED'])
			{
				throw new ApiException("Already registered", ApiResponse::STATUS_ERR_SYS);
			}
			else
			{
				throw new ApiException("Already executed", ApiResponse::STATUS_ERR_SYS);
			}
			$dsaData['executedAt'] = $result['executed_at'];
		}

		if($dstData['message'] == "")
		{
			try
			{
				//---------------------------------------------------------------------------------------------------------
				// Begin transaction
				//---------------------------------------------------------------------------------------------------------
				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				$pdo->beginTransaction();
				//---------------------------------------------------------------------------------------------------------

				// Get new job ID
				$sqlStr= "SELECT nextval('executed_plugin_list_job_id_seq')";
				$jobID =  DBConnector::query($sqlStr, NULL, 'SCALAR');

				// Get policy ID
				$sqlStr = "SELECT policy_id FROM plugin_result_policy"
						. " WHERE policy_name = ?";
				$policyID = DBConnector::query($sqlStr, array($resultPolicy), 'SCALAR');

				// Register into "execxuted_plugin_list"
				$sqlStr = "INSERT INTO executed_plugin_list"
						. " (job_id, plugin_id, storage_id, policy_id, status, exec_user,"
						. " registered_at, started_at, executed_at)"
						. " VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)";
				$sqlParams = array($jobID,
								$pluginID,
								$storageID,
								$policyID,
								$userID,
								$dstData['registeredAt'],
								$dstData['registeredAt'],
								$dstData['registeredAt']);
				$stmt = $pdo->prepare($sqlStr);
				$stmt->execute($sqlParams);

				// Register into "job_queue"
				$sqlStr = "INSERT INTO job_queue"
						. " (job_id, plugin_id, priority, status, exec_user, registered_at, updated_at)"
						. " VALUES (?, ?, ?, 1, ?, ?, ?)";
				$sqlParams = array($jobID,
								$pluginID,
								$priority,
								$userID,
								$dstData['registeredAt'],
								$dstData['registeredAt']);
				$stmt = $pdo->prepare($sqlStr);
				$stmt->execute($sqlParams);

				// Register into executed_series_list and job_queue_series
				for($i=0; $i<$seriesNum; $i++)
				{
					$sqlParams = array($jobID, $i, $sidArr[$i][0]);

					$sqlStr = "INSERT INTO executed_series_list(job_id, volume_id, series_sid)"
							. " VALUES (?, ?, ?)";
					$stmt = $pdo->prepare($sqlStr);
					$stmt->execute($sqlParams);

					// Match plug-in cad series
					$sqlStr = "INSERT INTO job_queue_series"
							. " (job_id, volume_id, series_sid, start_img_num, end_img_num, required_private_tags)"
							. " VALUES (?, ?, ?, ?, ?, ?)";

					$sqlParams = array($jobID, $i, $sidArr[$i][0]);
					$sqlParams[] = $this->rule[$i]['start_img_num'];
					$sqlParams[] = $this->rule[$i]['end_img_num'];
					$sqlParams[] = $this->rule[$i]['required_private_tags'];

					$stmt = $pdo->prepare($sqlStr);
					$stmt->execute($sqlParams);
				}
				//---------------------------------------------------------------------------------------------------------
				// Commit transaction
				//---------------------------------------------------------------------------------------------------------
				$pdo->commit();
				//---------------------------------------------------------------------------------------------------------

				$dstData['message'] = 'Successfully registered plug-in job';
			}
			catch (PDOException $e)
			{
				$pdo->rollBack();

				throw new ApiException("Fail to register plug-in job", ApiResponse::STATUS_ERR_SYS);
			}
		}

		$pdo = null;

		return $jobID;
	}
}

?>
