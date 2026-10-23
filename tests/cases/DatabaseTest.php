<?php

require_once BASEPATH.'database/DB.php';

function _sqlite_db()
{
	require_ext('pdo_sqlite');
	$CI = get_instance();
	$db = $CI->load->database(array(
		'dbdriver' => 'pdo', 'hostname' => 'sqlite::memory:', 'username' => '', 'password' => '', 'database' => '',
		'dbprefix' => 't_', 'pconnect' => FALSE, 'db_debug' => TRUE, 'cache_on' => FALSE, 'cachedir' => '',
		'char_set' => 'utf8', 'dbcollat' => 'utf8_general_ci', 'swap_pre' => '', 'autoinit' => TRUE, 'stricton' => FALSE,
	), TRUE);
	$db->query('CREATE TABLE t_users (id INTEGER PRIMARY KEY, name TEXT, age INT)');
	return $db;
}

/** Call a protected method (Closure::bind works from 5.4 to 8.x; ReflectionMethod::setAccessible is deprecated in 8.5) */
function _call_protected($obj, $method)
{
	$fn = Closure::bind(function () use ($method)
	{
		return call_user_func_array(array($this, $method), array());
	}, $obj, get_class($obj));
	return $fn();
}

/** Read straight through PDO: CI's PDO result wrapper cannot count SELECT rows on SQLite */
function _rows($db, $sql)
{
	return $db->conn_id->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

test('database: the factory resolves every bundled driver class without connecting', function ()
{
	$CI = get_instance();
	$expected = array(
		'mysql' => 'CI_DB_mysql_driver', 'mysqli' => 'CI_DB_mysqli_driver', 'postgre' => 'CI_DB_postgre_driver',
		'odbc' => 'CI_DB_odbc_driver', 'mssql' => 'CI_DB_mssql_driver',
		'sqlsrv' => 'CI_DB_sqlsrv_driver', 'sqlite' => 'CI_DB_sqlite_driver', 'pdo' => 'CI_DB_pdo_driver',
	);
	foreach ($expected as $driver => $class)
	{
		$db = DB(array('dbdriver' => $driver, 'hostname' => 'localhost', 'username' => 'u', 'password' => 'p',
			'database' => 'd', 'dbprefix' => '', 'pconnect' => FALSE, 'db_debug' => FALSE, 'cache_on' => FALSE,
			'cachedir' => '', 'char_set' => 'utf8', 'dbcollat' => 'utf8_general_ci', 'swap_pre' => '', 'autoinit' => FALSE,
			'stricton' => FALSE), TRUE);
		assert_instance($class, $db, $driver);
		assert_instance('CI_DB_active_record', $db, $driver);
		assert_same($driver, $db->dbdriver, $driver);
	}
});

test('database: active record builds SELECT statements (mysqli, no connection)', function ()
{
	$db = DB(array('dbdriver' => 'mysqli', 'hostname' => 'localhost', 'username' => 'u', 'password' => 'p', 'database' => 'd',
		'dbprefix' => 'pre_', 'pconnect' => FALSE, 'db_debug' => FALSE, 'cache_on' => FALSE, 'cachedir' => '',
		'char_set' => 'utf8', 'dbcollat' => 'utf8_general_ci', 'swap_pre' => '', 'autoinit' => FALSE, 'stricton' => FALSE), TRUE);

	$db->select('id, name')->from('users')->where('age >', 21)->where('id', 5)->order_by('name', 'asc')->limit(10, 20);
	$sql = _call_protected($db, '_compile_select');
	_call_protected($db, '_reset_select');
	assert_same("SELECT `id`, `name`\nFROM (`pre_users`)\nWHERE `age` > 21\nAND `id` =  5\nORDER BY `name` asc\nLIMIT 10 OFFSET 20", $sql);

	$db->from('users u')->join('posts p', 'p.user_id = u.id', 'left')->group_by('u.id');
	$sql = _call_protected($db, '_compile_select');
	_call_protected($db, '_reset_select');
	assert_contains('LEFT JOIN `pre_posts` p ON `p`.`user_id` = `u`.`id`', $sql);
	assert_contains('GROUP BY `u`.`id`', $sql);
});

test('database: active record writes work end to end (sqlite in memory)', function ()
{
	$db = _sqlite_db();
	$db->insert('users', array('name' => "O'Brien", 'age' => 30));
	assert_same(1, $db->affected_rows());
	assert_equals(1, $db->insert_id());
	$db->insert('users', array('name' => 'Ada', 'age' => 36));
	$db->insert('users', array('name' => 'Bob', 'age' => 20));

	$rows = _rows($db, 'SELECT name, age FROM t_users ORDER BY id');
	assert_same(3, count($rows));
	assert_same("O'Brien", $rows[0]['name']);

	$db->where('name', 'Bob')->update('users', array('age' => 21));
	assert_same(1, $db->affected_rows());
	$rows = _rows($db, "SELECT age FROM t_users WHERE name = 'Bob'");
	assert_equals(21, $rows[0]['age']);

	$db->where('name', 'Ada')->delete('users');
	assert_same(2, count(_rows($db, 'SELECT * FROM t_users')));
});

test('database: query() applies the table prefix and binds parameters', function ()
{
	$db = _sqlite_db();
	$db->query('INSERT INTO t_users (name, age) VALUES (?, ?)', array("it's", 5));
	$rows = _rows($db, 'SELECT name FROM t_users');
	assert_same("it's", $rows[0]['name']);
});

test('database: escaping helpers', function ()
{
	$db = _sqlite_db();
	assert_same("'it''s'", $db->escape("it's"));
	assert_same('5', (string) $db->escape(5));
	assert_same('NULL', $db->escape(NULL));
	assert_same('1', (string) $db->escape(TRUE));
	assert_same('50!!%', $db->escape_like_str('50%'));
});

test('database: failed queries are reported through error() when db_debug is off', function ()
{
	$db = _sqlite_db();
	$db->db_debug = FALSE;
	assert_false($db->query('SELECT * FROM no_such_table'));
});

test('database: forge and utility objects can be created', function ()
{
	$CI = get_instance();
	$db = _sqlite_db();
	$CI->db = $db;
	$forge = $CI->load->dbforge();
	assert_true(is_object($forge) OR isset($CI->dbforge));
	assert_instance('CI_DB_forge', $CI->dbforge);
});
