<?php
/**
 * Boots the framework in a fresh process (config overrides come from the
 * CI_TEST_CONFIG environment variable) and reports what happened as JSON.
 * Used by tests that need a boot with different settings.
 */
require dirname(__FILE__).'/../lib/harness.php';
$_SERVER['argv'] = array($argv[0]);
require dirname(__FILE__).'/../bootstrap.php';

echo "\n@@PROBE@@".json_encode(array(
	'welcome'		=> (strpos($GLOBALS['__boot_output'], 'Welcome to CodeIgniter') !== FALSE),
	'probe_class'	=> class_exists('Composer_Probe', FALSE),
	'diagnostics'	=> $GLOBALS['__boot_diag'],
));
