<?php

test('boot: welcome controller renders through the real front controller', function ()
{
	assert_contains('Welcome to CodeIgniter', $GLOBALS['__boot_output']);
	assert_true(defined('CI_VERSION'));
	assert_instance('CI_Controller', get_instance());
});
