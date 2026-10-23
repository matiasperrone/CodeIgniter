<?php

/**
 * Behaviour introduced or fixed by the PHP 8 migration. Everything here
 * must hold on every supported PHP version.
 */

/** A response/value shape the XML-RPC parser reliably round-trips */
function _mig_struct($word)
{
	return new XML_RPC_Values(array(
		'name'  => new XML_RPC_Values($word, 'string'),
		'age'   => new XML_RPC_Values(36, 'int'),
		'langs' => new XML_RPC_Values(array(new XML_RPC_Values('en', 'string'), new XML_RPC_Values('fr', 'string')), 'array'),
	), 'struct');
}

function _new_encrypt($driver, $cipher = NULL, $mode = NULL)
{
	require_once BASEPATH.'libraries/Encrypt.php';
	$c = get_instance()->config;
	$old = $c->item('encryption_driver');
	$c->set_item('encryption_driver', $driver);
	$e = new CI_Encrypt();
	$c->set_item('encryption_driver', $old);
	if ($cipher !== NULL)
	{
		$e->set_cipher($cipher);
		$e->set_mode($mode);
	}
	return $e;
}

test('compat: random_bytes returns the requested number of random bytes', function ()
{
	assert_true(function_exists('random_bytes'));
	foreach (array(1, 16, 100) as $n)
	{
		$b = random_bytes($n);
		assert_true(is_string($b));
		assert_same($n, strlen($b));
	}
	assert_true(random_bytes(32) !== random_bytes(32));
});

test('compat: the polyfill file can be loaded twice without redeclaring', function ()
{
	require BASEPATH.'core/compat/random_bytes.php';
	assert_same(8, strlen(random_bytes(8)));
});

test('xmlrpc: iso8601_encode produces a well-formed timestamp', function ()
{
	get_instance()->load->library('xmlrpc');
	$v = new XML_RPC_Values(0, 'string');
	assert_same('20231114T22:13:20', $v->iso8601_encode(1700000000));
	assert_same('20231114T22:13:20', $v->iso8601_encode(1700000000, 1));
	assert_same('20000101T00:00:00', $v->iso8601_encode(946684800, 0));
});

test('xmlrpc: debug output of a parsed response does not fail', function ()
{
	get_instance()->load->library('xmlrpc');
	$resp = new XML_RPC_Response(_mig_struct('ok'));
	$xml = $resp->prepare_response();
	$fp = fopen('php://temp', 'r+');
	fwrite($fp, "HTTP/1.1 200 OK\r\nContent-Type: text/xml\r\nContent-Length: ".strlen($xml)."\r\n\r\n".$xml);
	rewind($fp);
	$msg = new XML_RPC_Message('x');
	$msg->debug = TRUE;
	ob_start();
	$parsed = $msg->parseResponse($fp);
	$out = ob_get_clean();
	assert_contains('---DATA---', $out);
	assert_contains('---HEADERS---', $out);
	assert_equals(0, $parsed->faultCode());
	$d = $parsed->decode();
	assert_same('ok', $d['name']);
});

test('xmlrpc: two parsers can be used one after the other', function ()
{
	get_instance()->load->library('xmlrpc');
	foreach (array('first', 'second', 'third') as $word)
	{
		$resp = new XML_RPC_Response(_mig_struct($word));
		$xml = $resp->prepare_response();
		$fp = fopen('php://temp', 'r+');
		fwrite($fp, "HTTP/1.1 200 OK\r\nContent-Type: text/xml\r\nContent-Length: ".strlen($xml)."\r\n\r\n".$xml);
		rewind($fp);
		$m = new XML_RPC_Message('x');
		$d = $m->parseResponse($fp)->decode();
		assert_same($word, $d['name']);
	}
});

test('encrypt: openssl driver round trips with the default cipher', function ()
{
	require_ext('openssl');
	$e = _new_encrypt('openssl');
	assert_same('openssl', $e->_driver);
	$key = 'unit-test-key';
	foreach (array('x', 'The quick brown fox', str_repeat('0123456789abcdef', 8), "Caf\xc3\xa9 \xe6\x97\xa5\xe6\x9c\xac") as $plain)
	{
		$enc = $e->encode($plain, $key);
		assert_true($enc !== $plain);
		assert_same(1, preg_match('#^[A-Za-z0-9+/]+={0,2}$#', $enc));
		assert_same($plain, $e->decode($enc, $key));
	}
	assert_true($e->encode('same', $key) !== $e->encode('same', $key));
	assert_true($e->decode($e->encode('secret', 'right'), 'wrong') !== 'secret');
	assert_false($e->decode('not base64 !!', $key));
	assert_false($e->decode(base64_encode('short'), $key));
});

test('encrypt: openssl driver reads what mcrypt wrote for the rijndael-128 (AES) modes', function ()
{
	require_ext('openssl');
	$n = 0;
	foreach (include dirname(dirname(__FILE__)).'/fixtures/encrypt.php' as $f)
	{
		if ($f['cipher'] !== 'rijndael-128')
		{
			continue;
		}
		$e = _new_encrypt('openssl', $f['cipher'], $f['mode']);
		assert_same($f['plain'], $e->decode($f['encoded'], $f['key']), $f['name']);
		$n++;
	}
	assert_true($n >= 7, 'expected the rijndael-128 fixtures, found '.$n);
});

test('encrypt: mcrypt reads what the openssl driver wrote for the rijndael-128 modes', function ()
{
	require_ext('openssl', 'mcrypt');
	foreach (array('cbc', 'ecb', 'cfb', 'ncfb', 'nofb') as $mode)
	{
		$o = _new_encrypt('openssl', 'rijndael-128', $mode);
		$m = _new_encrypt('mcrypt', 'rijndael-128', $mode);
		foreach (array('a', 'exactly sixteen!!', 'a longer text that spans several AES blocks') as $plain)
		{
			assert_same($plain, $m->decode($o->encode($plain, 'shared key'), 'shared key'), $mode);
			assert_same($plain, $o->decode($m->encode($plain, 'shared key'), 'shared key'), $mode);
		}
	}
});

test('encrypt: auto picks mcrypt when it is loaded, openssl otherwise', function ()
{
	require_ext('openssl');
	$e = _new_encrypt('auto');
	assert_same(function_exists('mcrypt_encrypt') ? 'mcrypt' : 'openssl', $e->_driver);
	$e = _new_encrypt('');
	assert_same(function_exists('mcrypt_encrypt') ? 'mcrypt' : 'openssl', $e->_driver);
});

test('encrypt: set_cipher/set_mode work with plain strings (no MCRYPT_* constants needed)', function ()
{
	require_ext('openssl');
	$e = _new_encrypt('openssl', 'rijndael-128', 'cbc');
	assert_same('rijndael-128', $e->_get_cipher());
	assert_same('cbc', $e->_get_mode());
	assert_same('rijndael-256', _new_encrypt('openssl')->_get_cipher());
	assert_same('cbc', _new_encrypt('openssl')->_get_mode());
});

test('captcha: a random word of the default length is generated when none is given', function ()
{
	require_ext('gd');
	get_instance()->load->helper('captcha');
	$dir = TEST_TMP.'captcha2/';
	@mkdir($dir);
	$cap = create_captcha(array('img_path' => $dir, 'img_url' => 'http://example.com/c/'));
	assert_same(8, strlen($cap['word']));
	assert_same(1, preg_match('/^[0-9a-zA-Z]{8}$/', $cap['word']));
	$cap2 = create_captcha(array('img_path' => $dir, 'img_url' => 'http://example.com/c/', 'word_length' => 5));
	assert_same(5, strlen($cap2['word']));
});

test('zip: file modification times are stored in the archive', function ()
{
	require_ext('zip');
	$f = TEST_TMP.'dated.txt';
	file_put_contents($f, 'content');
	$ts = mktime(12, 34, 56, 5, 17, 2020);
	touch($f, $ts);
	require_once BASEPATH.'libraries/Zip.php';
	$z = new CI_Zip();
	$z->read_file($f, TRUE);
	assert_true($z->archive(TEST_TMP.'dated.zip'));
	$za = new ZipArchive();
	assert_true($za->open(TEST_TMP.'dated.zip') === TRUE);
	$stat = $za->statIndex(0);
	$za->close();
	// DOS time has a 2 second resolution
	assert_true(abs($stat['mtime'] - $ts) <= 2, 'stored '.date('c', $stat['mtime']).' expected '.date('c', $ts));
});

test('unit_test: the report lists every result row', function ()
{
	require_once BASEPATH.'libraries/Unit_test.php';
	$u = new CI_Unit_test();
	$html = $u->run(2, 2, 'sum');
	assert_contains('<th style="text-align: left; border-bottom:1px solid #CCC;">Test Name</th>', $html);
	assert_contains('sum', $html);
	assert_contains('Passed', $html);
});

test('core: the error handler ignores E_STRICT (2048) and reports other levels', function ()
{
	ob_start();
	_exception_handler(2048, 'strict thing', 'f.php', 1);
	$out = ob_get_clean();
	assert_same('', $out);
});

test('database: pdo statement errors do not throw exceptions (silent error mode)', function ()
{
	require_ext('pdo_sqlite');
	$db = get_instance()->load->database(array(
		'dbdriver' => 'pdo', 'hostname' => 'sqlite::memory:', 'username' => '', 'password' => '', 'database' => '',
		'dbprefix' => '', 'pconnect' => FALSE, 'db_debug' => FALSE, 'cache_on' => FALSE, 'cachedir' => '',
		'char_set' => 'utf8', 'dbcollat' => 'utf8_general_ci', 'swap_pre' => '', 'autoinit' => TRUE, 'stricton' => FALSE,
	), TRUE);
	assert_false($db->query('THIS IS NOT SQL'));
	assert_true($db->conn_id->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_SILENT);
});

test('input: ip validation honours ipv4 / ipv6 restrictions', function ()
{
	$_GET = $_POST = $_COOKIE = array();
	require_once BASEPATH.'core/Input.php';
	$in = new CI_Input();
	assert_true($in->valid_ip('10.0.0.1', 'ipv4'));
	assert_false($in->valid_ip('10.0.0.1', 'ipv6'));
	assert_true($in->valid_ip('2001:db8::1', 'ipv6'));
	assert_false($in->valid_ip('2001:db8::1', 'ipv4'));
});

test('lint: classes keep dynamic properties allowed (PHP 8.2+)', function ()
{
	require_once BASEPATH.'libraries/Table.php';
	$o = new CI_Table();
	$o->made_up_property = 1;
	assert_same(1, $o->made_up_property);
	$ci = get_instance();
	$ci->another_made_up = 'x';
	assert_same('x', $ci->another_made_up);
});

test('output: _display() copes with a controller that produced no output', function ()
{
	require_once BASEPATH.'core/Output.php';
	$o = new CI_Output();
	ob_start();
	$o->_display();
	$out = ob_get_clean();
	assert_same('', $out);

	$o = new CI_Output();
	$o->set_output('elapsed {elapsed_time} memory {memory_usage}');
	ob_start();
	$o->_display();
	$out = ob_get_clean();
	assert_not_contains('{elapsed_time}', $out);
	assert_not_contains('{memory_usage}', $out);
});

test('front controller: real index.php requests run without any PHP diagnostic', function ()
{
	$routes = array(
		''					=> 'Welcome to CodeIgniter',
		'welcome'			=> 'Welcome to CodeIgniter',
		'welcome/index'		=> 'Welcome to CodeIgniter',
		'no_such_controller'	=> '404 Page Not Found',
	);
	foreach ($routes as $route => $expected)
	{
		$out = array();
		exec(escapeshellarg(PHP_BINARY).' -d xdebug.mode=off -d display_errors=1 '.escapeshellarg(ROOT_PATH.'index.php').' '.escapeshellarg($route).' 2>&1', $out);
		$text = implode("\n", $out);
		assert_contains($expected, $text, 'route "'.$route.'"');
		foreach (array('A PHP Error was encountered', 'Deprecated:', 'Warning:', 'Notice:', 'Fatal error') as $bad)
		{
			assert_not_contains($bad, $text, 'route "'.$route.'"');
		}
	}
});
