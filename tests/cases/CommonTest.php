<?php

test('common: is_php compares against the running interpreter', function ()
{
	assert_true(is_php('5.0.0'));
	assert_true(is_php(PHP_VERSION));
	assert_false(is_php('99.0.0'));
});

test('common: remove_invisible_characters strips control chars but keeps whitespace', function ()
{
	assert_same('ab', remove_invisible_characters("a\0b"));
	assert_same("a\tb\nc", remove_invisible_characters("a\tb\nc"));
	assert_same('ab', remove_invisible_characters('a%00b'));
	assert_same('a%00b', remove_invisible_characters('a%00b', FALSE));
});

test('common: html_escape escapes strings and arrays', function ()
{
	assert_same('&lt;a href=&quot;x&quot;&gt;&amp;&#039;', html_escape('<a href="x">&\''));
	assert_same(array('&lt;b&gt;', 'k' => '&amp;'), html_escape(array('<b>', 'k' => '&')));
});

test('common: config_item / get_config read application config', function ()
{
	assert_same('CI_', config_item('subclass_prefix') === 'MY_' ? 'CI_' : 'other');
	assert_true(is_array(get_config()));
	assert_same(FALSE, config_item('this_item_does_not_exist'));
});

test('common: load_class returns a singleton and is_loaded tracks it', function ()
{
	$a =& load_class('Benchmark', 'core');
	$b =& load_class('Benchmark', 'core');
	assert_true($a === $b);
	$loaded = is_loaded();
	assert_true(isset($loaded['benchmark']));
});

test('common: is_really_writable', function ()
{
	assert_true(is_really_writable(TEST_TMP));
	assert_false(is_really_writable(TEST_TMP.'does/not/exist'));
});

test('common: log_message is callable with (level, message)', function ()
{
	// log_threshold is 0 in the default config, so this must be a silent no-op
	assert_same(NULL, log_message('error', 'unit test message'));
});

test('boot: version constants and singleton controller', function ()
{
	assert_true(version_compare(CI_VERSION, '2.2.6', '>='));
	$ci = get_instance();
	foreach (array('config', 'input', 'security', 'output', 'uri', 'router', 'lang', 'load', 'utf8') as $p)
	{
		assert_true(isset($ci->$p), 'controller property '.$p);
	}
});
