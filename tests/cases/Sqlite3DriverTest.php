<?php

require_once BASEPATH.'database/DB.php';

/**
 * Open an SQLite3 database through the factory
 *
 * @param	string	file path or ':memory:'
 * @param	array	parameters to override
 */
function _s3($file = ':memory:', $extra = array())
{
	require_ext('sqlite3');
	return DB(array_merge(array(
		'dbdriver' => 'sqlite3', 'hostname' => '', 'username' => '', 'password' => '', 'database' => $file,
		'dbprefix' => 't_', 'pconnect' => FALSE, 'db_debug' => FALSE, 'cache_on' => FALSE, 'cachedir' => '',
		'char_set' => 'utf8', 'dbcollat' => 'utf8_general_ci', 'swap_pre' => '', 'autoinit' => TRUE, 'stricton' => FALSE,
	), $extra), TRUE);
}

/** A database with a small, known "users" table */
function _s3_users()
{
	$db = _s3();
	$db->query('CREATE TABLE t_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, age INT, city TEXT)');
	$db->insert_batch('users', array(
		array('name' => 'Ada',   'age' => 36, 'city' => 'London'),
		array('name' => 'Bob',   'age' => 20, 'city' => 'Paris'),
		array('name' => 'Cleo',  'age' => 28, 'city' => 'London'),
		array('name' => 'Dan',   'age' => 41, 'city' => 'Rome'),
	));
	return $db;
}

// ---- factory / connection -------------------------------------------------

test('sqlite3: the factory returns the sqlite3 driver with Active Record', function ()
{
	$db = _s3();
	assert_instance('CI_DB_sqlite3_driver', $db);
	assert_instance('CI_DB_active_record', $db);
	assert_same('sqlite3', $db->dbdriver);
	assert_same('sqlite3', $db->platform());
	assert_instance('SQLite3', $db->conn_id);
});

test('sqlite3: version() reports the SQLite library version', function ()
{
	$db = _s3();
	assert_same(1, preg_match('/^3\.\d+\.\d+/', $db->version()));
	$v = SQLite3::version();
	assert_same($v['versionString'], $db->version());
});

test('sqlite3: connecting to an impossible path fails quietly with db_debug off', function ()
{
	$db = _s3(TEST_TMP.'no/such/dir/x.db');
	assert_false($db->conn_id);
});

test('sqlite3: a database file is created, persists and can be reopened', function ()
{
	$file = TEST_TMP.'persist.db';
	@unlink($file);
	$db = _s3($file);
	assert_true(file_exists($file));
	$db->query('CREATE TABLE t_k (v TEXT)');
	$db->insert('k', array('v' => 'kept'));
	$db->close();

	$db2 = _s3($file);
	$rows = $db2->get('k')->result_array();
	assert_same(array(array('v' => 'kept')), $rows);
	$db2->close();
});

test('sqlite3: pconnect works (the extension has no persistent connections)', function ()
{
	$db = _s3(':memory:', array('pconnect' => TRUE));
	assert_instance('SQLite3', $db->conn_id);
	assert_true($db->query('CREATE TABLE t_a (x INT)'));
});

// ---- writes -----------------------------------------------------------------

test('sqlite3: insert / update / delete report affected rows and the insert id', function ()
{
	$db = _s3_users();
	assert_true($db->insert('users', array('name' => 'Eve', 'age' => 30, 'city' => 'Oslo')));
	assert_same(1, $db->affected_rows());
	assert_same(5, $db->insert_id());

	assert_true($db->where('city', 'London')->update('users', array('age' => 50)));
	assert_same(2, $db->affected_rows());

	assert_true($db->where('age >', 40)->delete('users'));
	assert_same(3, $db->affected_rows());
	assert_same(2, $db->count_all('users'));
});

test('sqlite3: write queries return TRUE, reads return a result object', function ()
{
	$db = _s3_users();
	assert_true($db->query("UPDATE t_users SET age = 1 WHERE name = 'Ada'"));
	assert_instance('CI_DB_sqlite3_result', $db->query('SELECT * FROM t_users'));
});

test('sqlite3: insert_batch, update_batch and replace', function ()
{
	$db = _s3_users();
	$db->insert_batch('users', array(array('name' => 'Fay', 'age' => 1, 'city' => 'X'), array('name' => 'Gus', 'age' => 2, 'city' => 'Y')));
	assert_same(6, $db->count_all('users'));

	$db->update_batch('users', array(array('id' => 1, 'age' => 99), array('id' => 2, 'age' => 98)), 'id');
	$r = $db->select('id, age')->where_in('id', array(1, 2))->order_by('id')->get('users')->result_array();
	assert_equals(99, $r[0]['age']);
	assert_equals(98, $r[1]['age']);

	$db->replace('users', array('id' => 1, 'name' => 'Ada2', 'age' => 5, 'city' => 'Z'));
	$row = $db->where('id', 1)->get('users')->row();
	assert_same('Ada2', $row->name);
	assert_same(6, $db->count_all('users'));
});

test('sqlite3: empty_table / truncate remove every row', function ()
{
	$db = _s3_users();
	$db->empty_table('users');
	assert_same(0, $db->count_all('users'));
	$db = _s3_users();
	$db->truncate('users');
	assert_same(0, $db->count_all('users'));
});

// ---- reads and the result object ---------------------------------------------

test('sqlite3: select with where / order / limit builds and runs the expected SQL', function ()
{
	$db = _s3_users();
	$q = $db->select('name, age')->from('users')->where('age >', 21)->order_by('age', 'desc')->limit(2)->get();
	assert_same("SELECT name, age\nFROM (t_users)\nWHERE age > 21\nORDER BY age desc\nLIMIT 2", $db->last_query());
	assert_same(array(array('name' => 'Dan', 'age' => 41), array('name' => 'Ada', 'age' => 36)), $q->result_array());
});

test('sqlite3: limit with an offset', function ()
{
	$db = _s3_users();
	$q = $db->order_by('id')->get('users', 2, 1);
	assert_contains('LIMIT 1, 2', $db->last_query());
	$names = array();
	foreach ($q->result() as $r) { $names[] = $r->name; }
	assert_same(array('Bob', 'Cleo'), $names);
});

test('sqlite3: result accessors (num_rows, row, first/last/next/previous, objects and arrays)', function ()
{
	$db = _s3_users();
	$q = $db->order_by('id')->get('users');
	assert_same(4, $q->num_rows());
	assert_same(4, $q->num_fields());
	assert_same(array('id', 'name', 'age', 'city'), $q->list_fields());

	assert_same('Ada', $q->row()->name);
	assert_same('Cleo', $q->row(2)->name);
	$arr3 = $q->row_array(3);
	assert_same('Dan', $arr3['name']);
	$arr = $q->row_array(1);
	assert_same('Bob', $arr['name']);
	assert_same('Ada', $q->first_row()->name);
	assert_same('Dan', $q->last_row()->name);
	$first = $q->first_row('array');
	assert_same('Ada', $first['name']);

	$q->row(0);
	assert_same('Bob', $q->next_row()->name);
	assert_same('Cleo', $q->next_row()->name);
	assert_same('Bob', $q->previous_row()->name);

	$all = $q->result();
	assert_same(4, count($all));
	assert_instance('stdClass', $all[0]);
	assert_same('Dan', $all[3]->name);
	$arrays = $q->result_array();
	assert_same(36, (int) $arrays[0]['age']);
	// asking twice gives the same data
	assert_same($arrays, $q->result_array());
	assert_same(4, count($q->result('array')));
});

test('sqlite3: custom result objects', function ()
{
	if ( ! class_exists('S3_User', FALSE))
	{
		eval('class S3_User { public $name; public $age; public function shout() { return strtoupper($this->name); } }');
	}
	$db = _s3_users();
	$rows = $db->select('name, age')->order_by('id')->get('users')->result('S3_User');
	assert_instance('S3_User', $rows[0]);
	assert_same('ADA', $rows[0]->shout());
});

test('sqlite3: an empty result is handled', function ()
{
	$db = _s3_users();
	$q = $db->where('age >', 1000)->get('users');
	assert_same(0, $q->num_rows());
	assert_same(array(), $q->result());
	assert_same(array(), $q->result_array());
	assert_same(array(), $q->row_array());
	assert_same(4, $q->num_fields());
});

test('sqlite3: independent results can be read in an interleaved fashion', function ()
{
	$db = _s3_users();
	$a = $db->order_by('id')->get('users');
	$b = $db->order_by('id', 'desc')->get('users');
	assert_same('Ada', $a->row()->name);
	assert_same('Dan', $b->row()->name);
	assert_same('Bob', $a->next_row()->name);
	assert_same('Cleo', $b->next_row()->name);
});

test('sqlite3: free_result releases the statement', function ()
{
	$db = _s3_users();
	$q = $db->get('users');
	assert_same(4, $q->num_rows());
	$q->free_result();
	assert_same(NULL, $q->result_id);
	assert_same(0, $q->num_rows());
});

test('sqlite3: a larger result set', function ()
{
	$db = _s3();
	$db->query('CREATE TABLE t_big (n INT, s TEXT)');
	$db->trans_start();
	for ($i = 0; $i < 3000; $i++)
	{
		$db->insert('big', array('n' => $i, 's' => 'row '.$i));
	}
	$db->trans_complete();
	$q = $db->order_by('n')->get('big');
	assert_same(3000, $q->num_rows());
	$rows = $q->result_array();
	assert_same('row 2999', $rows[2999]['s']);
});

test('sqlite3: column types come back as SQLite stores them', function ()
{
	$db = _s3();
	$db->query('CREATE TABLE t_ty (i INT, f REAL, s TEXT, n TEXT)');
	$db->insert('ty', array('i' => 7, 'f' => 1.5, 's' => 'x', 'n' => NULL));
	$r = $db->get('ty')->row_array();
	assert_same(7, $r['i']);
	assert_same(1.5, $r['f']);
	assert_same('x', $r['s']);
	assert_same(NULL, $r['n']);
});

test('sqlite3: unicode round trip', function ()
{
	$db = _s3();
	$db->query('CREATE TABLE t_u (v TEXT)');
	$text = "Caf\xc3\xa9 \xe6\x97\xa5\xe6\x9c\xac\xe8\xaa\x9e \xf0\x9f\x98\x80";
	$db->insert('u', array('v' => $text));
	$r = $db->get('u')->row();
	assert_same($text, $r->v);
});

// ---- query building --------------------------------------------------------

test('sqlite3: Active Record clauses run (like, or_where, where_in, join, group_by, having, distinct, count)', function ()
{
	$db = _s3_users();
	assert_same(2, $db->like('name', 'a')->count_all_results('users'));
	assert_same(2, $db->where('city', 'London')->count_all_results('users'));
	$names = array();
	foreach ($db->where('city', 'Rome')->or_where('age', 20)->order_by('id')->get('users')->result() as $r) { $names[] = $r->name; }
	assert_same(array('Bob', 'Dan'), $names);
	assert_same(2, $db->where_in('id', array(1, 4))->get('users')->num_rows());
	assert_same(3, $db->distinct()->select('city')->get('users')->num_rows());

	$g = $db->select('city, COUNT(*) AS n')->group_by('city')->having('n >', 1)->get('users')->result_array();
	assert_same(array(array('city' => 'London', 'n' => 2)), $g);

	$db->query('CREATE TABLE t_pets (owner_id INT, pet TEXT)');
	$db->insert('pets', array('owner_id' => 1, 'pet' => 'cat'));
	$j = $db->select('users.name, pets.pet')->from('users')->join('pets', 'pets.owner_id = users.id')->get()->result_array();
	assert_same(array(array('name' => 'Ada', 'pet' => 'cat')), $j);
});

test('sqlite3: random ordering uses SQLite Random()', function ()
{
	$db = _s3_users();
	$db->order_by('id', 'random')->get('users');
	assert_contains('Random()', $db->last_query());
});

test('sqlite3: count_all counts a table and is 0 without a table name', function ()
{
	$db = _s3_users();
	assert_same(4, $db->count_all('users'));
	assert_same(0, $db->count_all(''));
});

// ---- escaping -----------------------------------------------------------------

test('sqlite3: escaping', function ()
{
	$db = _s3();
	assert_same("'it''s'", $db->escape("it's"));
	assert_same('5', (string) $db->escape(5));
	assert_same('NULL', $db->escape(NULL));
	assert_same('1', (string) $db->escape(TRUE));
	assert_same('0', (string) $db->escape(FALSE));
	assert_same("it''s", $db->escape_str("it's"));
	assert_same(array("a''b", 'c'), $db->escape_str(array("a'b", 'c')));
	assert_same('50!%', $db->escape_like_str('50%'));
	assert_same('a!_b', $db->escape_like_str('a_b'));
	assert_same('wow!!', $db->escape_like_str('wow!'));
	assert_same('!!!%!_', $db->escape_like_str('!%_'));
});

test('sqlite3: quotes and injection attempts are stored as data', function ()
{
	$db = _s3_users();
	$evil = "x'; DROP TABLE t_users; --";
	$db->insert('users', array('name' => $evil, 'age' => 1, 'city' => 'c'));
	assert_same(5, $db->count_all('users'));
	assert_same($evil, $db->where('age', 1)->get('users')->row()->name);

	$db->query('SELECT * FROM t_users WHERE name = ?', array($evil));
	assert_same(1, $db->query('SELECT * FROM t_users WHERE name = ?', array($evil))->num_rows());
	assert_same(0, $db->query('SELECT * FROM t_users WHERE name = ?', array("o'brien"))->num_rows());
});

test('sqlite3: like() with wildcards in the search term', function ()
{
	$db = _s3();
	$db->query('CREATE TABLE t_p (v TEXT)');
	$db->insert_batch('p', array(array('v' => '50% off'), array('v' => '500 off'), array('v' => 'a_b'), array('v' => 'axb')));
	assert_same(1, $db->like('v', '50%')->count_all_results('p'));
	assert_same(1, $db->like('v', 'a_b')->count_all_results('p'));
});

// ---- transactions -------------------------------------------------------------

test('sqlite3: trans_start / trans_complete commit', function ()
{
	$db = _s3_users();
	$db->trans_start();
	$db->insert('users', array('name' => 'T1', 'age' => 1, 'city' => 'c'));
	$db->insert('users', array('name' => 'T2', 'age' => 2, 'city' => 'c'));
	$db->trans_complete();
	assert_true($db->trans_status());
	assert_same(6, $db->count_all('users'));
});

test('sqlite3: a failing query inside trans_start rolls everything back', function ()
{
	$db = _s3_users();
	$db->trans_start();
	$db->insert('users', array('name' => 'T1', 'age' => 1, 'city' => 'c'));
	$db->query('INSERT INTO no_such_table VALUES (1)');
	$db->trans_complete();
	assert_false($db->trans_status());
	assert_same(4, $db->count_all('users'));
});

test('sqlite3: manual begin / rollback / commit', function ()
{
	$db = _s3_users();
	$db->trans_begin();
	$db->insert('users', array('name' => 'R', 'age' => 1, 'city' => 'c'));
	$db->trans_rollback();
	assert_same(4, $db->count_all('users'));

	$db->trans_begin();
	$db->insert('users', array('name' => 'C', 'age' => 1, 'city' => 'c'));
	$db->trans_commit();
	assert_same(5, $db->count_all('users'));
});

test('sqlite3: nested transactions only act on the outermost one', function ()
{
	$db = _s3_users();
	$db->trans_start();
	$db->trans_start();
	$db->insert('users', array('name' => 'N', 'age' => 1, 'city' => 'c'));
	$db->trans_complete();
	$db->trans_complete();
	assert_same(5, $db->count_all('users'));
});

test('sqlite3: transactions can be switched off', function ()
{
	$db = _s3_users();
	$db->trans_off();
	assert_true($db->trans_begin());
	assert_true($db->trans_commit());
	assert_true($db->trans_rollback());
});

// ---- errors ---------------------------------------------------------------------

test('sqlite3: a failed query returns FALSE and exposes the SQLite error', function ()
{
	$db = _s3_users();
	assert_false($db->query('SELECT * FROM no_such_table'));
	assert_contains('no such table', $db->_error_message());
	assert_true($db->_error_number() > 0);
	assert_false($db->query('THIS IS NOT SQL'));
	assert_contains('syntax error', $db->_error_message());
});

test('sqlite3: constraint violations are reported', function ()
{
	$db = _s3();
	$db->query('CREATE TABLE t_c (id INTEGER PRIMARY KEY, e TEXT UNIQUE)');
	assert_true($db->insert('c', array('id' => 1, 'e' => 'a')));
	assert_false($db->insert('c', array('id' => 2, 'e' => 'a')));
	assert_contains('UNIQUE', $db->_error_message());
	assert_same(1, $db->count_all('c'));
});

test('sqlite3: a failed write does not leave the database locked', function ()
{
	$file = TEST_TMP.'lock.db';
	@unlink($file);
	$a = _s3($file);
	$a->query('CREATE TABLE t_l (v INT)');
	$a->query('INSERT INTO t_l VALUES (1)');
	assert_false($a->query('INSERT INTO nope VALUES (1)'));
	$b = _s3($file);
	assert_true($b->query('INSERT INTO t_l VALUES (2)'));
	assert_same(2, $b->count_all('l'));
});

// ---- schema information ---------------------------------------------------------------

test('sqlite3: list_tables, table_exists and the prefix limit', function ()
{
	$db = _s3_users();
	$db->query('CREATE TABLE other (x INT)');
	assert_same(array('t_users'), $db->list_tables(TRUE));
	$db->data_cache = array();	// list_tables() results are cached by the framework
	$tables = $db->list_tables();
	sort($tables);
	assert_same(array('other', 't_users'), $tables);
	assert_true($db->table_exists('users'));
	assert_false($db->table_exists('missing'));
});

test('sqlite3: SQLite internal tables are not listed', function ()
{
	$db = _s3_users();   // AUTOINCREMENT creates sqlite_sequence
	assert_true(in_array('sqlite_sequence', array_map(function ($r) { return $r['name']; }, $db->query("SELECT name FROM sqlite_master WHERE type='table'")->result_array())));
	assert_false(in_array('sqlite_sequence', $db->list_tables()));
});

test('sqlite3: list_fields, field_exists and field_data', function ()
{
	$db = _s3_users();
	assert_same(array('id', 'name', 'age', 'city'), $db->list_fields('users'));
	assert_true($db->field_exists('age', 'users'));
	assert_false($db->field_exists('nope', 'users'));
	$fd = $db->field_data('users');
	assert_same(array('id', 'name', 'age', 'city'), array_map(function ($f) { return $f->name; }, $fd));
});

// ---- no-ops kept from the SQLite driver ----------------------------------------------------------

test('sqlite3: reconnect and charset are accepted', function ()
{
	$db = _s3();
	$db->reconnect();
	assert_true($db->db_set_charset('utf8', 'utf8_general_ci'));
	assert_true($db->db_select());
	assert_instance('SQLite3', $db->conn_id);
});

test('sqlite3: close() shuts the connection', function ()
{
	$db = _s3();
	$conn = $db->conn_id;
	$db->close();
	assert_false($db->conn_id);
	assert_true($conn !== NULL);
});

// ---- forge ----------------------------------------------------------------------------------------

function _s3_forge($db)
{
	$CI = get_instance();
	$CI->db = $db;
	unset($CI->dbforge);
	$CI->load->dbforge();
	return $CI->dbforge;
}

test('sqlite3: dbforge loads the sqlite3 forge', function ()
{
	$forge = _s3_forge(_s3());
	assert_instance('CI_DB_sqlite3_forge', $forge);
});

test('sqlite3: create_table with an auto increment primary key', function ()
{
	$db = _s3();
	$forge = _s3_forge($db);
	$forge->add_field(array(
		'id'	=> array('type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'auto_increment' => TRUE),
		'title'	=> array('type' => 'VARCHAR', 'constraint' => '100'),
		'body'	=> array('type' => 'TEXT', 'null' => TRUE),
		'score'	=> array('type' => 'INT', 'default' => '5'),
	));
	$forge->add_key('id', TRUE);
	assert_true($forge->create_table('posts'));

	assert_true($db->table_exists('posts'));
	assert_same(array('id', 'title', 'body', 'score'), $db->list_fields('posts'));

	$db->insert('posts', array('title' => 'one'));
	$db->insert('posts', array('title' => 'two', 'body' => 'b'));
	assert_same(2, $db->insert_id());
	$rows = $db->order_by('id')->get('posts')->result_array();
	assert_same(NULL, $rows[0]['body']);
	assert_equals(5, $rows[0]['score']);
	assert_same('b', $rows[1]['body']);
});

test('sqlite3: create_table generates the SQLite3 form of the DDL', function ()
{
	$db = _s3();
	$forge = _s3_forge($db);
	$sql = $forge->_create_table('t_x', array(
		'id' => array('type' => 'INT', 'auto_increment' => TRUE),
		'a' => array('type' => 'VARCHAR', 'constraint' => 10, 'default' => 'z'),
		'b' => array('type' => 'INT', 'null' => TRUE),
	), array('id'), array(), TRUE);
	assert_contains('CREATE TABLE IF NOT EXISTS t_x(', $sql);
	assert_contains('id INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
	assert_not_contains('AUTO_INCREMENT', $sql);
	assert_not_contains('PRIMARY KEY (', $sql);   // the inline form is the primary key
	assert_contains("a VARCHAR(10) DEFAULT 'z' NOT NULL", $sql);
	assert_contains('b INT NULL', $sql);
});

test('sqlite3: composite primary keys and unique keys', function ()
{
	$db = _s3();
	$forge = _s3_forge($db);
	$forge->add_field(array(
		'a' => array('type' => 'INT'), 'b' => array('type' => 'INT'), 'email' => array('type' => 'TEXT'),
	));
	$forge->add_key(array('a', 'b'), TRUE);
	$forge->add_key('email');
	assert_true($forge->create_table('pairs'));
	assert_true($db->insert('pairs', array('a' => 1, 'b' => 1, 'email' => 'x')));
	assert_false($db->insert('pairs', array('a' => 1, 'b' => 1, 'email' => 'y')));
	assert_false($db->insert('pairs', array('a' => 2, 'b' => 2, 'email' => 'x')));
	assert_true($db->insert('pairs', array('a' => 1, 'b' => 2, 'email' => 'z')));
});

test('sqlite3: create_table(IF NOT EXISTS) can be repeated', function ()
{
	$db = _s3();
	$forge = _s3_forge($db);
	$forge->add_field(array('x' => array('type' => 'INT')));
	assert_true($forge->create_table('once', TRUE));
	$forge->add_field(array('x' => array('type' => 'INT')));
	assert_true($forge->create_table('once', TRUE));
	$forge->add_field(array('x' => array('type' => 'INT')));
	assert_false($forge->create_table('once', FALSE));
});

test('sqlite3: add_column, with and without a default', function ()
{
	$db = _s3_users();
	$forge = _s3_forge($db);
	assert_true($forge->add_column('users', array('email' => array('type' => 'TEXT'))));
	assert_true($forge->add_column('users', array('level' => array('type' => 'INT', 'default' => '3'))));
	assert_same(array('id', 'name', 'age', 'city', 'email', 'level'), $db->list_fields('users'));
	$r = $db->where('id', 1)->get('users')->row_array();
	assert_equals(3, $r['level']);
	assert_same(NULL, $r['email']);
});

test('sqlite3: drop_column works on SQLite 3.35+', function ()
{
	$db = _s3_users();
	if (version_compare($db->version(), '3.35.0', '<'))
	{
		skip('SQLite '.$db->version().' cannot drop columns');
	}
	$forge = _s3_forge($db);
	assert_true($forge->drop_column('users', 'city'));
	assert_same(array('id', 'name', 'age'), $db->list_fields('users'));
});

test('sqlite3: modify_column is not supported by SQLite and fails cleanly', function ()
{
	$db = _s3_users();
	$forge = _s3_forge($db);
	assert_false($forge->_alter_table('CHANGE', 't_users', array('age' => array('type' => 'TEXT'))));
});

test('sqlite3: rename_table and drop_table', function ()
{
	$db = _s3_users();
	$forge = _s3_forge($db);
	assert_true($forge->rename_table('users', 'people'));
	$db->data_cache = array();	// list_tables() results are cached by the framework
	assert_true($db->table_exists('people'));
	assert_false($db->table_exists('users'));
	assert_same(4, $db->count_all('people'));

	assert_true($forge->drop_table('people'));
	$db->data_cache = array();
	assert_false($db->table_exists('people'));
	// dropping a table that is gone is not an error
	assert_true($forge->drop_table('people'));
});

test('sqlite3: create_database is a no-op, drop_database deletes the file', function ()
{
	$file = TEST_TMP.'dropme.db';
	@unlink($file);
	$db = _s3($file);
	$forge = _s3_forge($db);
	assert_true($forge->_create_database());
	assert_true(file_exists($file));
	$db->close();
	assert_true($forge->_drop_database('x'));
	assert_false(file_exists($file));
	assert_false($forge->_drop_database('x'));
	assert_true(_s3_forge(_s3())->_drop_database('x'));
});

// ---- utility ---------------------------------------------------------------------------------------

test('sqlite3: dbutil loads and reports unsupported features without failing', function ()
{
	$db = _s3_users();
	$CI = get_instance();
	$CI->db = $db;
	unset($CI->dbutil);
	$CI->load->dbutil();
	assert_instance('CI_DB_sqlite3_utility', $CI->dbutil);
	assert_same(array(), $CI->dbutil->_list_databases());
	assert_false($CI->dbutil->_optimize_table('t_users'));
	assert_false($CI->dbutil->repair_table('t_users'));
});

// ---- fallback from the removed sqlite (SQLite 2) driver ---------------------------------------------------

function _skip_if_sqlite2()
{
	if (function_exists('sqlite_open'))
	{
		skip('the sqlite (SQLite 2) extension is present, no fallback happens');
	}
	require_ext('sqlite3');
}

test('sqlite fallback: dbdriver sqlite uses sqlite3 when the sqlite extension is gone', function ()
{
	_skip_if_sqlite2();
	$db = _s3(':memory:', array('dbdriver' => 'sqlite'));
	assert_instance('CI_DB_sqlite3_driver', $db);
	assert_same('sqlite3', $db->dbdriver);
	assert_true($db->query('CREATE TABLE t_f (x INT)'));
});

test('sqlite fallback: the database path is kept and the file is usable', function ()
{
	_skip_if_sqlite2();
	$file = TEST_TMP.'fallback.db';
	@unlink($file);
	$db = _s3($file, array('dbdriver' => 'sqlite'));
	assert_same($file, $db->database);
	$db->query('CREATE TABLE t_f (x INT)');
	$db->insert('f', array('x' => 4));
	$db->close();
	assert_true(file_exists($file));
	$h = new SQLite3($file);
	assert_same(4, $h->querySingle('SELECT x FROM t_f'));
});

test('sqlite fallback: it also works with the DSN notation', function ()
{
	_skip_if_sqlite2();
	$db = DB('sqlite://x:x@localhost/'.rawurlencode(':memory:').'?autoinit=FALSE&db_debug=FALSE', TRUE);
	assert_instance('CI_DB_sqlite3_driver', $db);
});

test('sqlite fallback: works through $this->load->database() too', function ()
{
	_skip_if_sqlite2();
	$db = get_instance()->load->database(array(
		'dbdriver' => 'sqlite', 'hostname' => '', 'username' => '', 'password' => '', 'database' => ':memory:',
		'dbprefix' => '', 'pconnect' => FALSE, 'db_debug' => FALSE, 'cache_on' => FALSE, 'cachedir' => '',
		'char_set' => 'utf8', 'dbcollat' => 'utf8_general_ci', 'swap_pre' => '', 'autoinit' => TRUE, 'stricton' => FALSE,
	), TRUE);
	assert_same('sqlite3', $db->dbdriver);
	assert_same(1, preg_match('/^3\./', $db->version()));
});

test('sqlite fallback: other drivers and sqlite3 itself are not rewritten', function ()
{
	require_ext('sqlite3');
	$db = _s3(':memory:', array('dbdriver' => 'sqlite3'));
	assert_same('sqlite3', $db->dbdriver);
	$db = DB(array('dbdriver' => 'mysqli', 'hostname' => 'localhost', 'username' => 'u', 'password' => 'p', 'database' => 'd',
		'dbprefix' => '', 'pconnect' => FALSE, 'db_debug' => FALSE, 'cache_on' => FALSE, 'cachedir' => '', 'char_set' => 'utf8',
		'dbcollat' => 'utf8_general_ci', 'swap_pre' => '', 'autoinit' => FALSE, 'stricton' => FALSE), TRUE);
	assert_same('mysqli', $db->dbdriver);
});
