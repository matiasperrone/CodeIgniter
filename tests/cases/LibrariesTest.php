<?php

test('form_validation: required / email / length rules pass and fail', function ()
{
	$CI = get_instance();
	$_POST = array('name' => 'Ada', 'email' => 'ada@example.com', 'age' => '36', 'bad' => 'x');
	$CI->load->library('form_validation');
	$CI->form_validation->set_rules('name', 'Name', 'required|min_length[2]|max_length[10]');
	$CI->form_validation->set_rules('email', 'Email', 'required|valid_email');
	$CI->form_validation->set_rules('age', 'Age', 'required|integer|greater_than[18]');
	assert_true($CI->form_validation->run());
	assert_same('', validation_errors());
	assert_same('Ada', $CI->form_validation->set_value('name'));
});

test('form_validation: failures produce error messages', function ()
{
	$CI = get_instance();
	$_POST = array('email' => 'nope', 'name' => '', 'age' => '3');
	require_once BASEPATH.'libraries/Form_validation.php';
	$fv = new CI_Form_validation();
	$fv->set_rules('name', 'Name', 'required');
	$fv->set_rules('email', 'Email', 'required|valid_email');
	$fv->set_rules('age', 'Age', 'greater_than[18]');
	assert_false($fv->run());
	assert_contains('The Name field is required.', $fv->error_string());
	assert_contains('valid email', $fv->error('email'));
	assert_contains('Age', $fv->error('age'));
});

test('form_validation: prepping rules (trim, xss_clean, callbacks via functions)', function ()
{
	$_POST = array('t' => '  padded  ', 'x' => '<script>alert(1)</script>y');
	require_once BASEPATH.'libraries/Form_validation.php';
	$fv = new CI_Form_validation();
	$fv->set_rules('t', 'T', 'trim|required');
	$fv->set_rules('x', 'X', 'xss_clean');
	assert_true($fv->run());
	assert_same('padded', $_POST['t']);
	assert_not_contains('<script', $_POST['x']);
});

test('pagination: renders links for the current page', function ()
{
	$CI = get_instance();
	require_once BASEPATH.'libraries/Pagination.php';
	$p = new CI_Pagination(array('base_url' => 'http://example.com/list', 'total_rows' => 100, 'per_page' => 10, 'uri_segment' => 3));
	$links = $p->create_links();
	assert_contains('<strong>1</strong>', $links);
	assert_contains('http://example.com/list/10', $links);
	assert_contains('Last', $links);
});

test('pagination: nothing is rendered when everything fits on one page', function ()
{
	require_once BASEPATH.'libraries/Pagination.php';
	$p = new CI_Pagination(array('base_url' => 'http://example.com/list', 'total_rows' => 5, 'per_page' => 10));
	assert_same('', $p->create_links());
});

test('table: generates html from arrays and sets headings', function ()
{
	require_once BASEPATH.'libraries/Table.php';
	$t = new CI_Table();
	$t->set_heading('Name', 'Color');
	$t->add_row('Fred', 'Blue');
	$t->add_row(array('Mary', 'Red'));
	$html = $t->generate();
	assert_contains('<th>Name</th>', $html);
	assert_contains('<td>Fred</td>', $html);
	assert_contains('<td>Red</td>', $html);
	assert_contains('<table', $html);
	$t->clear();
	assert_contains('Undefined table data', $t->generate());
});

test('table: generate() from a nested array', function ()
{
	require_once BASEPATH.'libraries/Table.php';
	$t = new CI_Table();
	$html = $t->generate(array(array('a', 'b'), array('c', 'd')));
	// the first row of a nested array becomes the heading
	assert_contains('<th>a</th>', $html);
	assert_contains('<td>c</td>', $html);
	assert_contains('<td>d</td>', $html);
});

test('parser: variables and variable pairs', function ()
{
	require_once BASEPATH.'libraries/Parser.php';
	$p = new CI_Parser();
	$out = $p->parse_string('Hi {name}! {items}[{v}]{/items}', array(
		'name'  => 'Ada',
		'items' => array(array('v' => 1), array('v' => 2)),
	), TRUE);
	assert_same('Hi Ada! [1][2]', $out);
});

test('typography: auto_typography converts quotes, dashes and paragraphs', function ()
{
	require_once BASEPATH.'libraries/Typography.php';
	$t = new CI_Typography();
	$out = $t->auto_typography("He said \"hello\" -- and left...\n\nNew paragraph");
	assert_contains('&#8220;hello&#8221;', $out);
	assert_contains('&#8212;', $out);
	assert_contains('&#8230;', $out);
	assert_contains('<p>', $out);
	assert_contains('New paragraph', $out);
});

test('typography: format_characters and nl2br_except_pre', function ()
{
	require_once BASEPATH.'libraries/Typography.php';
	$t = new CI_Typography();
	assert_contains('&#8217;', $t->format_characters("it's"));
	assert_same("a<br />\nb", $t->nl2br_except_pre("a\nb"));
});

test('user_agent: parses browser, version, platform and robots', function ()
{
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/115.0';
	require_once BASEPATH.'libraries/User_agent.php';
	$ua = new CI_User_agent();
	assert_true($ua->is_browser());
	assert_false($ua->is_robot());
	assert_false($ua->is_mobile());
	assert_same('Firefox', $ua->browser());
	assert_same('115.0', $ua->version());
	assert_contains('Windows', $ua->platform());
	assert_true($ua->is_browser('Firefox'));
	assert_false($ua->is_browser('Chrome'));

	$_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1 (+http://www.google.com/bot.html)';
	$ua = new CI_User_agent();
	assert_true($ua->is_robot());
	assert_same('Googlebot', $ua->robot());

	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';
	$ua = new CI_User_agent();
	assert_true($ua->is_mobile());
	assert_same('Apple iPhone', $ua->mobile());
});

test('calendar: generates a month grid', function ()
{
	require_once BASEPATH.'libraries/Calendar.php';
	$c = new CI_Calendar();
	$html = $c->generate(2024, 2);
	assert_contains('February', $html);
	assert_contains('2024', $html);
	assert_contains('>29<', $html);
	assert_not_contains('>30<', $html);
	assert_same(29, $c->get_total_days(2, 2024));
	assert_same(28, $c->get_total_days(2, 2023));
});

test('calendar: day names and adjust_date', function ()
{
	require_once BASEPATH.'libraries/Calendar.php';
	$c = new CI_Calendar();
	assert_same(array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'), array_values(array_map('ucfirst', array_map('strtolower', $c->get_day_names('short')))));
	$d = $c->adjust_date(13, 2023);
	assert_same(array('month' => '01', 'year' => 2024), array('month' => $d['month'], 'year' => $d['year']));
});

test('zip: builds an archive with files and directories', function ()
{
	require_once BASEPATH.'libraries/Zip.php';
	$z = new CI_Zip();
	$z->add_data('hello.txt', 'Hello zip');
	$z->add_data(array('dir/a.txt' => 'A', 'dir/b.txt' => 'B'));
	$z->add_dir('empty');
	$bin = $z->get_zip();
	assert_same('PK', substr($bin, 0, 2));
	assert_contains('hello.txt', $bin);
	assert_contains('dir/a.txt', $bin);
	assert_true($z->archive(TEST_TMP.'t.zip'));
	assert_true(filesize(TEST_TMP.'t.zip') > 50);
	if (class_exists('ZipArchive'))
	{
		$za = new ZipArchive();
		assert_true($za->open(TEST_TMP.'t.zip') === TRUE);
		assert_same('Hello zip', $za->getFromName('hello.txt'));
		assert_same('B', $za->getFromName('dir/b.txt'));
		$za->close();
	}
});

test('unit_test: records passed and failed results', function ()
{
	require_once BASEPATH.'libraries/Unit_test.php';
	$u = new CI_Unit_test();
	assert_contains('Passed', $u->run(1 + 1, 2, 'addition'));
	assert_contains('Failed', $u->run(1 + 1, 3, 'wrong'));
	assert_equals(2, count($u->result()));
	assert_contains('addition', $u->report());
	$u->use_strict(TRUE);
	assert_contains('Failed', $u->run('1', 1, 'strict'));
});

test('cache: file driver saves, reads, metadata and deletes', function ()
{
	$CI = get_instance();
	$CI->config->set_item('cache_path', TEST_TMP.'cache/');
	@mkdir(TEST_TMP.'cache/');
	$CI->load->driver('cache', array('adapter' => 'file'));
	assert_true($CI->cache->is_supported('file'));
	assert_true($CI->cache->save('k1', array('a' => 1), 60));
	assert_same(array('a' => 1), $CI->cache->get('k1'));
	assert_false($CI->cache->get('missing'));
	$meta = $CI->cache->get_metadata('k1');
	assert_true(is_array($meta));
	assert_true($meta['expire'] > time());
	assert_true($CI->cache->delete('k1'));
	assert_false($CI->cache->get('k1'));
	$CI->cache->save('k2', 'x', 60);
	assert_true($CI->cache->clean());
	assert_false($CI->cache->get('k2'));
});

test('session: userdata and flashdata are kept in the cookie', function ()
{
	$CI = get_instance();
	$CI->config->set_item('encryption_key', 'unit-test-encryption-key');
	$CI->config->set_item('sess_use_database', FALSE);
	$CI->config->set_item('sess_encrypt_cookie', FALSE);
	unset($CI->session);
	$CI->load->library('session');
	$CI->session->set_userdata('user', 'ada');
	$CI->session->set_userdata(array('role' => 'admin', 'n' => 3));
	assert_same('ada', $CI->session->userdata('user'));
	assert_same('admin', $CI->session->userdata('role'));
	assert_false($CI->session->userdata('absent'));
	$CI->session->unset_userdata('role');
	assert_false($CI->session->userdata('role'));
	// flash data becomes readable on the NEXT request; within this one it is only stored as 'new'
	$CI->session->set_flashdata('msg', 'saved');
	assert_false($CI->session->flashdata('msg'));
	assert_same('saved', $CI->session->userdata('flash:new:msg'));
	$all = $CI->session->all_userdata();
	assert_true(isset($all['session_id']));
	assert_same(32, strlen($all['session_id']));
	$CI->session->sess_destroy();
});

test('sha1: the legacy class keeps its historic output', function ()
{
	require_once BASEPATH.'libraries/Sha1.php';
	$s = new CI_SHA1();
	// CI's own (historic) implementation: the exact output is the contract
	assert_same('f739b2df26bbe9a9113542506eff36f5bdbef7f7727582733fffdb7e9761adde376fdfb4f934bb21', $s->generate('abc'));
});
