<?php
include 'src/MongoQB/Builder.php';

class QBtest extends PHPUnit_Framework_TestCase {

	function defaultConnect($connect = true)
	{
		return new \MongoQB\Builder(array(
			'dsn'	=>	'mongodb://localhost:27017/mongoqbtest',
		), $connect);
	}

	function defaultDoc($qb)
	{
		$qb->insert('test_select', array(
			'firstname'	=>	'John',
			'surname'	=>	'Doe',
			'likes'	=>	array(
				'whisky',
				'gin',
				'rum'
			),
			'age'	=>	22
		));
	}

	function test__connectionString()
	{
		$qb = $this->defaultConnect(false);

		$r = new \ReflectionClass($qb);
		$_dsn = $r->getProperty('_dsn');
		$_dsn->setAccessible(true);
		$this->assertEquals('mongodb://localhost:27017/mongoqbtest', $_dsn->getValue($qb));

		$_dbname = $r->getProperty('_dbname');
		$_dbname->setAccessible(true);
		$this->assertEquals('mongoqbtest', $_dbname->getValue($qb));

		$_persist = $r->getProperty('_persist');
		$_persist->setAccessible(true);
		$this->assertTrue($_persist->getValue($qb));

		$_persist_key = $r->getProperty('_persist_key');
		$_persist_key->setAccessible(true);
		$this->assertEquals('mongoqb', $_persist_key->getValue($qb));

		$_replicaSet = $r->getProperty('_replicaSet');
		$_replicaSet->setAccessible(true);
		$this->assertFalse($_replicaSet->getValue($qb));

		$_querySafety = $r->getProperty('_querySafety');
		$_querySafety->setAccessible(true);
		$this->assertEquals('safe', $_querySafety->getValue($qb));
	}

	function test__connect()
	{
		$qb = $this->defaultConnect();

		$r = new \ReflectionClass($qb);
		$_connection = $r->getProperty('_connection');
		$_connection->setAccessible(true);
		$this->assertEquals('Mongo', get_class($_connection->getValue($qb)));
	}

	function test_switchDb()
	{
		$qb = $this->defaultConnect();

		$r = new \ReflectionClass($qb);
		$_dbname = $r->getProperty('_dbname');
		$_dbname->setAccessible(true);
		$dbname = $_dbname->getValue($qb);

		$qb->switchDb('mongodb://localhost:27017/mongoqbtest2');

		$_dbname2 = $r->getProperty('_dbname');
		$_dbname2->setAccessible(true);
		$dbname2 = $_dbname2->getValue($qb);

		$this->assertEquals('mongoqbtest2', $dbname2);
		$this->assertTrue(($dbname2 !== $dbname));
	}

	function test_dropDb()
	{
		$qb = new \MongoQB\Builder(array(
			'dsn'	=>	'mongodb://localhost:27017/mongoqbtestdrop',
		), true);

		$this->assertTrue($qb->dropDb('mongoqbtestdrop'));
	}

	function test_dropCollection()
	{
		$qb = new \MongoQB\Builder(array(
			'dsn'	=>	'mongodb://localhost:27017/mongoqbtestdrop',
		), true);

		$this->assertTrue($qb->dropCollection('mongoqbtestdrop', 'foo'));
	}

	function test_get()
	{
		$qb = new \MongoQB\Builder(array(
			'dsn'	=>	'mongodb://localhost:27017/mongoqbtest',
		), true);

		$result = $qb->get('test_get');

		$this->assertEquals(array(), $result);
	}

	function test_count()
	{
		$qb = $this->defaultConnect();

		$this->assertEquals(0, $qb->count('test_count'));
	}

	function test_insert()
	{
		$qb = $this->defaultConnect();

		$result = $qb->insert('test_insert', array(
			'foo'	=>	'bar'
		));

		$this->assertNotEquals(false, $result);
		$this->assertEquals(1, $qb->count('test_insert'));
	}

	function test_select()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$result = $qb->select(array('firstname'))->get('test_select');

		$this->assertTrue(isset($result[0]['firstname']));
		$this->assertFalse(isset($result[0]['lastname']));
	}

	function test_where()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$result = $qb
					->where(array('firstname' => 'John'))
					->get('test_select');

		$this->assertEquals(1, count($result));
	}

	function test_orWhere()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$result = $qb
					->orWhere(array(
						'firstname' => 'Jane',
						'surname'	=>	'Doe'
					))
					->get('test_select');

		$this->assertEquals(1, count($result));
	}

	function test_whereIn()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$result = $qb
					->whereIn('likes', array(
						'whisky',
						'vodka'
					))
					->get('test_select');

		$this->assertEquals(1, count($result));
	}

	function test_whereInAll()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$result = $qb
					->whereIn('likes', array(
						'whisky',
						'gin'
					))
					->get('test_select');

		$this->assertEquals(1, count($result));
	}

	function test_whereNotIn()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$result = $qb
					->whereNotIn('likes', array(
						'vodka',
						'sloe gin'
					))
					->get('test_select');

		$this->assertEquals(1, count($result));
	}

	function test_whereGt()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$result = $qb
					->whereGt('age', 18)
					->get('test_select');

		$this->assertEquals(1, count($result));
	}

	function test_whereGte()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$result = $qb
					->whereGte('age', 22)
					->get('test_select');

		$this->assertEquals(1, count($result));
	}

	function test_whereLt()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$result = $qb
					->whereLt('age', 25)
					->get('test_select');

		$this->assertEquals(1, count($result));	}

	function test_whereLte()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$result = $qb
					->whereLte('age', 22)
					->get('test_select');

		$this->assertEquals(1, count($result));
	}

	function test_whereBetween()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$result = $qb
					->whereBetween('age', 18, 25)
					->get('test_select');

		$this->assertEquals(1, count($result));
	}

	function test_whereBetweenNe()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$result = $qb
					->whereBetweenNe('age', 18, 21)
					->get('test_select');

		$this->assertEquals(1, count($result));
	}

	function test_whereNe()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$result = $qb
					->whereNe('age', 21)
					->get('test_select');

		$this->assertEquals(1, count($result));
	}

	function test_whereNear()
	{
		$this->markTestIncomplete('todo');
	}

	function test_like()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$result1 = $qb
					->whereLike('firstname', 'John', '', false, false)
					->get('test_select');

		$this->assertEquals(1, count($result1));

		$result2 = $qb
					->whereLike('firstname', 'john', 'i', false, false)
					->get('test_select');

		$this->assertEquals(1, count($result2));

		$result3 = $qb
					->whereLike('firstname', 'jo', 'i', false, true)
					->get('test_select');

		$this->assertEquals(1, count($result3));

		$result4 = $qb
					->whereLike('firstname', 'hn', 'i', true, false)
					->get('test_select');

		$this->assertEquals(1, count($result4));

		$result5 = $qb
					->whereLike('firstname', 'oh', 'i', true, true)
					->get('test_select');

		$this->assertEquals(1, count($result5));
	}

	function test_orderBy()
	{

	}

	function test_limit()
	{

	}

	function test_offset()
	{

	}

	function test_getWhere()
	{

	}

	function test_batchInsert()
	{

	}

	function test_update()
	{

	}

	function test_updateAll()
	{

	}

	function test_inc()
	{

	}

	function test_dec()
	{

	}

	function test_set()
	{

	}

	function test_unsetField()
	{

	}

	function test_addToSet()
	{

	}

	function test_push()
	{

	}

	function test_pop()
	{

	}

	function test_pull()
	{

	}

	function renameField()
	{

	}

	function test_delete()
	{

	}

	function test_deleteAll()
	{

	}

	function test_command()
	{

	}

	function test_addIndex()
	{

	}

	function test_removeIndex()
	{

	}

	function test_removeAllIndexes()
	{

	}

	function test_listIndexes()
	{

	}

	function test_date()
	{

	}

	function test_getDbref()
	{

	}

	function test_lastQuery()
	{

	}

	function test__clear()
	{

	}

	function test__whereInit()
	{

	}

	function test__updateInit()
	{

	}

	function tearDown()
	{
		$qb = new \MongoQB\Builder(array(
			'dsn'	=>	'mongodb://localhost:27017/mongoqb',
		), true);

		$qb->dropDb('mongoqbtest');
		$qb->dropDb('mongoqbtest2');
	}
}