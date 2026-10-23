<?php
/**
 * Boots the real framework (system/core/CodeIgniter.php) with the default
 * Welcome controller, capturing its output, so every test runs against a
 * fully initialised CodeIgniter instance and the boot sequence itself is
 * covered. Anything raised while loading is recorded in $__boot_diag.
 */

define('ROOT_PATH', str_replace('\\', '/', dirname(dirname(__FILE__))).'/');

error_reporting(-1);
// CodeIgniter installs its own error handler while booting and prints PHP
// errors into the output buffer; keep PHP's own display/log out of the way
// and recover the diagnostics from that output below.
ini_set('display_errors', '0');
ini_set('log_errors', '0');

chdir(ROOT_PATH);
date_default_timezone_set('UTC');

$GLOBALS['__boot_diag'] = array();
function _boot_error_handler($no, $str, $file, $line)
{
	if ($no === 2048 OR error_reporting() === 0) // 2048 = E_STRICT (constant deprecated in 8.4)
	{
		return TRUE;
	}
	$GLOBALS['__boot_diag'][] = _test_error_label($no).': '.$str.' in '.str_replace(ROOT_PATH, '', $file).':'.$line;
	return TRUE;
}

// A scratch copy of cache/log dirs so nothing in the repo is written to
define('TEST_TMP', rtrim(sys_get_temp_dir(), '/').'/ci-tests-'.getmypid().'/');
@mkdir(TEST_TMP, 0777, TRUE);
register_shutdown_function('_test_cleanup', TEST_TMP);
function _test_cleanup($dir)
{
	foreach ((array) glob($dir.'*') as $f)
	{
		@unlink($f);
	}
	@rmdir($dir);
}

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/115.0';

define('ENVIRONMENT', 'development');
define('SELF', 'index.php');
define('EXT', '.php');
define('BASEPATH', ROOT_PATH.'system/');
define('FCPATH', ROOT_PATH);
define('SYSDIR', 'system');
define('APPPATH', 'application/');

set_error_handler('_boot_error_handler');

// The buffer stays open for the whole run (CodeIgniter's Loader records the
// buffer level at boot, and an open buffer keeps header() calls from failing
// with "headers already sent" under the CLI). It is flushed at shutdown.
ob_start();
require BASEPATH.'core/CodeIgniter.php';
$GLOBALS['__boot_output'] = ob_get_contents();
ob_clean();

// Errors rendered by CI's own handler (application/errors/error_php.php)
if (preg_match_all('#<p>Severity: (.*?)</p>\s*<p>Message:\s*(.*?)</p>\s*<p>Filename: (.*?)</p>\s*<p>Line Number: (.*?)</p>#s', $GLOBALS['__boot_output'], $m, PREG_SET_ORDER))
{
	foreach ($m as $e)
	{
		$sev = array(8 => 'Notice', 2 => 'Warning', 8192 => 'Deprecated', 1024 => 'Notice', 512 => 'Warning');
		$e[1] = isset($sev[(int) trim($e[1])]) ? $sev[(int) trim($e[1])] : trim($e[1]);
		$GLOBALS['__boot_diag'][] = trim($e[1]).': '.trim($e[2]).' in '.trim($e[3]).':'.trim($e[4]);
	}
}

set_error_handler('_test_error_handler');
