<?php

/** Boot the framework in a child process with the given config overrides */
function _boot_with($config)
{
	$env = 'CI_TEST_CONFIG='.escapeshellarg(json_encode($config));
	$cmd = $env.' '.escapeshellarg(PHP_BINARY).' -d xdebug.mode=off '.escapeshellarg(ROOT_PATH.'tests/support/boot_probe.php').' 2>&1';
	exec($cmd, $out, $code);
	$text = implode("\n", $out);
	$probe = NULL;
	if (($pos = strpos($text, '@@PROBE@@')) !== FALSE)
	{
		$probe = json_decode(substr($text, $pos + 9), TRUE);
	}
	return array('probe' => $probe, 'text' => $text, 'code' => $code);
}

function _fake_vendor()
{
	$dir = TEST_TMP.'vendor/';
	@mkdir($dir);
	file_put_contents($dir.'autoload.php', "<?php\nclass Composer_Probe { public static function ok() { return 'loaded'; } }\n");
	return $dir.'autoload.php';
}

test('composer: autoloading is off by default', function ()
{
	assert_false(get_instance()->config->item('composer_autoload'));
	$r = _boot_with(array());
	assert_true(is_array($r['probe']), $r['text']);
	assert_true($r['probe']['welcome']);
	assert_false($r['probe']['probe_class']);
});

test('composer: a custom auto-loader path is required during boot', function ()
{
	$r = _boot_with(array('composer_autoload' => _fake_vendor()));
	assert_true(is_array($r['probe']), $r['text']);
	assert_true($r['probe']['welcome'], 'the page still renders');
	assert_true($r['probe']['probe_class'], 'the auto-loader was included');
	assert_same(array(), $r['probe']['diagnostics']);
});

test('composer: an unreadable path stops the boot with an explanation', function ()
{
	$r = _boot_with(array('composer_autoload' => TEST_TMP.'nowhere/autoload.php'));
	assert_contains('Composer auto-loader was not found', $r['text']);
	assert_false(is_array($r['probe']));
});

test('composer: TRUE looks for vendor/autoload.php next to index.php', function ()
{
	if (file_exists(ROOT_PATH.'vendor/autoload.php'))
	{
		skip('a real vendor/ directory exists in the project root');
	}
	$r = _boot_with(array('composer_autoload' => TRUE));
	assert_contains('Composer auto-loader was not found', $r['text']);
	assert_contains('vendor/autoload.php', $r['text']);
});

test('composer: composer.json is valid and declares the supported PHP range', function ()
{
	$json = json_decode(file_get_contents(ROOT_PATH.'composer.json'), TRUE);
	assert_true(is_array($json), 'composer.json must be valid JSON');
	assert_same('codeigniter/framework', $json['name']);
	assert_same('project', $json['type']);
	assert_same('>=5.6', $json['require']['php']);
	assert_true(isset($json['scripts']['test']));
});
