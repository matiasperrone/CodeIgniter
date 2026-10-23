<?php

require_once BASEPATH.'libraries/Email.php';

/** Exposes the protected internals the public API is built from */
class T_Email extends CI_Email
{
	public function call($method)
	{
		$args = func_get_args();
		array_shift($args);
		return call_user_func_array(array($this, $method), $args);
	}

	public function prop($name)
	{
		return $this->$name;
	}
}

function _email()
{
	$e = new T_Email();
	$e->from('me@example.com', 'Me Myself');
	$e->to('you@example.com');
	$e->subject('Hello');
	return $e;
}

test('email: valid_email / clean_email', function ()
{
	$e = new T_Email();
	assert_true($e->valid_email('a@b.co'));
	assert_false($e->valid_email('nope'));
	assert_false($e->valid_email('a@'));
	assert_same('foo@bar.com', $e->clean_email('Foo <foo@bar.com>'));
	assert_same(array('a@b.co', 'c@d.org'), $e->clean_email(array('A <a@b.co>', 'c@d.org')));
});

test('email: the builder methods are chainable', function ()
{
	$e = new T_Email();
	assert_true($e->from('me@example.com') === $e);
	assert_true($e->to('you@example.com') === $e);
	assert_true($e->cc('cc@example.com') === $e);
	assert_true($e->bcc('bcc@example.com') === $e);
	assert_true($e->subject('x') === $e);
	assert_true($e->message('x') === $e);
});

test('email: message() normalises line endings and leaves backslashes alone', function ()
{
	$e = new T_Email();
	$e->message("Line one\r\nLine two with a \\'quote\\'");
	assert_same("Line one\nLine two with a \\'quote\\'", $e->prop('_body'));
});

test('email: safe_mode flag is off on any modern PHP', function ()
{
	$e = new T_Email();
	assert_false($e->prop('_safe_mode'));
	$e->initialize(array('mailtype' => 'html'));
	assert_false($e->prop('_safe_mode'));
});

test('email: smtp_auth is derived from the credentials', function ()
{
	$e = new T_Email();
	assert_false($e->prop('_smtp_auth'));
	$e->initialize(array('smtp_user' => 'u', 'smtp_pass' => 'p'));
	assert_true($e->prop('_smtp_auth'));
});

test('email: content type follows the mail type and attachments', function ()
{
	$e = _email();
	assert_same('plain', $e->call('_get_content_type'));
	$e->set_mailtype('html');
	assert_same('html', $e->call('_get_content_type'));
	$e->set_alt_message('alt');
	assert_same('html', $e->call('_get_content_type'));
});

test('email: quoted-printable header encoding', function ()
{
	$e = new T_Email();
	assert_same('=?utf-8?Q?H=c3=a9llo_w=c3=b6rld?=', $e->call('_prep_q_encoding', "H\xc3\xa9llo w\xc3\xb6rld"));
});

test('email: word_wrap wraps at the requested column', function ()
{
	$e = new T_Email();
	$wrapped = $e->word_wrap(str_repeat('word ', 30), 40);
	foreach (explode("\n", rtrim($wrapped)) as $line)
	{
		assert_true(strlen($line) <= 40, 'line too long: '.$line);
	}
	assert_true(substr_count($wrapped, "\n") >= 3);
});

test('email: headers contain sender, recipients, message id and MIME info', function ()
{
	$e = _email();
	$e->cc('cc@example.com');
	$e->message('Body');
	$e->call('_build_headers');
	$e->call('_write_headers');
	$out = $e->print_debugger();
	assert_contains('From: &quot;Me Myself&quot; &lt;me@example.com&gt;', $out);
	assert_contains('Return-Path: &lt;me@example.com&gt;', $out);
	assert_contains('Cc: cc@example.com', $out);
	assert_contains('X-Mailer: CodeIgniter', $out);
	assert_contains('Mime-Version: 1.0', $out);
	assert_contains('Message-ID: &lt;', $out);
});

test('email: html mails with an alternative body become multipart/alternative', function ()
{
	$e = _email();
	$e->set_mailtype('html');
	$e->message('<p>Hi</p>');
	$e->set_alt_message('Hi alt');
	$e->call('_set_boundaries');
	$e->call('_build_headers');
	$e->call('_build_message');
	$body = $e->prop('_finalbody');
	assert_contains('Content-Type: text/plain', $body);
	assert_contains('Content-Type: text/html', $body);
	assert_contains('Hi alt', $body);
	assert_contains('<p>Hi</p>', $body);
	assert_contains('multipart/alternative', $e->prop('_header_str').implode('', (array) $e->prop('_headers')));
});

test('email: attach() records name, disposition and mime type', function ()
{
	$e = _email();
	$f = TEST_TMP.'attach.txt';
	file_put_contents($f, 'hello');
	assert_true($e->attach($f) === $e);
	assert_same(array($f), $e->prop('_attach_name'));
	assert_same(array('attachment'), $e->prop('_attach_disp'));
});

test('email: clear() resets the message state', function ()
{
	$e = _email();
	$e->message('x');
	$e->clear();
	assert_same('', $e->prop('_body'));
	assert_same(array(), $e->prop('_recipients'));
});

test('email: invalid recipients are reported when validation is on', function ()
{
	$e = new T_Email();
	$e->initialize(array('validate' => TRUE));
	$e->to('not-an-email');
	assert_contains('not-an-email', $e->print_debugger());
});
