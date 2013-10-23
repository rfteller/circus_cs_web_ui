<?php

/**
 * Internal interface for 'Preference' page.
 * @author Soichiro Miki <smiki-tky@umin.ac.jp>
 */
class UpdateUserPreferenceAction extends ApiActionBase
{
	public function execute($params)
	{
		if (!is_array($params) || !isset($params['mode'])) return false;
		$method = 'mode_' . $params['mode'];
		if (method_exists($this, $method)) {
			return call_user_func(array($this, $method), $params);
		}
		throw new ApiOperationException('Invalid API call');
	}

	private function getValidatedParams($params, $rules)
	{
		$validator = new FormValidator();
		$validator->addRules($rules);
		if (!$validator->validate($params)) {
			throw new ApiOperationException(implode("\n", $validator->errors));
		}
		return $validator->output;
	}

	private function mode_change_password($params)
	{
		$check = Auth::checkAuth($this->currentUser->user_id, md5($params['oldPassword']));
		if (!$check) throw new ApiOperationException('Current password is incorrect.');
		$this->currentUser->save(array('User' => array(
			'passcode' => md5($params['newPassword'])
		)));
		return array('message' => 'Password was successfully changed.');
	}

	private function mode_change_page_preference($params)
	{
		$params = $this->getValidatedParams($params, array(
			'today_disp' => '![series|cad]',
			'darkroom' => '![t|f]',
			'anonymized' => '![t|f]',
			'show_missed' => '![own|all|none]'
		));

		if (!$this->currentUser->hasPrivilege(Auth::PERSONAL_INFO_VIEW)) {
			$params['anonymized'] = 't';
		}

		$this->currentUser->save(array('User' => $params));

		// TODO: Remove these eventually
		$_SESSION['todayDisp'] = $params['today_disp'];
		$_SESSION['anonymizeFlg'] = ($params['anonymized'] == 't') ? 1 : 0;

		return array('message' => 'User page preference was successfully changed.');
	}

	private function mode_change_darkroom($params)
	{
		$params = $this->getValidatedParams($params, array(
			'darkroom' => '![t|f]',
		));
		$this->currentUser->save(array('User' => $params));
		return null;
	}

	private function mode_get_cad_preference($params, &$plugin = null)
	{
		$params = $this->getValidatedParams($params, array(
			'plugin_name' => 'string', 'version' => 'string'
		));
		$plugin = Plugin::selectOne($params);
		if (!$plugin) {
			throw new ApiOperationException('Specified plugin not found');
		}
		$configs = $plugin->userPreference($this->currentUser);
		return $configs;
	}

	private function mode_set_cad_preference($params)
	{
		$pdo = DBConnector::getConnection();
		$pdo->beginTransaction();
		$pdo->query('LOCK plugin_user_preference');

		$this->mode_get_cad_preference($params, $plugin);
		$plugin_id = $plugin->plugin_id;
		$user_id = $this->currentUser->user_id;

		$prefs = $this->getValidatedParams($params['preferences'], array(
			"sortKey" => '[confidence|location_z|volume_size]',
			"sortOrder" => '[ASC|DESC]',
			"maxDispNum" => 'int'
		));

		$tmp = DBConnector::query(
			'SELECT key, value FROM plugin_user_preference WHERE plugin_id=? AND user_id=?',
			array($plugin_id, $user_id),
			'ALL_ASSOC'
		);
		$old = array();
		foreach ($tmp as $item) $old[$item['key']] = $item['value'];

		$updateSth = $pdo->prepare(
			'UPDATE plugin_user_preference SET value=? ' .
			'WHERE plugin_id=? AND user_id=? AND key=?'
		);
		$insertSth = $pdo->prepare(
			'INSERT INTO plugin_user_preference (plugin_id, user_id, key, value) ' .
			'VALUES(?, ?, ?, ?)'
		);
		foreach ($prefs as $key => $value) {
			if (isset($old[$key])) {
				if ($old[$key] !== $value) {
					$updateSth->execute(array($value, $plugin_id, $user_id, $key));
				}
			} else {
				$insertSth->execute(array($plugin_id, $user_id, $key, $value));
			}
		}
		$pdo->commit();
		return array('message' => 'User preference saved.');
	}
}