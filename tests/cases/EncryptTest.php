<?php

function _enc($cipher = NULL, $mode = NULL)
{
	require_once BASEPATH.'libraries/Encrypt.php';
	$e = new CI_Encrypt();
	if ($cipher !== NULL)
	{
		$e->set_cipher($cipher);
		$e->set_mode($mode);
	}
	return $e;
}

function _enc_fixtures()
{
	return include TESTS_FIXTURES.'encrypt.php';
}

define('TESTS_FIXTURES', dirname(dirname(__FILE__)).'/fixtures/');

test('encrypt: golden ciphertexts from existing installations still decode', function ()
{
	require_ext('mcrypt');
	foreach (_enc_fixtures() as $f)
	{
		$e = _enc($f['cipher'], $f['mode']);
		assert_same($f['plain'], $e->decode($f['encoded'], $f['key']), $f['name']);
	}
});

test('encrypt: encode/decode round trip for every cipher/mode used in the fixtures', function ()
{
	require_ext('mcrypt');
	foreach (_enc_fixtures() as $f)
	{
		$e = _enc($f['cipher'], $f['mode']);
		$enc = $e->encode($f['plain'], $f['key']);
		assert_true($enc !== $f['plain'], $f['name']);
		assert_same($f['plain'], $e->decode($enc, $f['key']), $f['name']);
	}
});

test('encrypt: output is base64 and randomised by the IV', function ()
{
	require_ext('mcrypt');
	$e = _enc();
	$a = $e->encode('same text', 'k');
	$b = $e->encode('same text', 'k');
	assert_true($a !== $b);
	assert_same(1, preg_match('#^[A-Za-z0-9+/]+={0,2}$#', $a));
});

test('encrypt: a wrong key does not reveal the plaintext', function ()
{
	require_ext('mcrypt');
	$e = _enc();
	$enc = $e->encode('top secret', 'right key');
	assert_true($e->decode($enc, 'wrong key') !== 'top secret');
});

test('encrypt: set_key supplies the default key; explicit keys are md5-ed', function ()
{
	require_ext('mcrypt');
	$e = _enc();
	$e->set_key(md5('global'));
	assert_same(md5('global'), $e->get_key());
	assert_same(md5('explicit'), $e->get_key('explicit'));
	$enc = $e->encode('hello');
	assert_same('hello', $e->decode($enc));
});

test('encrypt: key is read from config when none is set', function ()
{
	require_ext('mcrypt');
	$CI = get_instance();
	$old = $CI->config->item('encryption_key');
	$CI->config->set_item('encryption_key', 'from-config');
	$e = _enc();
	assert_same(md5('from-config'), $e->get_key());
	$CI->config->set_item('encryption_key', $old);
});

test('encrypt: hashing helpers', function ()
{
	require_ext('mcrypt');
	$e = _enc();
	assert_same(sha1('abc'), $e->hash('abc'));
	assert_same(sha1('abc'), $e->sha1('abc'));
	$e->set_hash('md5');
	assert_same(md5('abc'), $e->hash('abc'));
	$e->set_hash('bogus');
	assert_same(sha1('abc'), $e->hash('abc'));
});

test('encrypt: encode_from_legacy rejects non-base64 input and returns base64 otherwise', function ()
{
	require_ext('mcrypt');
	$e = _enc();
	assert_false($e->encode_from_legacy('not base64 !!', MCRYPT_MODE_ECB, 'k'));

	$old = _enc();
	$old->set_mode(MCRYPT_MODE_ECB);
	$legacy = $old->encode('legacy text', 'k');
	$converted = _enc()->encode_from_legacy($legacy, MCRYPT_MODE_ECB, 'k');
	assert_same(1, preg_match('#^[A-Za-z0-9+/]+={0,2}$#', $converted));
});
