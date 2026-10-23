<?php
/**
 * Minimal, dependency-free test harness.
 *
 * Deliberately written in PHP 5.3-compatible syntax (no short arrays, no
 * ::class, no scalar type declarations) so the very same suite can run on
 * every PHP version the framework supports (5.x through 8.x).
 */

class TestFailure extends Exception {}
class TestSkip extends Exception {}

$GLOBALS['__tests'] = array();
$GLOBALS['__diag'] = array();

/**
 * Register a test
 */
function test($name, $callable)
{
	$GLOBALS['__tests'][] = array('name' => $name, 'fn' => $callable);
}

/**
 * Collects every notice/warning/deprecation raised while a test runs.
 * Any of them fails the test: the suite is the proof that the code runs
 * clean on the interpreter in use. E_STRICT is ignored (PHP 5 only noise).
 */
function _test_error_handler($no, $str, $file, $line)
{
	if ($no === 2048) // E_STRICT (the constant itself is deprecated in 8.4)
	{
		return TRUE;
	}
	if ( ! (error_reporting() & $no))
	{
		// suppressed with @ (PHP < 8 sets error_reporting() to 0, PHP 8 keeps only fatal levels)
		return TRUE;
	}
	$GLOBALS['__diag'][] = _test_error_label($no).': '.$str.' in '.str_replace(ROOT_PATH, '', $file).':'.$line;
	return TRUE;
}

function _test_error_label($no)
{
	$map = array(
		E_WARNING => 'Warning', E_NOTICE => 'Notice', E_USER_ERROR => 'User Error',
		E_USER_WARNING => 'User Warning', E_USER_NOTICE => 'User Notice',
		E_DEPRECATED => 'Deprecated', E_USER_DEPRECATED => 'User Deprecated',
		E_RECOVERABLE_ERROR => 'Recoverable Error',
	);
	return isset($map[$no]) ? $map[$no] : 'Error('.$no.')';
}

// ---- assertions -----------------------------------------------------

function fail($msg)
{
	throw new TestFailure($msg);
}

function skip($why)
{
	throw new TestSkip($why);
}

function _t_export($v)
{
	if (is_string($v))
	{
		// binary-safe: never print raw control bytes into the report
		$v = preg_replace('/[^\x20-\x7e\n]/', '.', strlen($v) > 200 ? substr($v, 0, 200).'...' : $v);
		return "'".$v."'";
	}
	return str_replace("\n", ' ', var_export($v, TRUE));
}

function assert_same($expected, $actual, $msg = '')
{
	if ($expected !== $actual)
	{
		fail(($msg ? $msg.': ' : '').'expected '._t_export($expected).' got '._t_export($actual));
	}
}

function assert_equals($expected, $actual, $msg = '')
{
	if ($expected != $actual)
	{
		fail(($msg ? $msg.': ' : '').'expected '._t_export($expected).' got '._t_export($actual));
	}
}

function assert_true($actual, $msg = '')
{
	if ($actual !== TRUE)
	{
		fail(($msg ? $msg.': ' : '').'expected TRUE got '._t_export($actual));
	}
}

function assert_false($actual, $msg = '')
{
	if ($actual !== FALSE)
	{
		fail(($msg ? $msg.': ' : '').'expected FALSE got '._t_export($actual));
	}
}

function assert_contains($needle, $haystack, $msg = '')
{
	if (strpos((string) $haystack, (string) $needle) === FALSE)
	{
		fail(($msg ? $msg.': ' : '').'expected to find '._t_export($needle).' in '._t_export($haystack));
	}
}

function assert_not_contains($needle, $haystack, $msg = '')
{
	if (strpos((string) $haystack, (string) $needle) !== FALSE)
	{
		fail(($msg ? $msg.': ' : '').'expected NOT to find '._t_export($needle).' in '._t_export($haystack));
	}
}

function assert_instance($class, $obj, $msg = '')
{
	if ( ! is_object($obj) OR ! ($obj instanceof $class))
	{
		fail(($msg ? $msg.': ' : '').'expected instance of '.$class.' got '.(is_object($obj) ? get_class($obj) : gettype($obj)));
	}
}

/**
 * Run $fn and assert it raises a PHP error (e.g. what show_error() does is
 * out of scope here; this is for plain exceptions).
 */
function assert_throws($fn, $msg = '')
{
	try
	{
		call_user_func($fn);
	}
	catch (Exception $e)
	{
		return $e;
	}
	fail(($msg ? $msg.': ' : '').'expected an exception');
}

/**
 * Skip unless every listed extension is loaded.
 */
function require_ext()
{
	$args = func_get_args();
	foreach ($args as $ext)
	{
		if ( ! extension_loaded($ext))
		{
			skip('extension '.$ext.' not loaded');
		}
	}
}

// ---- runner ---------------------------------------------------------

function run_tests($filter = NULL)
{
	$pass = $failed = $skipped = 0;
	$failures = array();

	// Anything raised while loading the framework counts as a failure of
	// the pseudo-test "boot" (compile-time deprecations land here).
	if (count($GLOBALS['__boot_diag']) > 0 && ! empty($GLOBALS['__report_boot']))
	{
		$failed++;
		$failures[] = array('boot: framework loads without diagnostics', implode("\n      ", $GLOBALS['__boot_diag']));
		echo "F";
	}

	foreach ($GLOBALS['__tests'] as $t)
	{
		if ($filter !== NULL && stripos($t['name'], $filter) === FALSE)
		{
			continue;
		}

		$GLOBALS['__diag'] = array();
		$GLOBALS['__current'] = $t['name'];
		$status = 'pass';
		$detail = '';
		try
		{
			call_user_func($t['fn']);
		}
		catch (TestSkip $e)
		{
			$status = 'skip';
			$detail = $e->getMessage();
		}
		catch (TestFailure $e)
		{
			$status = 'fail';
			$detail = $e->getMessage();
		}
		catch (Exception $e)
		{
			$status = 'fail';
			$detail = 'uncaught '.get_class($e).': '.$e->getMessage().' @ '.str_replace(ROOT_PATH, '', $e->getFile()).':'.$e->getLine();
		}
		catch (Throwable $e)
		{
			// PHP 7+: Error/TypeError (e.g. removed functions such as each())
			$status = 'fail';
			$detail = 'uncaught '.get_class($e).': '.$e->getMessage().' @ '.str_replace(ROOT_PATH, '', $e->getFile()).':'.$e->getLine();
		}
		if ($status === 'pass' && count($GLOBALS['__diag']) > 0)
		{
			$status = 'fail';
			$detail = "diagnostics:\n      ".implode("\n      ", array_unique($GLOBALS['__diag']));
		}

		if ($status === 'pass')
		{
			$pass++;
			echo '.';
		}
		elseif ($status === 'skip')
		{
			$skipped++;
			echo 'S';
		}
		else
		{
			$failed++;
			echo 'F';
			$failures[] = array($t['name'], $detail);
		}
	}

	echo "\n\n";
	foreach ($failures as $f)
	{
		echo 'FAIL  '.$f[0]."\n      ".$f[1]."\n";
	}
	echo 'RESULT pass='.$pass.' fail='.$failed.' skip='.$skipped."\n";
	$GLOBALS['__completed'] = TRUE;

	return $failed === 0;
}
