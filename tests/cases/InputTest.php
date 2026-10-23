<?php

function _fresh_input($get = array(), $post = array(), $cookie = array())
{
	$_GET = $get;
	$_POST = $post;
	$_COOKIE = $cookie;
	$_REQUEST = array_merge($get, $post);
	// CI_Input sanitizes the superglobals when constructed
	require_once BASEPATH.'core/Input.php';
	return new CI_Input();
}

test('input: get/post fetch values and honour defaults', function ()
{
	$in = _fresh_input(array('a' => '1'), array('b' => '2'));
	assert_same('1', $in->get('a'));
	assert_same('2', $in->post('b'));
	assert_false($in->get('missing'));
	assert_false($in->post('missing'));
	assert_same('1', $in->get_post('a'));
	assert_same('2', $in->get_post('b'));
});

test('input: get() without argument returns the whole array', function ()
{
	$in = _fresh_input(array('a' => '1', 'b' => '2'));
	assert_same(array('a' => '1', 'b' => '2'), $in->get());
});

test('input: global arrays are sanitized (control chars, key chars)', function ()
{
	$in = _fresh_input(array('name' => "va\0lue"));
	assert_same('value', $in->get('name'));
});

test('input: xss filtering is applied when requested', function ()
{
	$in = _fresh_input(array(), array('c' => '<script>alert(1)</script>x'));
	$raw = $in->post('c');
	assert_contains('<script>', $raw);
	$clean = $in->post('c', TRUE);
	assert_not_contains('<script', $clean);
});

test('input: newlines are normalised to \n', function ()
{
	$in = _fresh_input(array('t' => "a\r\nb\rc"));
	assert_same("a\nb\nc", $in->get('t'));
});

test('input: nested arrays are cleaned recursively', function ()
{
	$in = _fresh_input(array('arr' => array('k' => "v\0")));
	$v = $in->get('arr');
	assert_same('v', $v['k']);
});

test('input: valid_ip and ip_address', function ()
{
	$in = _fresh_input();
	assert_true($in->valid_ip('192.168.0.1'));
	assert_true($in->valid_ip('::1'));
	assert_false($in->valid_ip('999.1.1.1'));
	assert_false($in->valid_ip('nope'));
	$ci_ip = $in->ip_address();
	assert_true(is_string($ci_ip));
});

test('input: user_agent and server()', function ()
{
	$in = _fresh_input();
	assert_contains('Firefox', $in->user_agent());
	assert_same('GET', $in->server('REQUEST_METHOD'));
	assert_false($in->server('NOPE'));
});

test('input: set_cookie / cookie() read-back', function ()
{
	$in = _fresh_input(array(), array(), array('theme' => 'dark'));
	assert_same('dark', $in->cookie('theme'));
	assert_false($in->cookie('nope'));
});

test('input: is_ajax_request follows X-Requested-With', function ()
{
	$in = _fresh_input();
	assert_false($in->is_ajax_request());
	$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
	assert_true($in->is_ajax_request());
	unset($_SERVER['HTTP_X_REQUESTED_WITH']);
});
