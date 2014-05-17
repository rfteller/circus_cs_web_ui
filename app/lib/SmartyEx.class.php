<?php

global $WEB_UI_ROOT;
require_once($WEB_UI_ROOT . '/app/vendor/Smarty-3.1.18/SmartyBC.class.php');

/**
 * SmartyEx subclasses Smarty, and does CIRCUS-specific initialization.
 * Requires Smarty 3.1.x
 */
class SmartyEx extends SmartyBC
{
	/**
	 * Constructor.
	 * Initializes directories.
	 */
	public function __construct()
	{
		global $BASE_DIR, $DIR_SEPARATOR, $WEB_UI_ROOT;
		parent::__construct();
		$rootPath = $WEB_UI_ROOT . $DIR_SEPARATOR . 'app' . $DIR_SEPARATOR . 'smarty' . $DIR_SEPARATOR;
		$this->template_dir  = $rootPath . 'templates';
		$this->compile_dir   = $rootPath . 'templates_c';
		$this->config_dir    = $rootPath . 'configs';
		$this->cache_dir     = $rootPath . 'cache';
		$this->addPluginsDir($rootPath . 'plugins');

		$this->register_modifier('status_str', array('Job', 'codeToStatusName'));

		$this->assign('currentUser', Auth::currentUser());

		$this->assign('totop', relativeTopDir());
	}
}

