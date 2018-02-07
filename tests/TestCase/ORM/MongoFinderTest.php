<?php

namespace Hayko\Mongodb\Test\TestCase\ORM;

use Cake\Cache\Cache;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Hayko\Mongodb\ORM\Table;

class MongoTestsTable extends Table
{
    public function initialize(array $config)
    {
        $this->setTable('tests');
        $this->setEntityClass('Hayko\Mongodb\Test\TestCase\ORM\MongoTest');
        $this->setConnection(ConnectionManager::get('mongodb_test', false));
        parent::initialize($config);
    }
}
class MongoTest extends Entity
{
}

class MongoFinderTest extends TestCase
{
    /**
     * @var MongoTestsTable $table
     */
    public $table;

    public function setUp()
    {
        parent::setUp();
        Cache::disable();
        $this->table = TableRegistry::get('MongoTests', ['className' => 'Hayko\Mongodb\Test\TestCase\ORM\MongoTestsTable']);
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->table->deleteAll([]);
        TableRegistry::clear();
    }

    public function testFind()
    {
        $this->assertTrue($this->table instanceof Table);
        $data = ['foo' => 'bar', 'baz' => true];
        $entity = $this->table->newEntity($data);
        $this->assertNotFalse($this->table->save($entity));

        $this->assertNotEmpty($this->table->find('all'));

        $data = ['foo' => ['bar' => 'baz']];
        $entity = $this->table->newEntity($data);
        $this->assertNotFalse($this->table->save($entity));

        $condition = [
            'foo.bar' => 'baz'
        ];
        $this->assertNotEmpty($this->table->find('all', ['where' => $condition]));

        $condition = [
            'foo LIKE' => 'b%r'
        ];
        $this->assertNotEmpty($this->table->find('all', ['where' => $condition]));

        $condition = [
            'foo' => ['$regex' => 'b.*r']
        ];
        $this->assertNotEmpty($this->table->find('all', ['where' => $condition]));

        $condition = [
            'foo.bar' => new \MongoDB\BSON\Regex('^b.*z$', 'i')
        ];
        $this->assertNotEmpty($this->table->find('all', ['where' => $condition]));

        $condition = [
            'OR' => [
                ['foo' => 'bar'],
                ['foo' => 'baz'],
            ]
        ];
        $this->assertNotEmpty($this->table->find('all', ['where' => $condition]));

        $condition = [
            'AND' => [
                ['foo' => 'bar'],
                ['baz' => true],
            ]
        ];
        $this->assertNotEmpty($this->table->find('all', ['where' => $condition]));


        $data = ['foo' => 125];
        $entity = $this->table->newEntity($data);
        $this->assertNotFalse($this->table->save($entity));

        $condition = [
            'foo >=' => 100
        ];
        $this->assertNotEmpty($this->table->find('all', ['where' => $condition]));


        $data = ['foo' => 125, 'bar' => 150];
        $entity = $this->table->newEntity($data);
        $this->assertNotFalse($this->table->save($entity));

        $condition = [
            'foo < bar'
        ];
        $this->assertNotEmpty($this->table->find('all', ['where' => $condition]));

        $condition = [
            '$where' => "this.foo < this.bar"
        ];
        $this->assertNotEmpty($this->table->find('all', ['where' => $condition]));

        $condition = [
            ['foo' => 'bar'],
            [['baz' => true], ['foo LIKE' => 'b%']]
        ];
        $this->assertNotEmpty($this->table->find('all', ['where' => $condition]));

        $condition = [
            ['foo IN' => ['bar', 'baz']],
        ];
        $this->assertNotEmpty($this->table->find('all', ['where' => $condition]));

        $condition = [
            ['foo NOT IN' => ['bar', 'baz']],
        ];
        $this->assertNotEmpty($this->table->find('all', ['where' => $condition]));

        $entity = $this->table->find('all')[0];
        $condition = [
            '_id' => $entity->get('_id')
        ];
        $this->assertNotEmpty($this->table->find('all', ['where' => $condition]));

        $results = $this->table->find('all', ['limit' => 2, 'page' => 2]);
        $this->assertEquals(2, count($results));
        $this->assertEquals(150, $results[1]->get('bar'));
    }

    public function testFindFirst()
    {
        $data = ['foo' => 'zip', 'baz' => true];
        $entity = $this->table->newEntity($data);
        $this->assertNotFalse($this->table->save($entity));
        $this->assertNotEmpty($this->table->find('first'));

        $data = ['foo' => 'bar', 'baz' => false];
        $entity = $this->table->newEntity($data);
        $this->assertNotFalse($this->table->save($entity));

        $expected = 'bar';
        $this->assertEquals($expected, $this->table->find('first', ['order' => 'foo'])->get('foo'));

        $expected = 'zip';
        $this->assertEquals($expected, $this->table->find('first', ['order' => ['foo' => 'desc']])->get('foo'));
    }

    public function testFindList()
    {
        $entity1 = $this->table->save($this->table->newEntity(['name' => 'foo', 'baz' => true]));
        $entity2 = $this->table->save($this->table->newEntity(['name' => 'bar', 'baz' => false]));
        $expected = [
            (string)$entity1->get('_id') => 'foo',
            (string)$entity2->get('_id') => 'bar',
        ];
        $this->assertEquals($expected, $this->table->find('list'));
    }

    public function testUpdate()
    {
        $entity1 = $this->table->save($this->table->newEntity(['name' => 'foo', 'baz' => true]));
        $this->table->save($this->table->newEntity(['name' => 'bar', 'baz' => false]));
        $this->table->save($this->table->newEntity(['name' => 'baz', 'baz' => false]));
        $entity1->set('name', 'biz');
        $this->assertTrue((bool)$this->table->save($entity1));

        $expected = 'biz';
        $this->assertEquals($expected, $this->table->find('first', ['where' => ['baz' => true]])->get('name'));

        $this->assertEquals(2, $this->table->updateAll(['name' => 'foo'], ['baz' => false]));
    }
}
