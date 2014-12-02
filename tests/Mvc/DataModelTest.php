<?php

namespace Awf\Tests\DataModel;

use Awf\Date\Date;
use Awf\Event\Observer;
use Awf\Tests\Database\DatabaseMysqliCase;
use Awf\Tests\Helpers\ReflectionHelper;
use Awf\Tests\Stubs\Fakeapp\Container;
use Awf\Tests\Stubs\Mvc\DataModelStub;
use Awf\Tests\Stubs\Utils\ObserverClosure;
use Awf\Tests\Stubs\Utils\TestClosure;

require_once 'DataModelDataprovider.php';

class DataModeltest extends DatabaseMysqliCase
{
    /**
     * @group           DataModel
     * @group           DataModelGetTableFields
     * @covers          DataModel::getTableFields
     * @dataProvider    DataModelDataprovider::getTestGetTableFields
     */
    public function testGetTableFields($test, $check)
    {
        $msg = 'DataModel::getTableFields %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = new DataModelStub($container);

        // Mocking the whole database it's simply too hard. We will play with the cache and we won't get 100% code coverage
        if($test['mock']['tables'] !== null)
        {
            $tables = ReflectionHelper::getValue($model, 'tableFieldCache');

            if($test['mock']['tables'] == 'nuke')
            {
                $tables = array();
            }
            else
            {
                foreach($test['mock']['tables'] as $mockedTable => $value)
                {
                    if($value == 'unset')
                    {
                        unset($tables[$mockedTable]);
                    }
                    else
                    {
                        $tables[$mockedTable] = $value;
                    }
                }
            }

            ReflectionHelper::setValue($model, 'tableFieldCache', $tables);
        }

        if($test['mock']['tableName'] !== null)
        {
            ReflectionHelper::setValue($model, 'tableName', $test['mock']['tableName']);
        }

        $result = $model->getTableFields($test['table']);

        $this->assertEquals($check['result'], $result, sprintf($msg, 'Returned the wrong result'));
    }

    /**
     * @group           DataModel
     * @group           DataModelGetDbo
     * @covers          DataModel::getDbo
     * @dataProvider    DataModelDataprovider::getTestGetDbo
     */
    public function testGetDbo($test, $check)
    {
        // Please note that if you try to debug this test, you'll get a "Couldn't fetch mysqli_result" error
        // That's harmless and appears in debug only, you might want to suppress exception thowing
        //\PHPUnit_Framework_Error_Warning::$enabled = false;

        $msg       = 'DataModel::setFieldValue %s - Case: '.$check['case'];
        $dbcounter = 0;
        $selfDb    = clone self::$driver;

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = new DataModelStub($container);

        $newContainer = new Container(array(
            'db' => function() use (&$dbcounter, $selfDb){
                $dbcounter++;
                return $selfDb;
            }
        ));

        ReflectionHelper::setValue($model, 'container', $newContainer);

        if($test['nuke'])
        {
            ReflectionHelper::setValue($model, 'dbo', null);
        }

        $db = $model->getDbo();

        $this->assertInstanceOf('\\Awf\\Database\\Driver', $db, sprintf($msg, 'Should return an instance of Driver'));
        $this->assertEquals($check['dbCounter'], $dbcounter, sprintf($msg, ''));
    }

    /**
     * @group           DataModel
     * @group           DataModelSetFieldValue
     * @covers          DataModel::setFieldValue
     * @dataProvider    DataModelDataprovider::getTestSetFieldValue
     */
    public function testSetFieldValue($test, $check)
    {
        $msg = 'DataModel::setFieldValue %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = new DataModelStub($container);

        ReflectionHelper::setValue($model, 'aliasFields', $test['mock']['alias']);

        $model->setFieldValue($test['name'], $test['value']);

        $data  = ReflectionHelper::getValue($model, 'recordData');
        $count = isset($model->methodCounter[$check['method']]) ? $model->methodCounter[$check['method']] : 0;

        if($check['set'])
        {
            $this->assertArrayHasKey($check['key'], $data, sprintf($msg, ''));
            $this->assertEquals($check['value'], $data[$check['key']], sprintf($msg, ''));
        }
        else
        {
            $this->assertArrayNotHasKey($check['key'], $data, sprintf($msg, ''));
        }

        $this->assertEquals($check['count'], $count, sprintf($msg, 'Called the magic setter the wrong amount of times'));
    }

    /**
     * @group           DataModel
     * @group           DataModelReset
     * @covers          DataModel::reset
     * @dataProvider    DataModelDataprovider::getTestReset
     */
    public function testReset($test, $check)
    {
        $msg = 'DataModel::reset %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => $test['table']
            )
        ));

        $model = new DataModelStub($container);

        $relation = $this->getMock('\\Awf\\Mvc\\DataModel\\RelationManager', array('resetRelations'), array($model));
        $relation->expects($check['resetRelations'] ? $this->once() : $this->never())->method('resetRelations')->willReturn(null);

        ReflectionHelper::setValue($model, 'relationManager', $relation);
        ReflectionHelper::setValue($model, 'recordData', $test['mock']['recordData']);
        ReflectionHelper::setValue($model, 'eagerRelations', $test['mock']['eagerRelations']);
        ReflectionHelper::setValue($model, 'relationFilters', $test['mock']['relationFilters']);

        $return = $model->reset($test['default'], $test['relations']);

        $data    = ReflectionHelper::getValue($model, 'recordData');
        $eager   = ReflectionHelper::getValue($model, 'eagerRelations');
        $filters = ReflectionHelper::getValue($model, 'relationFilters');

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $return, sprintf($msg, 'Should return an instance of itself'));
        $this->assertEquals($check['data'], $data, sprintf($msg, 'Failed to reset the internal data'));
        $this->assertEquals($check['eager'], $eager, sprintf($msg, 'Eager relations are not correctly set'));
        $this->assertEmpty($filters, sprintf($msg, 'Relations filters should be empty'));
    }

    /**
     * @group           DataModel
     * @group           DataModelCall
     * @covers          DataModel::__call
     * @dataProvider    DataModelDataprovider::getTest__call
     */
    public function test__call($test, $check)
    {
        $msg = 'DataModel::__call %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model  = new DataModelStub($container);

        $relation = $this->getMock('\\Awf\\Mvc\\DataModel\\RelationManager', array('isMagicMethod', '__call'), array($model));
        $relation->expects($check['magic'] ? $this->once() : $this->never())->method('isMagicMethod')->willReturn($test['mock']['magic']);
        $relation->expects($check['relationCall'] ? $this->once() : $this->never())->method('__call')->willReturn(null);

        ReflectionHelper::setValue($model, 'relationManager', $relation);

        $method = $test['method'];

        // I have to use this syntax to check when I don't pass any argument
        // N.B. If I use the __call syntax to set a property, I have to use a REAL property, otherwise the __set magic
        // method kicks in and its behavior it's out the scope of this test
        if(isset($test['argument']))
        {
            $result = $model->$method($test['argument'][0], $test['argument'][1]);
        }
        else
        {
            $result = $model->$method();
        }

        $count = isset($model->methodCounter[$check['method']]) ? $model->methodCounter[$check['method']] : 0;
        $property = ReflectionHelper::getValue($model, $check['property']);

        if(is_object($result))
        {
            $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
        }
        else
        {
            $this->assertNull($result, sprintf($msg, 'Should return null when the relation manager is involved'));
        }

        $this->assertEquals($check['count'], $count, sprintf($msg, 'Invoked the specific caller method a wrong amount of times'));
        $this->assertEquals($check['value'], $property, sprintf($msg, 'Failed to set the property'));
    }

    /**
     * @group           DataModel
     * @group           DataModel__isset
     * @covers          DataModel::__isset
     * @dataProvider    DataModelDataprovider::getTest__isset
     */
    public function test__isset($test, $check)
    {
        $msg = 'DataModel::__isset %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('getFieldValue'), array($container));
        $model->expects($check['getField'] ? $this->once() : $this->never())->method('getFieldValue')->with($check['getField'])
                ->willReturn($test['mock']['getField']);

        $relation = $this->getMock('\\Awf\\Mvc\\DataModel\\RelationManager', array('isMagicProperty', '__get'), array($model));
        $relation->expects($check['magic'] ? $this->once() : $this->never())->method('isMagicProperty')->with($check['magic'])
            ->willReturn($test['mock']['magic']);
        $relation->expects($check['relationGet'] ? $this->once() : $this->never())->method('__get')->willReturn($test['mock']['relationGet']);

        ReflectionHelper::setValue($model, 'relationManager', $relation);

        $property = $test['property'];

        ReflectionHelper::setValue($model, 'aliasFields', $test['mock']['alias']);

        $isset = isset($model->$property);

        $this->assertEquals($check['isset'], $isset, sprintf($msg, 'Failed to correctly detect if a property is set'));
    }

    /**
     * @group           DataModel
     * @group           DataModel__get
     * @covers          DataModel::__get
     * @dataProvider    DataModelDataprovider::getTest__get
     */
    public function test__get($test, $check)
    {
        $msg = 'DataModel::__get %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('getFieldValue', 'getState'), array($container));
        $model->expects($check['getField'] ? $this->once() : $this->never())->method('getFieldValue')->with($check['getField'])
            ->willReturn($test['mock']['getField']);

        $model->expects($check['getState'] ? $this->once() : $this->never())->method('getState')->with($check['getState'])
            ->willReturn($test['mock']['getState']);

        $relation = $this->getMock('\\Awf\\Mvc\\DataModel\\RelationManager', array('isMagicProperty', '__get'), array($model));
        $relation->expects($check['magic'] ? $this->once() : $this->never())->method('isMagicProperty')->with($check['magic'])
            ->willReturn($test['mock']['magic']);
        $relation->expects($check['relationGet'] ? $this->once() : $this->never())->method('__get')->willReturn($test['mock']['relationGet']);

        ReflectionHelper::setValue($model, 'relationManager', $relation);

        $property = $test['property'];

        ReflectionHelper::setValue($model, 'aliasFields', $test['mock']['alias']);

        $get = $model->$property;

        $this->assertEquals($check['get'], $get, sprintf($msg, 'Failed to get the property value'));
    }

    /**
     * @group           DataModel
     * @group           DataModel__set
     * @covers          DataModel::__set
     * @dataProvider    DataModelDataprovider::getTest__set
     */
    public function test__set($test, $check)
    {
        $msg = 'DataModel::__set %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('setFieldValue', 'setState', '__call'), array($container));
        $model->expects($check['call'] ? $this->once() : $this->never())->method('__call')->willReturn(null);

        $model->expects($check['setField'] ? $this->once() : $this->never())->method('setFieldValue')->with($check['setField'])->willReturn(null);
        $model->expects($check['setState'] ? $this->once() : $this->never())->method('setState')->with($check['setState'])->willReturn(null);

        ReflectionHelper::setValue($model, 'aliasFields', $test['mock']['alias']);

        $property = $test['property'];
        $model->$property = $test['value'];

        $count = isset($model->methodCounter[$check['method']]) ? $model->methodCounter[$check['method']] : 0;

        $this->assertEquals($check['count'], $count, sprintf($msg, 'Invoked the specific setter method a wrong amount of times'));
    }

    /**
     * @group           DataModel
     * @group           DataModelGetFieldValue
     * @covers          DataModel::getFieldValue
     * @dataProvider    DataModelDataprovider::getTestGetFieldValue
     */
    public function testGetFieldValue($test, $check)
    {
        $msg = 'DataModel::getFieldValue %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = new DataModelStub($container);

        ReflectionHelper::setValue($model, 'aliasFields', $test['mock']['alias']);

        if($test['find'])
        {
            $model->find($test['find']);
        }

        $result = $model->getFieldValue($test['property'], $test['default']);

        $count = isset($model->methodCounter[$check['method']]) ? $model->methodCounter[$check['method']] : 0;

        $this->assertEquals($check['result'], $result, sprintf($msg, 'Returned the wrong value'));
        $this->assertEquals($check['count'], $count, sprintf($msg, 'Invoked the specific getter method a wrong amount of times'));
    }

    /**
     * @group           DataModel
     * @group           DataModelArchive
     * @covers          DataModel::archive
     * @dataProvider    DataModelDataprovider::getTestArchive
     */
    public function testArchive($test, $check)
    {
        $msg     = 'DataModel::getFieldValue %s - Case: '.$check['case'];
        $methods = array();

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => $test['table']
            )
        ));

        if($test['mock']['before'])
        {
            $methods['onBeforeArchive'] = $test['mock']['before'];
        }

        if($test['mock']['after'])
        {
            $methods['onAfterArchive'] = $test['mock']['after'];
        }

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('save', 'getId'), array($container, $methods));
        $model->expects($this->any())->method('getId')->willReturn(1);
        $model->expects($check['save'] ? $this->once() : $this->never())->method('save')->willReturn(null);

        $dispatcher = $this->getMock('\\Awf\\Event\\Dispatcher', array('trigger'), array($container));
        $dispatcher->expects($this->exactly($check['dispatcher']))->method('trigger')->withConsecutive(
            array($this->equalTo('onBeforeArchive')),
            array($this->equalTo('onAfterArchive'))
        );

        ReflectionHelper::setValue($model, 'behavioursDispatcher', $dispatcher);
        ReflectionHelper::setValue($model, 'aliasFields', $test['mock']['alias']);

        if($check['exception'])
        {
            $this->setExpectedException('Exception');
        }

        $result = $model->archive();

        if($check['save'])
        {
            $enabled = $model->getFieldAlias('enabled');
            $value   = $model->$enabled;

            $this->assertEquals(2, $value, sprintf($msg, 'Should set the value of the enabled field to 2'));
        }

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an istance of itself'));
    }

    /**
     * @group           DataModel
     * @group           DataModelArchive
     * @covers          DataModel::archive
     */
    public function testArchiveException()
    {
        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $this->setExpectedException('RuntimeException');

        $model = new DataModelStub($container);
        $model->archive();
    }

    /**
     * @group           DataModel
     * @group           DataModelHasField
     * @covers          DataModel::hasField
     * @dataProvider    DataModelDataprovider::getTestHasField
     */
    public function testHasField($test, $check)
    {
        $msg = 'DataModel::hasField %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('getFieldAlias'), array($container));
        $model->expects($this->any())->method('getFieldAlias')->willReturn($test['mock']['getAlias']);

        ReflectionHelper::setValue($model, 'knownFields', $test['mock']['fields']);

        $result = $model->hasField($test['field']);

        $this->assertEquals($check['result'], $result, sprintf($msg, 'Returned the wrong value'));
    }

    /**
     * @group           DataModel
     * @group           DataModelGetFieldAlias
     * @covers          DataModel::getFieldAlias
     * @dataProvider    DataModelDataprovider::getTestGetFieldAlias
     */
    public function testGetFieldAlias($test, $check)
    {
        $msg = 'DataModel::getFieldAlias %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = new DataModelStub($container);

        ReflectionHelper::setValue($model, 'aliasFields', $test['mock']['alias']);

        $result = $model->getFieldAlias($test['field']);

        $this->assertEquals($check['result'], $result, sprintf($msg, 'Returned the wrong result'));
    }

    /**
     * @group           DataModel
     * @group           DataModelBind
     * @covers          DataModel::bind
     * @dataProvider    DataModelDataprovider::getTestBind
     */
    public function testBind($test, $check)
    {
        $msg       = 'DataModel::bind %s - Case: '.$check['case'];
        $checkBind = array();

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('setFieldValue'), array($container));
        $model->expects($this->any())->method('setFieldValue')->willReturnCallback(
            function($key, $value) use (&$checkBind){
                $checkBind[$key] = $value;
            }
        );

        $dispatcher = $this->getMock('\\Awf\\Event\\Dispatcher', array('trigger'), array($container));
        $dispatcher->expects($this->exactly($check['dispatcher']))->method('trigger')->withConsecutive(
            array($this->equalTo('onBeforeBind')),
            array($this->equalTo('onAfterBind'))
        )
            ->willReturnCallback(
                function($event, $params) use ($test){
                    if($event == 'onBeforeBind' && !is_null($test['mock']['beforeDisp'])){
                        $params[1] = $test['mock']['beforeDisp'];
                    }
                }
            );

        ReflectionHelper::setValue($model, 'behavioursDispatcher', $dispatcher);

        $result = $model->bind($test['data'], $test['ignore']);

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
        $this->assertEquals($check['bind'], $checkBind, sprintf($msg, 'Failed to bind the data to the model'));
    }

    /**
     * @group           DataModel
     * @group           DataModelBind
     * @covers          DataModel::bind
     * @dataProvider    DataModelDataprovider::getTestBindException
     */
    public function testBindException($test)
    {
        $this->setExpectedException('InvalidArgumentException');

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = new DataModelStub($container);

        $model->bind($test['data']);
    }

    /**
     * @group           DataModel
     * @group           DataModelGetData
     * @covers          DataModel::getData
     */
    public function testGetData()
    {
        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = new DataModelStub($container);
        $model->find(1);

        $result = $model->getData();

        $check = array('id' => 1, 'title' => 'Testing', 'start_date' => '1980-04-18 00:00:00', 'description' => 'one');

        $this->assertEquals($check, $result, 'DataModel::getData Returned the wrong result');
    }

    /**
     * @group           DataModel
     * @group           DataModelCheck
     * @covers          DataModel::check
     * @dataProvider    DataModelDataprovider::getTestCheck
     */
    public function testCheck($test, $check)
    {
        $msg = 'DataModel::check %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => $test['table']
            )
        ));

        $model = new DataModelStub($container);

        ReflectionHelper::setValue($model, 'autoChecks', $test['mock']['auto']);

        if($test['load'])
        {
            $model->find($test['load']);
        }

        if($check['exception'])
        {
            $this->setExpectedException('RuntimeException', $check['exception']);
        }

        $result = $model->check();

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
    }

    /**
     * @group           DataModel
     * @group           DataModelReorder
     * @covers          DataModel::reorder
     * @dataProvider    DataModelDataprovider::getTestReorder
     */
    public function testReorder($test, $check)
    {
        // Please note that if you try to debug this test, you'll get a "Couldn't fetch mysqli_result" error
        // That's harmless and appears in debug only, you might want to suppress exception thowing
        //\PHPUnit_Framework_Error_Warning::$enabled = false;

        $before = 0;
        $after  = 0;
        $db     = self::$driver;
        $msg    = 'DataModel::reorder %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest_extended'
            )
        ));

        // I am passing those methods so I can double check if the method is really called
        $methods = array(
            'onBeforeReorder' => function() use(&$before){
                $before++;
            },
            'onAfterReorder' => function() use(&$after){
                $after++;
            }
        );

        // Let's mess up the records a little
        foreach($test['mock']['ordering'] as $id => $order)
        {
            $query = $db->getQuery(true)
                        ->update($db->qn('#__dbtest_extended'))
                        ->set($db->qn('ordering').' = '.$db->q($order))
                        ->where($db->qn('id').' = '.$db->q($id));

            $db->setQuery($query)->execute();
        }

        $model = new DataModelStub($container, $methods);

        // Let's mock the dispatcher, too. So I can check if events are really triggered
        $dispatcher = $this->getMock('\\Awf\\Event\\Dispatcher', array('trigger'), array($container));
        $dispatcher->expects($this->exactly(2))->method('trigger')->withConsecutive(
            array($this->equalTo('onBeforeReorder')),
            array($this->equalTo('onAfterReorder'))
        );

        ReflectionHelper::setValue($model, 'behavioursDispatcher', $dispatcher);

        $result = $model->reorder($test['where']);

        // Now let's take a look at the updated records
        $query = $db->getQuery(true)
                    ->select('ordering')
                    ->from($db->qn('#__dbtest_extended'))
                    ->order($db->qn($model->getIdFieldName()).' ASC');
        $ordering = $db->setQuery($query)->loadColumn();

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
        $this->assertEquals(1, $before, sprintf($msg, 'Failed to invoke the onBefore method'));
        $this->assertEquals(1, $after, sprintf($msg, 'Failed to invoke the onAfter method'));
        $this->assertEquals($check['order'], $ordering, sprintf($msg, 'Failed to save the correct order'));
    }

    /**
     * @group           DataModel
     * @group           DataModelReorder
     * @covers          DataModel::reorder
     */
    public function testReorderException()
    {
        $this->setExpectedException('\\Awf\\Mvc\\DataModel\\Exception\\SpecialColumnMissing');

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = new DataModelStub($container);
        $model->reorder();
    }

    /**
     * @group           DataModel
     * @group           DataModelMove
     * @covers          DataModel::move
     * @dataProvider    DataModelDataprovider::getTestMove
     */
    public function testMove($test, $check)
    {
        // Please note that if you try to debug this test, you'll get a "Couldn't fetch mysqli_result" error
        // That's harmless and appears in debug only, you might want to suppress exception thowing
        \PHPUnit_Framework_Error_Warning::$enabled = false;

        $before     = 0;
        $beforeDisp = 0;
        $after      = 0;
        $afterDisp  = 0;
        $db         = self::$driver;
        $msg        = 'DataModel::move %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest_extended'
            )
        ));

        // I am passing those methods so I can double check if the method is really called
        $methods = array(
            'onBeforeMove' => function() use(&$before){
                $before++;
            },
            'onAfterMove' => function() use(&$after){
                $after++;
            }
        );

        $model      = new DataModelStub($container, $methods);
        $dispatcher = $model->getBehavioursDispatcher();

        // Let's attach a custom observer, so I can mock and check all the calls performed by the dispatcher
        // P.A. The object is immediatly attached to the dispatcher, so I don't need to manually do that
        new ObserverClosure($dispatcher, array(
            'onBeforeMove' => function(&$subject, &$delta, &$where) use ($test, &$beforeDisp){
                if(!is_null($test['mock']['find'])){
                    $subject->find($test['mock']['find']);
                }

                if(!is_null($test['mock']['delta'])){
                    $delta = $test['mock']['delta'];
                }

                if(!is_null($test['mock']['where'])){
                    $where = $test['mock']['where'];
                }

                $beforeDisp++;
            },
            'onAfterMove' => function() use(&$afterDisp){
                $afterDisp++;
            }
        ));

        if($test['id'])
        {
            $model->find($test['id']);
        }

        $result = $model->move($test['delta'], $test['where']);

        // Now let's take a look at the updated records
        $query = $db->getQuery(true)
                    ->select('ordering')
                    ->from($db->qn('#__dbtest_extended'))
                    ->order($db->qn($model->getIdFieldName()).' ASC');
        $ordering = $db->setQuery($query)->loadColumn();

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
        $this->assertEquals(1, $before, sprintf($msg, 'Failed to invoke the onBefore method'));
        $this->assertEquals(1, $beforeDisp, sprintf($msg, 'Failed to invoke the onBeforeMove event'));
        $this->assertEquals(1, $after, sprintf($msg, 'Failed to invoke the onAfter method'));
        $this->assertEquals(1, $afterDisp, sprintf($msg, 'Failed to invoke the onAfterMove event'));
        $this->assertEquals($check['order'], $ordering, sprintf($msg, 'Failed to save the correct order'));
    }

    /**
     * @group           DataModel
     * @group           DataModelMove
     * @covers          DataModel::move
     */
    public function testMoveException()
    {
        $this->setExpectedException('RuntimeException');

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = new DataModelStub($container);
        $model->move(-1);
    }

    /**
     * @group           DataModel
     * @group           DataModelChunk
     * @covers          DataModel::chunk
     * @dataProvider    DataModelDataprovider::getTestChunk
     */
    public function testChunk($test, $check)
    {
        $msg     = 'DataModel::chunk %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $fakeGet = new TestClosure(array(
            'transform' => function(){}
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('count', 'get'), array($container));
        $model->expects($this->once())->method('count')->willReturn($test['mock']['count']);
        $model->expects($this->exactly($check['get']))->method('get')->willReturn($fakeGet);

        $result = $model->chunk($test['chunksize'], function(){});

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
    }

    /**
     * @group           DataModel
     * @group           DataModelCount
     * @covers          DataModel::count
     */
    public function testCount()
    {
        $db     = self::$driver;
        $after  = 0;

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        // I am passing those methods so I can double check if the method is really called
        $methods = array(
            'buildCountQuery' => function() use(&$after){
                $after++;
            }
        );

        $mockedQuery = $db->getQuery(true)->select('*')->from('#__dbtest');
        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('buildQuery'), array($container, $methods));
        $model->expects($this->any())->method('buildQuery')->willReturn($mockedQuery);

        // Let's mock the dispatcher, too. So I can check if events are really triggered
        $dispatcher = $this->getMock('\\Awf\\Event\\Dispatcher', array('trigger'), array($container));
        $dispatcher->expects($this->once())->method('trigger')->withConsecutive(
            array($this->equalTo('buildCountQuery'))
        );

        ReflectionHelper::setValue($model, 'behavioursDispatcher', $dispatcher);

        $result = $model->count();

        $query = $db->getQuery(true)->select('COUNT(*)')->from('#__dbtest');
        $count = $db->setQuery($query)->loadResult();

        $this->assertEquals($count, $result, 'DataModel::count Failed to return the right amount of rows');
    }

    /**
     * @group           DataModel
     * @group           DataModelBuildQuery
     * @covers          DataModel::buildQuery
     * @dataProvider    DataModelDataprovider::getTestBuildQuery
     */
    public function testBuildQuery($test, $check)
    {
        // Please note that if you try to debug this test, you'll get a "Couldn't fetch mysqli_result" error
        // That's harmless and appears in debug only, you might want to suppress exception thowing
        //\PHPUnit_Framework_Error_Warning::$enabled = false;

        $before = 0;
        $after  = 0;
        $msg    = 'DataModel::buildQuery %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        // I am passing those methods so I can double check if the method is really called
        $methods = array(
            'onBeforeBuildQuery' => function() use(&$before){
                $before++;
            },
            'onAfterBuildQuery' => function() use(&$after){
                $after++;
            }
        );

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('getState'), array($container, $methods));
        $model->expects($check['filter'] ? $this->exactly(2) : $this->never())->method('getState')->willReturnCallback(
            function($state, $default) use ($test)
            {
                if($state == 'filter_order')
                {
                    if(isset($test['mock']['order']))
                    {
                        return $test['mock']['order'];
                    }
                }
                elseif($state == 'filter_order_Dir')
                {
                    if(isset($test['mock']['dir']))
                    {
                        return $test['mock']['dir'];
                    }
                }

                return $default;
            }
        );

        // Let's mock the dispatcher, too. So I can check if events are really triggered
        $dispatcher = $this->getMock('\\Awf\\Event\\Dispatcher', array('trigger'), array($container));
        $dispatcher->expects($this->exactly(2))->method('trigger')->withConsecutive(
            array($this->equalTo('onBeforeBuildQuery')),
            array($this->equalTo('onAfterBuildQuery'))
        );

        ReflectionHelper::setValue($model, 'behavioursDispatcher', $dispatcher);
        ReflectionHelper::setValue($model, 'whereClauses', $test['mock']['where']);

        $query = $model->buildQuery($test['override']);

        $select = $query->select->getElements();
        $table  = $query->from->getElements();
        $where  = $query->where ? $query->where->getElements() : array();
        $order  = $query->order ? $query->order->getElements() : array();

        $this->assertInstanceOf('\\Awf\\Database\\Query', $query, sprintf($msg, 'Should return an instance of Awf\\Database\\Query'));

        $this->assertEquals(array('*'), $select, sprintf($msg, 'Wrong SELECT clause'));
        $this->assertEquals(array('#__dbtest'), $table, sprintf($msg, 'Wrong FROM clause'));
        $this->assertEquals($check['where'], $where, sprintf($msg, 'Wrong WHERE clause'));
        $this->assertEquals($check['order'], $order, sprintf($msg, 'Wrong ORDER BY clause'));
    }

    /**
     * @group           DataModel
     * @group           DataModelGet
     * @covers          DataModel::get
     * @dataProvider    DataModelDataprovider::getTestGet
     */
    public function testGet($test, $check)
    {
        $msg = 'DataModel::get %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('getState', 'getItemsArray', 'eagerLoad'), array($container));

        $model->expects($this->any())->method('eagerLoad')->willReturn(null);
        $model->expects($this->any())->method('getState')->willReturnCallback(
            function($state, $default) use ($test)
            {
                if($state == 'limitstart')
                {
                    return $test['mock']['limitstart'];
                }
                elseif($state == 'limit')
                {
                    return $test['mock']['limit'];
                }

                return $default;
            }
        );

        $model->expects($this->once())->method('getItemsArray')
            ->with($this->equalTo($check['limitstart']), $this->equalTo($check['limit']))
            ->willReturn(array());

        $result = $model->get($test['override'], $test['limitstart'], $test['limit']);

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel\\Collection', $result, sprintf($msg, 'Returned the wrong object'));
    }

    /**
     * @group           DataModel
     * @group           DataModelGetId
     * @covers          DataModel::getId
     */
    public function testGetId()
    {
        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = new DataModelStub($container);
        $model->find(2);

        $id = $model->getId();

        $this->assertEquals(2, $id, 'DataModel::getId Failed to return the correct id');
    }

    /**
     * @group           DataModel
     * @group           DataModelGetIdFieldName
     * @covers          DataModel::getIdFieldName
     */
    public function testGetIdFieldName()
    {
        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = new DataModelStub($container);
        $id = $model->getIdFieldName();

        $this->assertEquals('id', $id, 'DataModel::getIdFieldName Failed to return the table column id');
    }

    /**
     * @group           DataModel
     * @group           DataModelGetTableName
     * @covers          DataModel::getTableName
     */
    public function testGetTableName()
    {
        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = new DataModelStub($container);
        $table = $model->getTableName();

        $this->assertEquals('#__dbtest', $table, 'DataModel::getTableName Failed to return the table name');
    }

    /**
     * @group           DataModel
     * @group           DataModelCopy
     * @covers          DataModel::copy
     */
    public function testCopy()
    {
        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('save'), array($container));
        $model->expects($this->any())->method('save')->willReturn(null);

        $model->find(2);
        $model->copy();

        $id = $model->getId();

        $this->assertNull($id, 'DataModel::copy Should set the table ID to null before saving the record');
    }

    /**
     * @group           DataModel
     * @group           DataModelDelete
     * @covers          DataModel::delete
     * @dataProvider    DataModelDataprovider::getTestDelete
     */
    public function testDelete($test, $check)
    {
        $msg = 'DataModel::delete %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('trash', 'forceDelete'), array($container));
        $model->expects($check['trash'] ? $this->once() : $this->never())->method('trash')->willReturnSelf();
        $model->expects($check['force'] ? $this->once() : $this->never())->method('forceDelete')->willReturnSelf();

        ReflectionHelper::setValue($model, 'softDelete', $test['soft']);

        $result = $model->delete($test['id']);

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
    }

    /**
     * @group           DataModel
     * @group           DataModelTrash
     * @covers          DataModel::trash
     * @dataProvider    DataModelDataprovider::getTestTrash
     */
    public function testTrash($test, $check)
    {
        $before = 0;
        $after  = 0;
        $msg    = 'DataModel::trash %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest_extended'
            )
        ));

        // I am passing those methods so I can double check if the method is really called
        $methods = array(
            'onBeforeTrash' => function() use(&$before){
                $before++;
            },
            'onAfterTrash' => function() use(&$after){
                $after++;
            }
        );

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('save', 'getId', 'findOrFail'), array($container, $methods));
        $model->expects($this->any())->method('save')->willReturn(null);
        $model->expects($this->any())->method('getId')->willReturn(1);
        $model->expects($check['find'] ? $this->once() : $this->never())->method('findOrFail')->willReturn(null);

        // Let's mock the dispatcher, too. So I can check if events are really triggered
        $dispatcher = $this->getMock('\\Awf\\Event\\Dispatcher', array('trigger'), array($container));
        $dispatcher->expects($this->exactly($check['dispatcher']))->method('trigger')->withConsecutive(
            array($this->equalTo('onBeforeTrash')),
            array($this->equalTo('onAfterTrash'))
        );

        ReflectionHelper::setValue($model, 'behavioursDispatcher', $dispatcher);

        $result = $model->trash($test['id']);

        $enabled = $model->getFieldValue('enabled');

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
        $this->assertEquals($check['before'], $before, sprintf($msg, 'Failed to call the onBefore method'));
        $this->assertEquals($check['after'], $after, sprintf($msg, 'Failed to call the onAfter method'));
        $this->assertSame($check['enabled'], $enabled, sprintf($msg, 'Failed to set the enabled field'));
    }

    /**
     * @group           DataModel
     * @group           DataModelTrash
     * @covers          DataModel::trash
     * @dataProvider    DataModelDataprovider::getTestTrashException
     */
    public function testTrashException($test, $check)
    {
        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => $test['table']
            )
        ));

        $this->setExpectedException($check['exception']);

        $model = new DataModelStub($container);
        $model->trash($test['id']);
    }

    /**
     * @group           DataModel
     * @group           DataModelFindOrFail
     * @covers          DataModel::findOrFail
     * @dataProvider    DataModelDataprovider::getTestFindOrFail
     */
    public function testFindOrFail($test, $check)
    {
        $msg    = 'DataModel::findOrFail %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('find', 'getId'), array($container));
        $model->expects($this->any())->method('find')->willReturn(null);
        $model->expects($this->any())->method('getId')->willReturn($test['mock']['getId']);

        if($check['exception'])
        {
            $this->setExpectedException('RuntimeException');
        }

        $result = $model->findOrFail($test['keys']);

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
    }

    /**
     * @group           DataModel
     * @group           DataModelForceDelete
     * @covers          DataModel::forceDelete
     * @dataProvider    DataModelDataprovider::getTestForceDelete
     */
    public function testForceDelete($test, $check)
    {
        $before = 0;
        $after  = 0;
        $msg    = 'DataModel::forceDelete %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        // I am passing those methods so I can double check if the method is really called
        $methods = array(
            'onBeforeDelete' => function() use(&$before){
                $before++;
            },
            'onAfterDelete' => function() use(&$after){
                $after++;
            }
        );

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('getId', 'findOrFail', 'reset'), array($container, $methods));
        $model->expects($this->once())->method('reset')->willReturn(null);
        $model->expects($this->any())->method('getId')->willReturn($test['mock']['id']);
        $model->expects($check['find'] ? $this->once() : $this->never())->method('findOrFail')->willReturn(null);

        // Let's mock the dispatcher, too. So I can check if events are really triggered
        $dispatcher = $this->getMock('\\Awf\\Event\\Dispatcher', array('trigger'), array($container));
        $dispatcher->expects($this->exactly(2))->method('trigger')->withConsecutive(
            array($this->equalTo('onBeforeDelete')),
            array($this->equalTo('onAfterDelete'))
        );

        ReflectionHelper::setValue($model, 'behavioursDispatcher', $dispatcher);

        $result = $model->delete($test['id']);

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
        $this->assertEquals(1, $before, sprintf($msg, 'Failed to call the onBefore method'));
        $this->assertEquals(1, $after, sprintf($msg, 'Failed to call the onAfter method'));

        // Now let's check if the record was really deleted
        $db = self::$driver;

        $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->qn('#__dbtest'))
                    ->where($db->qn('id').' = '.$db->q($check['id']));
        $count = $db->setQuery($query)->loadResult();

        $this->assertEquals(0, $count, sprintf($msg, ''));
    }

    /**
     * @group           DataModel
     * @group           DataModelForceDelete
     * @covers          DataModel::forceDelete
     */
    public function testForceDeleteException()
    {
        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = new DataModelStub($container);

        $this->setExpectedException('RuntimeException');

        $model->forceDelete();
    }

    /**
     * @group           DataModel
     * @group           DataModelFirstOrCreate
     * @covers          DataModel::firstOrCreate
     * @dataProvider    DataModelDataprovider::getTestFirstOrCreate
     */
    public function testFirstOrCreate($test, $check)
    {
        $msg = 'DataModel::firstOrCreate %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $fakeCollection = new TestClosure(array(
            'first' => function() use ($test){
                return $test['mock']['first'];
            }
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('get', 'create'), array($container));
        $model->expects($this->once())->method('get')->willReturn($fakeCollection);
        $model->expects($check['create'] ? $this->once() : $this->never())->method('create')->willReturn(null);

        $result = $model->firstOrCreate(array());

        if($check['result'] == 'object')
        {
            $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Returned the wrong value'));
        }
        else
        {
            $this->assertEquals($check['result'], $result, sprintf($msg, 'Returned the wrong value'));
        }
    }

    /**
     * @group           DataModel
     * @group           DataModelCreate
     * @covers          DataModel::create
     */
    public function testCreate()
    {
        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('reset', 'bind', 'save'), array($container));
        $model->expects($this->once())->method('reset')->willReturnSelf();
        $model->expects($this->once())->method('bind')->willReturnSelf();
        $model->expects($this->once())->method('save')->willReturnSelf();

        $model->create(array('foo' => 'bar'));
    }

    /**
     * @group           DataModel
     * @group           DataModelFirstOrFail
     * @covers          DataModel::firstOrFail
     * @dataProvider    DataModelDataprovider::getTestFirstOrFail
     */
    public function testFirstOrFail($test, $check)
    {
        $msg = 'DataModel::firstOrFail %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $fakeCollection = new TestClosure(array(
            'first' => function() use ($test){
                return $test['mock']['first'];
            }
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('get'), array($container));
        $model->expects($this->once())->method('get')->willReturn($fakeCollection);

        if($check['exception'])
        {
            $this->setExpectedException('RuntimeException');
        }

        $result = $model->firstOrFail(array());

        $this->assertEquals($check['result'], $result, sprintf($msg, 'Returned the wrong value'));
    }

    /**
     * @group           DataModel
     * @group           DataModelFirstOrNew
     * @covers          DataModel::firstOrNew
     * @dataProvider    DataModelDataprovider::getTestFirstOrNew
     */
    public function testFirstOrNew($test, $check)
    {
        $msg = 'DataModel::firstOrNew %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $fakeCollection = new TestClosure(array(
            'first' => function() use ($test){
                return $test['mock']['first'];
            }
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('get', 'reset'), array($container));
        $model->expects($this->once())->method('get')->willReturn($fakeCollection);
        $model->expects($check['reset'] ? $this->once() : $this->never())->method('reset')->willReturn(null);

        $result = $model->firstOrNew(array());

        if($check['result'] == 'object')
        {
            $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Returned the wrong value'));
        }
        else
        {
            $this->assertEquals($check['result'], $result, sprintf($msg, 'Returned the wrong value'));
        }
    }

    /**
     * @group           DataModel
     * @group           DataModelLock
     * @covers          DataModel::lock
     * @dataProvider    DataModelDataprovider::getTestLock
     */
    public function testLock($test, $check)
    {
        $before = 0;
        $after  = 0;
        $msg    = 'DataModel::lock %s - Case: '.$check['case'];

        $fakeUserManager = new TestClosure(array(
            'getUser' => function() use ($test){
                return new TestClosure(array(
                    'getId' => function() use ($test){
                        return $test['mock']['user_id'];
                    }
                ));
            }
        ));

        $container = new Container(array(
            'db' => self::$driver,
            'userManager' => $fakeUserManager,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => $test['table']
            )
        ));

        // I am passing those methods so I can double check if the method is really called
        $methods = array(
            'onBeforeLock' => function() use(&$before){
                $before++;
            },
            'onAfterLock' => function() use(&$after){
                $after++;
            }
        );

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('save', 'getId'), array($container, $methods));
        $model->expects($this->any())->method('save')->willReturn(null);
        $model->expects($this->any())->method('getId')->willReturn(1);

        // Let's mock the dispatcher, too. So I can check if events are really triggered
        $dispatcher = $this->getMock('\\Awf\\Event\\Dispatcher', array('trigger'), array($container));
        $dispatcher->expects($this->exactly($check['dispatcher']))->method('trigger')->withConsecutive(
            array($this->equalTo('onBeforeLock')),
            array($this->equalTo('onAfterLock'))
        );

        ReflectionHelper::setValue($model, 'behavioursDispatcher', $dispatcher);

        $result = $model->lock($test['user_id']);

        $locked_by = $model->getFieldValue('locked_by');
        $locked_on = $model->getFieldValue('locked_on');

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
        $this->assertEquals($check['before'], $before, sprintf($msg, 'Failed to call the onBefore method'));
        $this->assertEquals($check['after'], $after, sprintf($msg, 'Failed to call the onAfter method'));
        $this->assertEquals($check['locked_by'], $locked_by, sprintf($msg, 'Failed to set the locking user'));

        // The time is calculated on the fly, so I can only check if it's null or not
        if($check['locked_on'])
        {
            $this->assertNotNull($locked_on, sprintf($msg, 'Failed to set the locking time'));
        }
        else
        {
            $this->assertNull($locked_on, sprintf($msg, 'Failed to set the locking time'));
        }
    }

    /**
     * @group           DataModel
     * @group           DataModelLock
     * @covers          DataModel::lock
     */
    public function testLockException()
    {
        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $this->setExpectedException('RuntimeException');

        $model = new DataModelStub($container);
        $model->lock();
    }

    /**
     * @group           DataModel
     * @group           DataModelOrderBy
     * @covers          DataModel::orderBy
     * @dataProvider    DataModelDataprovider::getTestOrderBy
     */
    public function testOrderBy($test, $check)
    {
        $msg    = 'DataModel::orderBy %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('setState'), array($container));
        $model->expects($this->exactly(2))->method('setState')->willReturn(null)->withConsecutive(
            array($this->equalTo('filter_order'), $this->equalTo($check['field'])),
            array($this->equalTo('filter_order_Dir'), $this->equalTo($check['dir']))
        );

        $result = $model->orderBy($test['field'], $test['dir']);

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
    }

    /**
     * @group           DataModel
     * @group           DataModelPublish
     * @covers          DataModel::publish
     * @dataProvider    DataModelDataprovider::getTestPublish
     */
    public function testPublish($test, $check)
    {
        $before = 0;
        $after  = 0;
        $msg    = 'DataModel::publish %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => $test['table']
            )
        ));

        // I am passing those methods so I can double check if the method is really called
        $methods = array(
            'onBeforePublish' => function() use(&$before){
                $before++;
            },
            'onAfterPublish' => function() use(&$after){
                $after++;
            }
        );

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('save', 'getId'), array($container, $methods));
        $model->expects($this->any())->method('save')->willReturn(null);
        $model->expects($this->any())->method('getId')->willReturn(1);

        // Let's mock the dispatcher, too. So I can check if events are really triggered
        $dispatcher = $this->getMock('\\Awf\\Event\\Dispatcher', array('trigger'), array($container));
        $dispatcher->expects($this->exactly($check['dispatcher']))->method('trigger')->withConsecutive(
            array($this->equalTo('onBeforePublish')),
            array($this->equalTo('onAfterPublish'))
        );

        ReflectionHelper::setValue($model, 'behavioursDispatcher', $dispatcher);

        $result = $model->publish($test['state']);

        $enabled = $model->getFieldValue('enabled');

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
        $this->assertEquals($check['before'], $before, sprintf($msg, 'Failed to call the onBefore method'));
        $this->assertEquals($check['after'], $after, sprintf($msg, 'Failed to call the onAfter method'));
        $this->assertEquals($check['enabled'], $enabled, sprintf($msg, 'Failed to set the enabled field'));
    }

    /**
     * @group           DataModel
     * @group           DataModelPublish
     * @covers          DataModel::publish
     */
    public function testPublishException()
    {
        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $this->setExpectedException('RuntimeException');

        $model = new DataModelStub($container);
        $model->publish();
    }

    /**
     * @group           DataModel
     * @group           DataModelRestore
     * @covers          DataModel::restore
     * @dataProvider    DataModelDataprovider::getTestrestore
     */
    public function testRestore($test, $check)
    {
        $before = 0;
        $after  = 0;
        $msg    = 'DataModel::restore %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => $test['table']
            )
        ));

        // I am passing those methods so I can double check if the method is really called
        $methods = array(
            'onBeforeRestore' => function() use(&$before){
                $before++;
            },
            'onAfterRestore' => function() use(&$after){
                $after++;
            }
        );

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('save', 'getId', 'findOrFail'), array($container, $methods));
        $model->expects($this->any())->method('save')->willReturn(null);
        $model->expects($this->any())->method('getId')->willReturn(1);
        $model->expects($check['find'] ? $this->once() : $this->never())->method('findOrFail')->willReturn(null);

        // Let's mock the dispatcher, too. So I can check if events are really triggered
        $dispatcher = $this->getMock('\\Awf\\Event\\Dispatcher', array('trigger'), array($container));
        $dispatcher->expects($this->exactly($check['dispatcher']))->method('trigger')->withConsecutive(
            array($this->equalTo('onBeforeRestore')),
            array($this->equalTo('onAfterRestore'))
        );

        ReflectionHelper::setValue($model, 'behavioursDispatcher', $dispatcher);

        $result = $model->restore($test['id']);

        $enabled = $model->getFieldValue('enabled');

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
        $this->assertEquals($check['before'], $before, sprintf($msg, 'Failed to call the onBefore method'));
        $this->assertEquals($check['after'], $after, sprintf($msg, 'Failed to call the onAfter method'));
        $this->assertSame($check['enabled'], $enabled, sprintf($msg, 'Failed to set the enabled field'));
    }

    /**
     * @group           DataModel
     * @group           DataModelRestore
     * @covers          DataModel::restore
     */
    public function testRestoreException()
    {
        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'autoChecks'  => false,
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest_extended'
            )
        ));

        $this->setExpectedException('RuntimeException');

        $model = new DataModelStub($container);
        $model->restore();
    }

    /**
     * @group           DataModel
     * @group           DataModelSkip
     * @covers          DataModel::skip
     * @dataProvider    DataModelDataprovider::getTestSkip
     */
    public function testSkip($test, $check)
    {
        $msg = 'DataModel::skip %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('setState'), array($container));
        $model->expects($this->once())->method('setState')->willReturn(null)->with($this->equalTo('limitstart'), $this->equalTo($check['limitstart']));

        $result = $model->skip($test['limitstart']);

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
    }

    /**
     * @group           DataModel
     * @group           DataModelTake
     * @covers          DataModel::take
     * @dataProvider    DataModelDataprovider::getTestTake
     */
    public function testTake($test, $check)
    {
        $msg = 'DataModel::take %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('setState'), array($container));
        $model->expects($this->once())->method('setState')->willReturn(null)->with($this->equalTo('limit'), $this->equalTo($check['limit']));

        $result = $model->take($test['limit']);

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
    }

    /**
     * @group           DataModel
     * @group           DataModelTouch
     * @covers          DataModel::touch
     * @dataProvider    DataModelDataprovider::getTestTouch
     */
    public function testTouch($test, $check)
    {
        $msg    = 'DataModel::touch %s - Case: '.$check['case'];

        $fakeUserManager = new TestClosure(array(
            'getUser' => function() use ($test){
                return new TestClosure(array(
                    'getId' => function() use ($test){
                        return $test['mock']['user_id'];
                    }
                ));
            }
        ));

        $container = new Container(array(
            'db' => self::$driver,
            'userManager' => $fakeUserManager,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => $test['table']
            )
        ));

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('save', 'getId'), array($container));
        $model->expects($this->any())->method('save')->willReturn(null);
        $model->expects($this->any())->method('getId')->willReturn(1);

        $result = $model->touch($test['user_id']);

        $modified_by = $model->getFieldValue('modified_by');
        $modified_on = $model->getFieldValue('modified_on');

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
        $this->assertEquals($check['modified_by'], $modified_by, sprintf($msg, 'Failed to set the modifying user'));

        // The time is calculated on the fly, so I can only check if it's null or not
        if($check['modified_on'])
        {
            $this->assertNotNull($modified_on, sprintf($msg, 'Failed to set the modifying time'));
        }
        else
        {
            $this->assertNull($modified_on, sprintf($msg, 'Failed to set the modifying time'));
        }
    }

    /**
     * @group           DataModel
     * @group           DataModelTouch
     * @covers          DataModel::touch
     */
    public function testTouchException()
    {
        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $this->setExpectedException('RuntimeException');

        $model = new DataModelStub($container);
        $model->touch();
    }

    /**
     * @group           DataModel
     * @group           DataModelUnlock
     * @covers          DataModel::unlock
     * @dataProvider    DataModelDataprovider::getTestUnlock
     */
    public function testUnlock($test, $check)
    {
        $before = 0;
        $after  = 0;
        $msg    = 'DataModel::unlock %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => $test['table']
            )
        ));

        // I am passing those methods so I can double check if the method is really called
        $methods = array(
            'onBeforeUnlock' => function() use(&$before){
                $before++;
            },
            'onAfterUnlock' => function() use(&$after){
                $after++;
            }
        );

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('save', 'getId'), array($container, $methods));
        $model->expects($this->any())->method('save')->willReturn(null);
        $model->expects($this->any())->method('getId')->willReturn(1);

        // Let's mock the dispatcher, too. So I can check if events are really triggered
        $dispatcher = $this->getMock('\\Awf\\Event\\Dispatcher', array('trigger'), array($container));
        $dispatcher->expects($this->exactly($check['dispatcher']))->method('trigger')->withConsecutive(
            array($this->equalTo('onBeforeUnlock')),
            array($this->equalTo('onAfterUnlock'))
        );

        ReflectionHelper::setValue($model, 'behavioursDispatcher', $dispatcher);

        if($model->hasField('locked_on'))
        {
            $now = new Date();
            $model->setFieldValue('locked_on', $now->toSql());
        }

        $result = $model->unlock();

        $locked_by = $model->getFieldValue('locked_by');
        $locked_on = $model->getFieldValue('locked_on');

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
        $this->assertEquals($check['before'], $before, sprintf($msg, 'Failed to call the onBefore method'));
        $this->assertEquals($check['after'], $after, sprintf($msg, 'Failed to call the onAfter method'));
        $this->assertEquals($check['locked_by'], $locked_by, sprintf($msg, 'Failed to set the locking user'));

        // The time is calculated on the fly, so I can only check if it's null or not
        if($check['locked_on'])
        {
            $this->assertEquals(self::$driver->getNullDate(), $locked_on, sprintf($msg, 'Failed to set the locking time'));
        }
        else
        {
            $this->assertNull($locked_on, sprintf($msg, 'Failed to set the locking time'));
        }
    }

    /**
     * @group           DataModel
     * @group           DataModelUnlock
     * @covers          DataModel::unlock
     */
    public function testUnlockException()
    {
        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $this->setExpectedException('RuntimeException');

        $model = new DataModelStub($container);
        $model->unlock();
    }

    /**
     * @group           DataModel
     * @group           DataModelUnpublish
     * @covers          DataModel::unpublish
     * @dataProvider    DataModelDataprovider::getTestUnpublish
     */
    public function testUnpublish($test, $check)
    {
        $before = 0;
        $after  = 0;
        $msg    = 'DataModel::unpublish %s - Case: '.$check['case'];

        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => $test['table']
            )
        ));

        // I am passing those methods so I can double check if the method is really called
        $methods = array(
            'onBeforeUnpublish' => function() use(&$before){
                $before++;
            },
            'onAfterUnpublish' => function() use(&$after){
                $after++;
            }
        );

        $model = $this->getMock('\\Awf\\Tests\\Stubs\\Mvc\\DataModelStub', array('save', 'getId'), array($container, $methods));
        $model->expects($this->any())->method('save')->willReturn(null);
        $model->expects($this->any())->method('getId')->willReturn(1);

        // Let's mock the dispatcher, too. So I can check if events are really triggered
        $dispatcher = $this->getMock('\\Awf\\Event\\Dispatcher', array('trigger'), array($container));
        $dispatcher->expects($this->exactly($check['dispatcher']))->method('trigger')->withConsecutive(
            array($this->equalTo('onBeforeUnpublish')),
            array($this->equalTo('onAfterUnpublish'))
        );

        ReflectionHelper::setValue($model, 'behavioursDispatcher', $dispatcher);

        $result = $model->unpublish();

        $enabled = $model->getFieldValue('enabled');

        $this->assertInstanceOf('\\Awf\\Mvc\\DataModel', $result, sprintf($msg, 'Should return an instance of itself'));
        $this->assertEquals($check['before'], $before, sprintf($msg, 'Failed to call the onBefore method'));
        $this->assertEquals($check['after'], $after, sprintf($msg, 'Failed to call the onAfter method'));
        $this->assertSame($check['enabled'], $enabled, sprintf($msg, 'Failed to set the enabled field'));
    }

    /**
     * @group           DataModel
     * @group           DataModelUnpublish
     * @covers          DataModel::unpublish
     */
    public function testUnpublishException()
    {
        $container = new Container(array(
            'db' => self::$driver,
            'mvc_config' => array(
                'idFieldName' => 'id',
                'tableName'   => '#__dbtest'
            )
        ));

        $this->setExpectedException('RuntimeException');

        $model = new DataModelStub($container);
        $model->unpublish();
    }
}