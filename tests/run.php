<?php
/**
 * Test entry point:
 *
 *   php tests/run.php [--filter=text] [--file=EmailTest] [--no-isolate]
 *
 * By default every file in tests/cases runs in its own PHP process, so a
 * test that terminates the interpreter (CodeIgniter's show_error() calls
 * exit) is reported by name instead of silently ending the whole run.
 * Run it under every interpreter you support, see tests/run-matrix.sh.
 */
$opt = array('filter' => NULL, 'file' => NULL, 'isolate' => TRUE);
foreach ($argv as $a)
{
	if (strpos($a, '--filter=') === 0)
	{
		$opt['filter'] = substr($a, 9);
	}
	elseif (strpos($a, '--file=') === 0)
	{
		$opt['file'] = substr($a, 7);
	}
	elseif ($a === '--no-isolate')
	{
		$opt['isolate'] = FALSE;
	}
}

$cases = glob(dirname(__FILE__).'/cases/*Test.php');
sort($cases);

// ---- parent: one child process per case file ------------------------

if ($opt['file'] === NULL && $opt['isolate'])
{
	$tot = array('pass' => 0, 'fail' => 0, 'skip' => 0);
	$report = '';
	foreach ($cases as $file)
	{
		$name = basename($file, '.php');
		$cmd = escapeshellarg(PHP_BINARY).' -d xdebug.mode=off -d memory_limit=512M '.escapeshellarg(__FILE__)
			.' --file='.escapeshellarg($name).($opt['filter'] !== NULL ? ' --filter='.escapeshellarg($opt['filter']) : '').' 2>&1';
		$out = array();
		exec($cmd, $out, $code);
		$text = implode("\n", $out);

		if (preg_match('/^RESULT pass=(\d+) fail=(\d+) skip=(\d+)$/m', $text, $m))
		{
			$tot['pass'] += $m[1];
			$tot['fail'] += $m[2];
			$tot['skip'] += $m[3];
			$progress = strtok($text, "\n");
			echo $progress;
			$details = trim(preg_replace('/^RESULT .*$/m', '', substr($text, strlen($progress))));
			if ($details !== '')
			{
				$report .= "--- ".$name." ---\n".$details."\n\n";
			}
		}
		else
		{
			// the child never reached the end of its run
			$tot['fail']++;
			echo 'A';
			$report .= "--- ".$name." ---\nABORT  the process ended before reporting results (exit code ".$code.")\n".substr($text, -800)."\n\n";
		}
	}
	echo "\n\n".$report;
	echo 'PHP '.PHP_VERSION.': '.$tot['pass'].' passed, '.$tot['fail'].' failed, '.$tot['skip'].' skipped'."\n";
	exit($tot['fail'] === 0 ? 0 : 1);
}

// ---- child (or --no-isolate): run the selected case files ------------

require dirname(__FILE__).'/lib/harness.php';

// Parse (and remove) our own options before booting: CodeIgniter's CLI
// router would otherwise treat them as URI segments.
$_SERVER['argv'] = $argv = array($argv[0]);
$_SERVER['argc'] = $argc = 1;

$GLOBALS['__report_boot'] = ($opt['file'] === NULL OR $opt['file'] === 'BootTest');
$GLOBALS['__completed'] = FALSE;
$GLOBALS['__current'] = 'boot';
register_shutdown_function('_test_abort_report');
function _test_abort_report()
{
	if ($GLOBALS['__completed'])
	{
		return;
	}
	$e = error_get_last();
	$buf = '';
	while (ob_get_level() > 0)
	{
		$buf = ob_get_clean().$buf;
	}
	$page = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('#<style.*?</style>#s', '', $buf))));
	echo "\nFAIL  ".$GLOBALS['__current']."\n      ABORT the test ended the process (exit/fatal)"
		.($e ? ': '.$e['message'].' in '.str_replace(ROOT_PATH, '', $e['file']).':'.$e['line'] : '')
		.($page !== '' ? "\n      last output: ".substr($page, -300) : '')."\n";
	echo "RESULT pass=0 fail=1 skip=0\n";
}

require dirname(__FILE__).'/bootstrap.php';

foreach ($cases as $file)
{
	if ($opt['file'] === NULL OR basename($file, '.php') === $opt['file'])
	{
		require $file;
	}
}

exit(run_tests($opt['filter']) ? 0 : 1);
