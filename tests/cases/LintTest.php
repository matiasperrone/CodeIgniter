<?php

/**
 * Every PHP file must parse with the running interpreter and compile
 * WITHOUT any diagnostic. Compile-time deprecations (e.g. optional
 * parameters before required ones, PHP 8) show up here.
 */
test('lint: every framework and application file compiles without diagnostics', function ()
{
	$files = array(ROOT_PATH.'index.php');
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_PATH.'system', FilesystemIterator::SKIP_DOTS));
	foreach ($it as $f)
	{
		if (substr($f->getFilename(), -4) === '.php')
		{
			$files[] = $f->getPathname();
		}
	}
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_PATH.'application', FilesystemIterator::SKIP_DOTS));
	foreach ($it as $f)
	{
		if (substr($f->getFilename(), -4) === '.php')
		{
			$files[] = $f->getPathname();
		}
	}
	sort($files);
	assert_true(count($files) > 100, 'found '.count($files).' files');

	$bad = array();
	foreach ($files as $file)
	{
		$out = array();
		$cmd = escapeshellarg(PHP_BINARY).' -n -d error_reporting=-1 -d display_errors=1 -d html_errors=0 -l '.escapeshellarg($file).' 2>&1';
		exec($cmd, $out, $code);
		$text = trim(implode("\n", $out));
		if ($code !== 0 OR strpos($text, 'No syntax errors detected') !== 0 OR strpos($text, "\n") !== FALSE)
		{
			$bad[] = str_replace(ROOT_PATH, '', $file).': '.str_replace("\n", ' | ', $text);
		}
	}
	if (count($bad) > 0)
	{
		fail(count($bad)." file(s) with diagnostics:\n      ".implode("\n      ", array_slice($bad, 0, 40)));
	}
});
