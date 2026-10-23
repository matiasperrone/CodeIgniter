<?php

test('captcha helper: creates a jpeg image and returns word, time and tag', function ()
{
	require_ext('gd');
	$CI = get_instance();
	$CI->load->helper('captcha');
	$dir = TEST_TMP.'captcha/';
	@mkdir($dir);
	$cap = create_captcha(array(
		'img_path' => $dir, 'img_url' => 'http://example.com/captcha/', 'word' => 'AB12cd89',
		'img_width' => 120, 'img_height' => 40, 'expiration' => 7200,
	));
	assert_true(is_array($cap));
	$keys = array_keys($cap);
	sort($keys);
	assert_same(array('image', 'time', 'word'), $keys);
	// NOTE: with no explicit 'word' the 2.2.6 helper yields an empty word ($word_length
	// is never defaulted); only the explicit-word path is part of the contract.
	assert_same('AB12cd89', $cap['word']);
	assert_contains('<img src="http://example.com/captcha/'.$cap['time'].'.jpg"', $cap['image']);
	$info = getimagesize($dir.$cap['time'].'.jpg');
	assert_same(120, $info[0]);
	assert_same(40, $info[1]);
	assert_same('image/jpeg', $info['mime']);
	@unlink($dir.$cap['time'].'.jpg');
});

test('captcha helper: the random byte source returns the requested length', function ()
{
	require_ext('gd');
	get_instance()->load->helper('captcha');
	foreach (array(1, 8, 32) as $n)
	{
		$b = _ci_captcha_get_random_bytes($n);
		assert_true(is_string($b), 'bytes');
		assert_same($n, strlen($b));
	}
	assert_true(_ci_captcha_get_random_bytes(16) !== _ci_captcha_get_random_bytes(16));
});

test('image_lib: resize a png with GD', function ()
{
	require_ext('gd');
	$src = TEST_TMP.'src.png';
	$im = imagecreatetruecolor(200, 100);
	imagefill($im, 0, 0, imagecolorallocate($im, 200, 30, 30));
	imagepng($im, $src);
	if (PHP_VERSION_ID < 80000) { imagedestroy($im); } // no-op since 8.0, deprecated in 8.5

	$CI = get_instance();
	$CI->load->library('image_lib', array(
		'image_library' => 'gd2', 'source_image' => $src, 'new_image' => TEST_TMP.'thumb.png',
		'maintain_ratio' => TRUE, 'width' => 50, 'height' => 50,
	));
	assert_true($CI->image_lib->resize());
	$info = getimagesize(TEST_TMP.'thumb.png');
	assert_same(50, $info[0]);
	assert_same(25, $info[1]);
});

test('image_lib: crop and rotate', function ()
{
	require_ext('gd');
	$src = TEST_TMP.'src2.png';
	$im = imagecreatetruecolor(100, 60);
	imagepng($im, $src);
	if (PHP_VERSION_ID < 80000) { imagedestroy($im); } // no-op since 8.0, deprecated in 8.5

	require_once BASEPATH.'libraries/Image_lib.php';
	$lib = new CI_Image_lib(array('image_library' => 'gd2', 'source_image' => $src, 'new_image' => TEST_TMP.'crop.png',
		'x_axis' => 10, 'y_axis' => 10, 'width' => 40, 'height' => 20, 'maintain_ratio' => FALSE));
	assert_true($lib->crop());
	$info = getimagesize(TEST_TMP.'crop.png');
	assert_same(40, $info[0]);
	assert_same(20, $info[1]);

	$lib = new CI_Image_lib(array('image_library' => 'gd2', 'source_image' => $src, 'new_image' => TEST_TMP.'rot.png', 'rotation_angle' => '90'));
	assert_true($lib->rotate());
	$info = getimagesize(TEST_TMP.'rot.png');
	assert_same(60, $info[0]);
	assert_same(100, $info[1]);
});
