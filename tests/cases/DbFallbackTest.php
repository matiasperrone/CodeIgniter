<?php

/**
 * 'mssql' falls back to 'sqlsrv' when the removed mssql extension is
 * missing. sqlsrv is a Microsoft PECL extension that is not installed
 * here, so a stand-in sqlsrv_connect() is declared. This file runs in its
 * own process, so the stand-in cannot leak into the other cases.
 */

if ( ! function_exists('mssql_connect') && ! function_exists('sqlsrv_connect'))
{
	function sqlsrv_connect($server, $info)
	{
		return array('server' => $server, 'info' => $info);
	}
}

function _mssql_params($extra = array())
{
	return array_merge(array('dbdriver' => 'mssql', 'hostname' => 'dbhost', 'username' => 'u', 'password' => 'p',
		'database' => 'd', 'dbprefix' => '', 'pconnect' => FALSE, 'db_debug' => FALSE, 'cache_on' => FALSE,
		'cachedir' => '', 'char_set' => 'utf8', 'dbcollat' => 'utf8_general_ci', 'swap_pre' => '', 'autoinit' => FALSE,
		'stricton' => FALSE), $extra);
}

function _skip_unless_fallback()
{
	if (function_exists('mssql_connect'))
	{
		skip('the mssql extension is present, no fallback happens');
	}
}

test('db fallback: mssql uses the sqlsrv driver when the mssql extension is gone', function ()
{
	_skip_unless_fallback();
	require_once BASEPATH.'database/DB.php';
	$db = DB(_mssql_params(), TRUE);
	assert_instance('CI_DB_sqlsrv_driver', $db);
	assert_same('sqlsrv', $db->dbdriver);
});

test('db fallback: host:port becomes host,port, other hosts are left alone', function ()
{
	_skip_unless_fallback();
	require_once BASEPATH.'database/DB.php';
	$cases = array(
		'dbhost:1433'			=> 'dbhost,1433',
		'10.0.0.5:1434'			=> '10.0.0.5,1434',
		'dbhost,1433'			=> 'dbhost,1433',
		'dbhost'				=> 'dbhost',
		'dbhost\\SQLEXPRESS'	=> 'dbhost\\SQLEXPRESS',
		''						=> '',
	);
	foreach ($cases as $in => $out)
	{
		$db = DB(_mssql_params(array('hostname' => $in)), TRUE);
		assert_same($out, $db->hostname, 'hostname "'.$in.'"');
	}
});

test('db fallback: it also works with the DSN string notation', function ()
{
	_skip_unless_fallback();
	require_once BASEPATH.'database/DB.php';
	$db = DB('mssql://u:p@dbhost:1433/d?autoinit=FALSE', TRUE);
	assert_instance('CI_DB_sqlsrv_driver', $db);
	assert_same('d', $db->database);
});

test('db fallback: the sqlsrv connection is actually used, including pconnect', function ()
{
	_skip_unless_fallback();
	require_once BASEPATH.'database/DB.php';
	$db = DB(_mssql_params(array('hostname' => 'dbhost:1433')), TRUE);

	$conn = $db->db_connect();
	assert_same('dbhost,1433', $conn['server']);
	assert_same('u', $conn['info']['UID']);
	assert_same('d', $conn['info']['Database']);
	assert_same(0, $conn['info']['ConnectionPooling']);

	$conn = $db->db_pconnect();
	assert_true(is_array($conn), 'db_pconnect() must return the connection');
	assert_same(1, $conn['info']['ConnectionPooling']);
});

test('db fallback: empty credentials mean Windows Authentication', function ()
{
	_skip_unless_fallback();
	require_once BASEPATH.'database/DB.php';
	$db = DB(_mssql_params(array('username' => '', 'password' => '')), TRUE);
	$conn = $db->db_connect();
	assert_false(isset($conn['info']['UID']));
	assert_false(isset($conn['info']['PWD']));
});

test('db fallback: sqlsrv itself and the other drivers are not rewritten', function ()
{
	require_once BASEPATH.'database/DB.php';
	$db = DB(_mssql_params(array('dbdriver' => 'sqlsrv', 'hostname' => 'dbhost:1433')), TRUE);
	assert_same('sqlsrv', $db->dbdriver);
	assert_same('dbhost:1433', $db->hostname);
	$db = DB(_mssql_params(array('dbdriver' => 'mysqli', 'hostname' => 'dbhost:3306')), TRUE);
	assert_same('mysqli', $db->dbdriver);
	assert_same('dbhost:3306', $db->hostname);
});
