<?php

function _h()
{
	$CI = get_instance();
	$args = func_get_args();
	foreach ($args as $h)
	{
		$CI->load->helper($h);
	}
}

test('array_helper: element, elements, random_element', function ()
{
	_h('array');
	$a = array('a' => 1, 'b' => 2);
	assert_same(1, element('a', $a));
	assert_false(element('z', $a));
	assert_same('d', element('z', $a, 'd'));
	assert_same(array('a' => 1, 'z' => FALSE), elements(array('a', 'z'), $a));
	assert_same(array('a' => 1, 'z' => 'dflt'), elements(array('a', 'z'), $a, 'dflt'));
	assert_true(in_array(random_element(array(1, 2, 3)), array(1, 2, 3)));
});

test('string_helper: random_string, alternator, repeater, trimming and quotes', function ()
{
	_h('string');
	assert_same(12, strlen(random_string('alnum', 12)));
	assert_same(1, preg_match('/^[0-9]{8}$/', random_string('numeric', 8)));
	assert_same(1, preg_match('/^[0-9a-f]{32}$/', random_string('unique')));
	assert_same(40, strlen(random_string('sha1')));
	assert_same('aaa', repeater('a', 3));
	assert_same('foo/bar', trim_slashes('/foo/bar/'));
	assert_same('foo/bar/', reduce_double_slashes('foo//bar//'));
	assert_same('a b', reduce_multiples('a    b', ' '));
	assert_same('hi', strip_quotes('"hi"'));
	assert_same('&quot;hi&quot; &#39;x&#39;', quotes_to_entities('"hi" \'x\''));
	assert_same('/a/b/', reduce_double_slashes('//a//b//'));
	assert_same('one', alternator('one', 'two'));
	assert_same('two', alternator('one', 'two'));
	assert_same('one', alternator('one', 'two'));
	alternator();
	assert_same('a_1', increment_string('a'));
	assert_same('file_5', increment_string('file_4'));
});

test('text_helper: limiters, ellipsize, word_wrap, highlight_phrase', function ()
{
	_h('text');
	assert_same('one two&#8230;', word_limiter('one two three four', 2));
	assert_same('one two three four', word_limiter('one two three four', 10));
	assert_same('The quick&#8230;', character_limiter('The quick brown fox', 10));
	assert_same('The&hellip;fox', ellipsize('The quick brown fox', 6, .5));
	assert_same('The quick &hellip;', ellipsize('The quick brown fox', 10));
	$wrapped = word_wrap(str_repeat('word ', 20), 30);
	foreach (explode("\n", $wrapped) as $l)
	{
		assert_true(strlen($l) <= 31, 'wrap: '.$l);
	}
	assert_same('A <strong>b</strong> c', highlight_phrase('A b c', 'b', '<strong>', '</strong>'));
	assert_same('Cafe', convert_accented_characters("Caf\xc3\xa9"));
	assert_same('caf&#233;', ascii_to_entities("caf\xc3\xa9"));
	assert_same('ab', ascii_to_entities('ab'));
	assert_same('ab', entities_to_ascii('&#97;&#98;'));
});

test('url_helper: site_url, base_url, anchor, url_title, prep_url', function ()
{
	_h('url');
	$c = get_instance()->config;
	$old = array($c->item('base_url'), $c->item('index_page'));
	$c->set_item('base_url', 'http://example.com/');
	$c->set_item('index_page', 'index.php');
	assert_same('http://example.com/index.php/a/b', site_url('a/b'));
	assert_same('http://example.com/x.css', base_url('x.css'));
	assert_same('<a href="http://example.com/index.php/a">T</a>', anchor('a', 'T'));
	assert_same('<a href="http://x.com/" class="c">X</a>', anchor('http://x.com/', 'X', 'class="c"'));
	assert_same('my-title-here', url_title('My Title, Here!', 'dash', TRUE));
	assert_same('My_Title_Here', url_title('My Title Here', 'underscore'));
	assert_same('http://example.com', prep_url('example.com'));
	assert_same('http://example.com', prep_url('http://example.com'));
	assert_same('<a href="http://a.com">http://a.com</a>', auto_link('http://a.com', 'url', FALSE));
	assert_contains('<script type="text/javascript">', safe_mailto('a@b.com'));
	assert_same('a-b', url_title('a  b'));
	$c->set_item('base_url', $old[0]);
	$c->set_item('index_page', $old[1]);
});

test('form_helper: open/close, input, dropdown, checkbox, labels, set_value', function ()
{
	_h('url', 'form');
	$c = get_instance()->config;
	$c->set_item('base_url', 'http://example.com/');
	$c->set_item('index_page', 'index.php');
	$_POST = array('field' => 'posted <b>', 'sel' => 'b', 'chk' => 'yes');
	$open = form_open('contact/send', array('class' => 'f'));
	assert_contains('<form action="http://example.com/index.php/contact/send"', $open);
	assert_contains('method="post"', $open);
	assert_contains('class="f"', $open);
	assert_same('</form>', form_close());
	assert_same('</form></div>', form_close('</div>'));
	// the first read of a field is escaped; later reads of the same field are assumed to be prepped already
	assert_same('posted &lt;b&gt;', set_value('field'));
	assert_contains('name="field"', form_input('field', 'v'));
	assert_contains('value="v"', form_input('field', 'v'));
	assert_same('dflt', set_value('nothing', 'dflt'));
	$dd = form_dropdown('sel', array('a' => 'A', 'b' => 'B'), 'a');
	assert_contains('<option value="a" selected="selected">A</option>', $dd);
	assert_contains('<select name="sel">', $dd);
	assert_contains('selected="selected"', form_dropdown('sel', array('a' => 'A', 'b' => 'B'), set_value('sel')));
	assert_contains('checked="checked"', form_checkbox('chk', 'yes', TRUE));
	assert_true(set_checkbox('chk', 'yes') !== '');
	assert_contains('<label for="x">Lbl</label>', form_label('Lbl', 'x'));
	assert_contains('<textarea', form_textarea('t', 'body'));
	assert_contains('type="submit"', form_submit('go', 'Go'));
	assert_contains('type="hidden"', form_hidden('h', 'v'));
	assert_contains('multipart/form-data', form_open_multipart('up'));
	assert_same('a &amp; b', form_prep('a & b'));
});

test('html_helper: heading, lists, br, nbs, img, link_tag, meta', function ()
{
	_h('url', 'html');
	assert_same('<h2>T</h2>', heading('T', 2));
	assert_same('<h1 class="x">T</h1>', heading('T', 1, 'class="x"'));
	assert_same("<ul>\n  <li>a</li>\n  <li>b</li>\n</ul>\n", ul(array('a', 'b')));
	assert_contains('<ol', ol(array('a')));
	assert_same('<br /><br />', br(2));
	assert_same('&nbsp;&nbsp;&nbsp;', nbs(3));
	assert_contains('<img src="http://example.com/img/a.png"', img('http://example.com/img/a.png'));
	assert_contains('rel="stylesheet"', link_tag('http://example.com/s.css'));
	assert_contains('<meta name="description" content="d" />', meta('description', 'd'));
	assert_contains('<!DOCTYPE', doctype('html5'));
});

test('date_helper: now, mdate, standard_date, timespan, days_in_month, conversions', function ()
{
	_h('date');
	assert_true(abs(now() - time()) < 3);
	assert_same('2023-11-14 22:13:20', mdate('%Y-%m-%d %H:%i:%s', 1700000000));
	assert_same('Tue, 14 Nov 23 22:13:20 +0000', standard_date('DATE_RFC822', 1700000000));
	assert_same('Tue, 14 Nov 2023 22:13:20 +0000', standard_date('DATE_RFC1123', 1700000000));
	assert_same('Tue, 14 Nov 2023 22:13:20 +0000', standard_date('DATE_RSS', 1700000000));
	assert_same('Tuesday, 14-Nov-23 22:13:20 UTC', standard_date('DATE_COOKIE', 1700000000));
	assert_false(standard_date('DATE_NOT_A_FORMAT', 1700000000));
	assert_same(29, days_in_month(2, 2024));
	assert_same(28, days_in_month(2, 2023));
	assert_same(1700000000, mysql_to_unix('2023-11-14 22:13:20'));
	assert_same('2023-11-14 10:13 PM', unix_to_human(1700000000));
	assert_same('2023-11-14 10:13:20 PM', unix_to_human(1700000000, TRUE, 'us'));
	assert_same('2023-11-14 22:13:20', unix_to_human(1700000000, TRUE, 'eu'));
	assert_same(1700000000, human_to_unix('2023-11-14 10:13:20 PM'));
	assert_same('1 Hour, 30 Minutes', timespan(1000, 6400));
	assert_same('2 Days', timespan(0, 172800));
	assert_same(1700000000, local_to_gmt(1700000000));
	assert_equals(0, timezones('UTC'));
	assert_equals(-5, timezones('UM5'));
});

test('number_helper: byte_format', function ()
{
	_h('number');
	assert_same('512 Bytes', byte_format(512));
	assert_same('1.0 KB', byte_format(1024));
	assert_same('1.5 MB', byte_format(1572864));
	assert_same('1.00 GB', byte_format(1073741824, 2));
	assert_same('0 Bytes', byte_format(0));
});

test('inflector_helper: singular, plural, camelize, underscore, humanize', function ()
{
	_h('inflector');
	assert_same('dog', singular('dogs'));
	assert_same('city', singular('cities'));
	assert_same('dogs', plural('dog'));
	assert_same('cities', plural('city'));
	assert_same('boxes', plural('box'));
	assert_same('myFieldName', camelize('my_field_name'));
	assert_same('my_field_name', underscore('my field name'));
	assert_same('My Field Name', humanize('my_field_name'));
});

test('file_helper: read/write/delete, names, mime types, permissions', function ()
{
	_h('file');
	$f = TEST_TMP.'h.txt';
	assert_true(write_file($f, 'hello'));
	assert_same('hello', read_file($f));
	assert_true(write_file($f, ' world', FOPEN_WRITE_CREATE));
	assert_same('hello world', read_file($f));
	assert_false(@read_file(TEST_TMP.'nope.txt'));
	assert_contains('h.txt', implode(',', get_filenames(TEST_TMP)));
	$info = get_file_info($f, array('name', 'size'));
	assert_same('h.txt', $info['name']);
	assert_same(11, (int) $info['size']);
	assert_same('text/plain', get_mime_by_extension('x.txt'));
	assert_same('image/jpeg', get_mime_by_extension('x.jpg'));
	assert_false(get_mime_by_extension('x.unknownext'));
	assert_same('-rw-r--r--', symbolic_permissions(0100644));
	assert_same('755', octal_permissions(0100755));
	assert_true(unlink($f));
});

test('directory_helper: directory_map', function ()
{
	_h('directory');
	@mkdir(TEST_TMP.'d1');
	@mkdir(TEST_TMP.'d1/sub');
	file_put_contents(TEST_TMP.'d1/a.txt', 'a');
	file_put_contents(TEST_TMP.'d1/sub/b.txt', 'b');
	$map = directory_map(TEST_TMP.'d1');
	assert_same(array('b.txt'), $map['sub']);
	assert_true(in_array('a.txt', $map));
	$flat = directory_map(TEST_TMP.'d1', 1);
	sort($flat);
	assert_same(array('a.txt', 'sub'), $flat);
});

test('path_helper: set_realpath', function ()
{
	_h('path');
	assert_same(realpath(TEST_TMP).'/', set_realpath(TEST_TMP));
	assert_same(realpath(TEST_TMP).'/', set_realpath(TEST_TMP, FALSE));
});

test('security_helper: xss_clean, sanitize_filename, do_hash, strip_image_tags, encode_php_tags', function ()
{
	_h('security');
	assert_not_contains('<script', xss_clean('<script>x</script>'));
	assert_same('a.txt', sanitize_filename('a.txt'));
	assert_same(sha1('abc'), do_hash('abc'));
	assert_same(md5('abc'), do_hash('abc', 'md5'));
	assert_same('http://x.com/a.png', strip_image_tags('<img src="http://x.com/a.png" />'));
	assert_same('&lt;?php echo 1; ?&gt;', encode_php_tags('<?php echo 1; ?>'));
});

test('xml_helper: xml_convert', function ()
{
	_h('xml');
	assert_same('a &lt; b &amp; c &gt; d &quot;e&quot; &apos;f&apos;', xml_convert('a < b & c > d "e" \'f\''));
	assert_same('Tom &amp; Jerry', xml_convert('Tom & Jerry'));
});

test('typography_helper & language_helper & email_helper', function ()
{
	_h('typography', 'language', 'email');
	assert_same("a<br />\nb", nl2br_except_pre("a\nb"));
	get_instance()->lang->load('form_validation');
	assert_contains('for="x"', lang('required', 'x'));
	assert_true(valid_email('a@b.co'));
	assert_false(valid_email('nope'));
});

test('cookie_helper: set_cookie / get_cookie / delete_cookie', function ()
{
	_h('cookie');
	$_COOKIE['ci_test'] = 'v';
	assert_same('v', get_cookie('ci_test'));
	assert_false(get_cookie('missing'));
	assert_same(NULL, set_cookie('a', 'b', 60));
	assert_same(NULL, delete_cookie('a'));
});

test('smiley_helper: parse_smileys replaces known smileys', function ()
{
	_h('url', 'smiley');
	$out = parse_smileys(':-) test', 'http://example.com/smileys/', array(':-)' => array('grin.gif', '19', '19', 'grin')));
	assert_contains('<img src="http://example.com/smileys/grin.gif"', $out);
	assert_contains('alt="grin"', $out);
});

test('inflector/text: ascii_to_entities round trip is lossless for ascii', function ()
{
	_h('text');
	assert_same('hello', entities_to_ascii(ascii_to_entities('hello')));
});
