<?php

test('config: item access, slash_item, set_item', function ()
{
	$c = get_instance()->config;
	$c->set_item('unit_test_item', 'v');
	assert_same('v', $c->item('unit_test_item'));
	assert_false($c->item('unit_test_missing'));
	$c->set_item('unit_test_path', 'a/b');
	assert_same('a/b/', $c->slash_item('unit_test_path'));
	assert_same('v', $c->item('unit_test_item'));
});

test('config: site_url / base_url / system_url', function ()
{
	$c = get_instance()->config;
	$old = $c->item('base_url');
	$c->set_item('base_url', 'http://example.com/');
	$c->set_item('index_page', 'index.php');
	$c->set_item('url_suffix', '');
	assert_same('http://example.com/index.php/foo/bar', $c->site_url('foo/bar'));
	assert_same('http://example.com/', $c->base_url());
	assert_same('http://example.com/img/a.png', $c->base_url('img/a.png'));
	$c->set_item('base_url', $old);
});

test('config: load() reports unknown files when failing gracefully', function ()
{
	$c = get_instance()->config;
	assert_false($c->load('no_such_config_file', FALSE, TRUE));
});

test('lang: load a language file and fetch lines', function ()
{
	$l = get_instance()->lang;
	$l->load('form_validation');
	assert_same('The %s field is required.', $l->line('required'));
	assert_false($l->line('no_such_line_at_all'));
});

test('uri: segments, assoc arrays and slash helpers', function ()
{
	require_once BASEPATH.'core/URI.php';
	$u = new CI_URI();
	$u->_set_uri_string('products/view/id/7/color/red');
	$u->_explode_segments();
	$u->_reindex_segments();
	assert_same('products', $u->segment(1));
	assert_same('view', $u->segment(2));
	assert_same(FALSE, $u->segment(9));
	assert_same('none', $u->segment(9, 'none'));
	assert_same(6, $u->total_segments());
	assert_same(array('id' => '7', 'color' => 'red'), $u->uri_to_assoc(3));
	assert_same('id/7/color/red', $u->assoc_to_uri(array('id' => 7, 'color' => 'red')));
	assert_same('/products', $u->slash_segment(1, 'leading'));
	assert_same('products/', $u->slash_segment(1, 'trailing'));
	assert_same('/view/', $u->slash_segment(2, 'both'));
	assert_same('products/view/id/7/color/red', $u->uri_string());
});

test('router: resolves the default controller and routes', function ()
{
	$r = get_instance()->router;
	assert_same('welcome', $r->fetch_class());
	assert_same('index', $r->fetch_method());
	assert_same('', $r->fetch_directory());
	assert_true(is_array($r->routes));
});

test('output: buffer, headers and content type', function ()
{
	require_once BASEPATH.'core/Output.php';
	$o = new CI_Output();
	$o->set_output('hello');
	$o->append_output(' world');
	assert_same('hello world', $o->get_output());
	assert_true($o->set_header('X-Test: 1') === $o);
	assert_true($o->set_content_type('application/json') === $o);
	assert_true($o->enable_profiler(FALSE) === $o);
	assert_true($o->cache(5) === $o);
	assert_true($o->set_status_header(200) === $o);
});

test('loader: views, vars, files, helpers and libraries', function ()
{
	$CI = get_instance();
	$CI->load->vars(array('title' => 'Hi'));
	$CI->load->vars('who', 'World');
	assert_same('World', $CI->load->get_var('who'));
	assert_same('Hi', $CI->load->get_var('title'));

	$html = $CI->load->view('welcome_message', array(), TRUE);
	assert_contains('Welcome to CodeIgniter', $html);

	$f = TEST_TMP.'tpl.php';
	file_put_contents($f, "<h1><?php echo \$title; ?></h1><?=\$who?>|");
	$out = $CI->load->file($f, TRUE);
	assert_contains('<h1>Hi</h1>', $out);
	assert_contains('World|', $out);

	$CI->load->helper('url');
	assert_true(function_exists('site_url'));
	$CI->load->library('table');
	assert_true(isset($CI->table));
	assert_same('table', $CI->load->is_loaded('table'));
});

test('loader: a library can be loaded under an alias and with params', function ()
{
	$CI = get_instance();
	$CI->load->library('table', array(), 'tbl_alias');
	assert_instance('CI_Table', $CI->tbl_alias);
});

test('utf8: clean_string and validity checks', function ()
{
	$u = get_instance()->utf8;
	assert_true($u->_is_ascii('plain'));
	assert_false($u->_is_ascii("caf\xc3\xa9"));
	assert_same("caf\xc3\xa9", $u->clean_string("caf\xc3\xa9"));
	assert_same('abc', $u->safe_ascii_for_xml("a\x01bc"));
});

test('log: writes to the configured file at or below the threshold', function ()
{
	$c = get_instance()->config;
	$c->set_item('log_path', TEST_TMP);
	$c->set_item('log_threshold', 1);
	require_once BASEPATH.'libraries/Log.php';
	$log = new CI_Log();
	assert_true($log->write_log('error', 'unit test error message'));
	assert_false($log->write_log('debug', 'too verbose to be written'));
	$file = TEST_TMP.'log-'.date('Y-m-d').'.php';
	assert_true(file_exists($file));
	$content = file_get_contents($file);
	assert_contains('ERROR', $content);
	assert_contains('unit test error message', $content);
	assert_not_contains('too verbose', $content);
	$c->set_item('log_threshold', 0);
});

test('benchmark: marks and elapsed_time', function ()
{
	$b = get_instance()->benchmark;
	$b->mark('t_start');
	$b->mark('t_end');
	$t = $b->elapsed_time('t_start', 't_end');
	assert_true(is_numeric($t));
	assert_true($t >= 0);
	assert_same('0.0000', number_format(0, 4, '.', ''));
	assert_true(is_string($b->memory_usage()));
});

test('hooks: disabled by default and callable', function ()
{
	$h = get_instance()->hooks;
	assert_false($h->_call_hook('does_not_exist'));
});
