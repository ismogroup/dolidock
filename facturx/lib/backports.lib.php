<?php

/**
 * Is Dolibarr module enabled
 *
 * @param string $module module name to check
 * @return int
 */
if (!function_exists('isModEnabled')) {
	function isModEnabled($module)
	{
		global $conf;
		return !empty($conf->$module->enabled);
	}
}
