<?php

test('profiler: loads and compiles every section by default', function ()
{
	$CI = get_instance();
	$CI->load->library('profiler');
	$out = $CI->profiler->run();
	assert_contains('BENCHMARKS', $out);
	assert_contains('MEMORY USAGE', $out);
	assert_contains('HTTP HEADERS', $out);
	assert_contains('CLASS/METHOD', $out);
});

test('profiler: sections can be switched off via set_sections()', function ()
{
	$CI = get_instance();
	$CI->load->library('profiler');
	$CI->profiler->set_sections(array('benchmarks' => FALSE, 'memory_usage' => FALSE));
	$out = $CI->profiler->run();
	assert_not_contains('BENCHMARKS', $out);
	assert_not_contains('MEMORY USAGE', $out);
	assert_contains('HTTP HEADERS', $out);
});

test('profiler: constructor config disables sections', function ()
{
	require_once BASEPATH.'libraries/Profiler.php';
	$p = new CI_Profiler(array('http_headers' => FALSE, 'config' => FALSE, 'session_data' => FALSE));
	$out = $p->run();
	assert_not_contains('HTTP HEADERS', $out);
	assert_not_contains('CONFIG VARIABLES', $out);
	assert_contains('BENCHMARKS', $out);
});
