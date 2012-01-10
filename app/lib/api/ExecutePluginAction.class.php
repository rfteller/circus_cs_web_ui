<?php

class ExecutePluginAction extends ApiActionBase
{
	protected static $required_privileges = array(
		Auth::API_EXEC,
		Auth::CAD_EXEC
	);

	public function execute($params)
	{
		try {
			$pdo = DBConnector::getConnection();
			$pdo->beginTransaction();
			$t = true;
			$plugin = Plugin::selectOne(array(
				'plugin_name' => $params['pluginName'],
				'version' => $params['pluginVersion']
			));
			if (!$plugin)
				throw new ApiOperationException('Plugin not found');

			$job_id = Job::registerNewJob(
				$plugin,
				$params['seriesUID'],
				$this->owner->currentUser()->user_id,
				$params['priority'],
				$params['resultPolicy']
			);
			$pdo->commit();
		} catch (Exception $e) {
			if ($t) $pdo->rollBack();
			throw $e;
		}

		$j = new QueryJobAction($this->owner);
		$result = $j->query_job(array($job_id));
		return $result[0];
	}
}