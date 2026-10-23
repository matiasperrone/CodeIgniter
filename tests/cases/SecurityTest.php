<?php

function _sec()
{
	return get_instance()->security;
}

test('security: xss_clean neutralises script tags', function ()
{
	$out = _sec()->xss_clean('<script>alert("x")</script>hello');
	assert_not_contains('<script', $out);
	assert_contains('hello', $out);
});

test('security: xss_clean neutralises javascript: urls and event handlers', function ()
{
	$out = _sec()->xss_clean('<a href="javascript:alert(1)">x</a>');
	assert_not_contains('javascript:alert', $out);
	$out = _sec()->xss_clean('<img src="x" onerror="alert(1)">');
	assert_not_contains('onerror', $out);
});

test('security: xss_clean leaves harmless text untouched', function ()
{
	assert_same('Just some plain text, 1 < 2 is false.', str_replace('&lt;', '<', _sec()->xss_clean('Just some plain text, 1 < 2 is false.')));
	assert_same('<b>bold</b>', _sec()->xss_clean('<b>bold</b>'));
});

test('security: xss_clean recurses into arrays and keeps keys', function ()
{
	$in = array('a' => '<script>alert(1)</script>ok', 'b' => array('c' => '<iframe src="x"></iframe>fine'), 3 => 'plain');
	$out = _sec()->xss_clean($in);
	assert_same(array('a', 'b', 3), array_keys($out));
	assert_not_contains('<script', $out['a']);
	assert_contains('ok', $out['a']);
	assert_not_contains('<iframe', $out['b']['c']);
	assert_same('plain', $out[3]);
});

test('security: xss_clean decodes encoded attacks', function ()
{
	$out = _sec()->xss_clean('<a href="&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;alert(1)">x</a>');
	assert_not_contains('javascript:', strtolower($out));
});

test('security: xss_clean image mode reports safety', function ()
{
	assert_true(_sec()->xss_clean("GIF89a\x00\x01 harmless bytes", TRUE));
	assert_false(_sec()->xss_clean('<script>alert(1)</script>', TRUE));
});

test('security: sanitize_filename removes path and dangerous chars', function ()
{
	assert_same('etcpasswd', str_replace('_', '', _sec()->sanitize_filename('../../etc/passwd')));
	assert_same('file.txt', _sec()->sanitize_filename('file.txt'));
	assert_not_contains('/', _sec()->sanitize_filename('a/b/c.txt'));
	assert_not_contains('<', _sec()->sanitize_filename('<a>.txt'));
});

test('security: entity_decode handles numeric and named entities', function ()
{
	assert_same('<>&', _sec()->entity_decode('&lt;&gt;&amp;'));
});
