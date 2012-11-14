<?php
include 'src/MongoQB/Builder.php';

class QBtest extends PHPUnit_Framework_TestCase {

	function defaultConnect($connect = true)
	{
		return new \MongoQB\Builder(array(
			'dsn'	=>	'mongodb://localhost:27017/mongoqbtest',
			'query_safety'	=>	null
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

	function moreDocs($qb)
	{
		$qb->insert('test_select', array(
			'firstname'	=>	'Jane',
			'surname'	=>	'Doe',
			'likes'	=>	array(
				'vodka',
				'tequila'
			),
			'age'	=>	25
		));

		$qb->insert('test_select', array(
			'firstname'	=>	'George',
			'surname'	=>	'Doe',
			'likes'	=>	array(
				'brandy',
			),
			'age'	=>	58
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

	/**
	 * @covers \MongoQB\Builder::setConfig
	 */
	function test__connect()
	{
		$qb = $this->defaultConnect();

		$r = new \ReflectionClass($qb);
		$_connection = $r->getProperty('_connection');
		$_connection->setAccessible(true);
		$this->assertEquals('Mongo', get_class($_connection->getValue($qb)));
	}

	/**
	 * @expectedException MongoQB\Exception
	 */
	function test_setConfig_exception()
	{
		$qb = $this->defaultConnect();
		$qb->setConfig('');
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

	/**
	 * @expectedException MongoQB\Exception
	 */
	function test_switchDb_no_dsn()
	{
		$qb = $this->defaultConnect();
		$qb->switchDb('');
	}

	/**
	 * @expectedException MongoQB\Exception
	 */
	function test_switchDb_false_dsn()
	{
		$qb = $this->defaultConnect();
		$qb->switchDb('foo');
	}

	function test_dropDb()
	{
		$qb = new \MongoQB\Builder(array(
			'dsn'	=>	'mongodb://localhost:27017/mongoqbtestdrop',
		), true);

		$this->assertTrue($qb->dropDb('mongoqbtestdrop'));
	}

	/**
	 * @expectedException MongoQB\Exception
	 */
	function test_dropDb_no_db()
	{
		$qb = $this->defaultConnect();
		$qb->dropDb();
	}

	function test_dropCollection()
	{
		$qb = new \MongoQB\Builder(array(
			'dsn'	=>	'mongodb://localhost:27017/mongoqbtestdrop',
		), true);

		$this->assertTrue($qb->dropCollection('mongoqbtestdrop', 'foo'));
	}

	/**
	 * @expectedException MongoQB\Exception
	 */
	function test_dropCollection_no_db()
	{
		$qb = $this->defaultConnect();
		$qb->dropCollection();
	}

	/**
	 * @expectedException MongoQB\Exception
	 */
	function test_dropCollection_no_collection()
	{
		$qb = $this->defaultConnect();
		$qb->dropCollection('foo');
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

		$qb->select('');
		$result = $qb->select(array('firstname'))->get('test_select');

		$this->assertTrue(isset($result[0]['firstname']));
		$this->assertFalse(isset($result[0]['lastname']));

		$qb->select('', '');
		$result = $qb
				->select(
					array(),
					array('firstname'))
				->get('test_select');

		$this->assertFalse(isset($result[0]['firstname']));
		$this->assertTrue(isset($result[0]['surname']));
	}

	function test_where()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$result = $qb
				->where(array('firstname' => 'John'))
				->get('test_select');

		$this->assertEquals(1, count($result));

		$result = $qb
				->where('firstname', 'John')
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
				->whereInAll('likes', array(
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

		$this->assertEquals(1, count($result));
	}

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
				->whereBetweenNe('age', 18, 25)
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
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);
		$this->moreDocs($qb);

		$results = $qb
				->select(array('firstname'))
				->orderBy(array('age' => 'desc'))
				->get('test_select');

		$this->assertEquals('George', $results[0]['firstname']);
		$this->assertEquals('Jane', $results[1]['firstname']);
		$this->assertEquals('John', $results[2]['firstname']);

		$results = $qb
				->select(array('firstname'))
				->orderBy(array('age' => 'asc'))
				->get('test_select');

		$this->assertEquals('John', $results[0]['firstname']);
		$this->assertEquals('Jane', $results[1]['firstname']);
		$this->assertEquals('George', $results[2]['firstname']);
	}

	function test_limit()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);
		$this->moreDocs($qb);

		$results = $qb
				->select(array('firstname'))
				->limit(1)
				->get('test_select');

		$this->assertEquals(1, count($results));
	}

	function test_offset()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);
		$this->moreDocs($qb);

		$results = $qb
				->select(array('firstname'))
				->orderBy(array('age' => 'desc'))
				->limit(1)
				->offset(1)
				->get('test_select');

		$this->assertEquals(1, count($results));
		$this->assertEquals('Jane', $results[0]['firstname']);
	}

	function test_getWhere()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$results = $qb
				->getWhere('test_select', array(
					'age'	=>	array(
						'$gt'	=>	21
					)
				));

		$this->assertEquals(1, count($results));
		$this->assertEquals('John', $results[0]['firstname']);
	}

	function test_batchInsert()
	{
		$this->markTestIncomplete('todo');
	}

	function test_update()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$qb->updates = array(
			'$set'	=>	array(
				'firstname'	=>	'Jane'
			)
		);

		$qb->update('test_select');

		$result = $qb->get('test_select');

		$this->assertEquals('Jane', $result[0]['firstname']);
	}

	function test_updateAll()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);
		$this->moreDocs($qb);

		$qb->updates = array(
			'$set'	=>	array(
				'firstname'	=>	'Bob'
			)
		);

		$qb->updateAll('test_select');

		$results = $qb
				->select(array('firstname'))
				->get('test_select');

		$this->assertEquals('Bob', $results[0]['firstname']);
		$this->assertEquals('Bob', $results[1]['firstname']);
		$this->assertEquals('Bob', $results[2]['firstname']);
	}

	function test_inc()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$qb->inc('age', 1)->update('test_select');

		$result = $qb->get('test_select');

		$this->assertEquals(23, $result[0]['age']);
	}

	function test_dec()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$qb->dec('age', 1)->update('test_select');

		$result = $qb->get('test_select');

		$this->assertEquals(21, $result[0]['age']);
	}

	function test_set()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$qb->set('firstname', 'Jane')->update('test_select');

		$result = $qb->get('test_select');

		$this->assertEquals('Jane', $result[0]['firstname']);
	}

	function test_unsetField()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$qb->unsetField('firstname')->update('test_select');

		$result = $qb->get('test_select');

		$this->assertFalse(isset($result[0]['firstname']));
	}

	function test_addToSet()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$qb->addToSet('likes', 'whisky')->update('test_select');
		$result = $qb->get('test_select');
		$this->assertTrue(in_array('whisky', $result[0]['likes']));

		$qb->addToSet('likes', 'vodka')->update('test_select');
		$result = $qb->get('test_select');
		$this->assertTrue(in_array('vodka', $result[0]['likes']));
	}

	function test_push()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$qb->push('likes', 'vodka')->update('test_select');
		$result = $qb->get('test_select');
		$this->assertTrue(in_array('vodka', $result[0]['likes']));
	}

	function test_pop()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$qb->pop('likes')->update('test_select');
		$result = $qb->get('test_select');
		$this->assertFalse(in_array('whisky', $result[0]['likes']));
	}

	function test_pull()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$qb->pull('likes', 'whisky')->update('test_select');
		$result = $qb->get('test_select');
		$this->assertFalse(in_array('whisky', $result[0]['likes']));
	}

	function renameField()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$qb->renameField('firstname', 'fname')->update('test_select');

		$result = $qb->get('test_select');

		$this->assertFalse(isset($result[0]['firstname']));
		$this->assertTrue(isset($result[0]['fname']));
	}

	function test_delete()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$qb->delete('test_select');

		$result = $qb->get('test_select');

		$this->assertEquals(0, count($result));
	}

	function test_deleteAll()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);
		$this->moreDocs($qb);

		$qb->deleteAll('test_select');
		$result = $qb->get('test_select');
		$this->assertEquals(0, count($result));

		$this->defaultDoc($qb);
		$this->moreDocs($qb);
		$qb->whereLt('age', 24)->delete('test_select');
		$result = $qb->get('test_select');
		$this->assertEquals(2, count($result));
	}

	function test_command()
	{
		$this->markTestIncomplete('todo');
	}

	function test_addIndex()
	{
		$this->markTestIncomplete('todo');
	}

	function test_removeIndex()
	{
		$this->markTestIncomplete('todo');
	}

	function test_removeAllIndexes()
	{
		$this->markTestIncomplete('todo');
	}

	function test_listIndexes()
	{
		$this->markTestIncomplete('todo');
	}

	function test_date()
	{
		$date = \MongoQB\Builder::date(1352303587);
		$this->assertEquals(1352303587, $date->sec);

		$t = time();
		$date2 = \MongoQB\Builder::date();
		$this->assertEquals($t, $date2->sec);
	}

	function test_getDbref()
	{
		$this->markTestIncomplete('todo');
	}

	function test_lastQuery()
	{
		$qb = $this->defaultConnect();
		$this->defaultDoc($qb);

		$this->assertEquals(array(), $qb->lastQuery());

		$qb
			->select(array('firstname'))
			->where(array('lastname' => 'Doe'))
			->get('test_select');

		$lastQuery = $qb->lastQuery();

		$this->assertEquals('test_select', $lastQuery['collection']);
		$this->assertEquals('get', $lastQuery['action']);
		$this->assertEquals(array(
			'lastname' => 'Doe'
		), $lastQuery['wheres']);
		$this->assertEquals(array(
			'firstname' => 1
		), $lastQuery['selects']);
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