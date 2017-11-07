<?php

/*
  +------------------------------------------------------------------------+
  | Phalcon Framework                                                      |
  +------------------------------------------------------------------------+
  | Copyright (c) 2011-2015 Phalcon Team (http://www.phalconphp.com)       |
  +------------------------------------------------------------------------+
  | This source file is subject to the New BSD License that is bundled     |
  | with this package in the file LICENSE.txt.                             |
  |                                                                        |
  | If you did not receive a copy of the license and are unable to         |
  | obtain it through the world-wide-web, please send an email             |
  | to license@phalconphp.com so we can send you a copy immediately.       |
  +------------------------------------------------------------------------+
  | Authors: Andres Gutierrez <andres@phalconphp.com>                      |
  |          Eduar Carvajal <eduar@phalconphp.com>                         |
  +------------------------------------------------------------------------+
*/

use PHPUnit\Framework\TestCase;

class ModelsDynamicOperationsTest extends TestCase
{

	public function __construct()
	{
		spl_autoload_register(array($this, 'modelsAutoloader'));
	}

	public function __destruct()
	{
		spl_autoload_unregister(array($this, 'modelsAutoloader'));
	}

	public function modelsAutoloader($className)
	{
		$className = str_replace('\\', '/', $className);
		if (file_exists('unit-tests/models/' . $className . '.php')) {
			require 'unit-tests/models/' . $className . '.php';
		}
	}

	protected function _getDI()
	{

		Phalcon\DI::reset();

		$di = new Phalcon\DI();

		$di->set('modelsManager', function(){
			return new Phalcon\Mvc\Model\Manager();
		}, true);

		$di->set('modelsMetadata', function(){
			return new Phalcon\Mvc\Model\Metadata\Memory();
		}, true);

		return $di;
	}

	public function testModelsMysql()
	{
		require 'unit-tests/config.db.php';
		if (empty($configMysql)) {
			$this->markTestSkipped('Test skipped');
			return;
		}

		$di = $this->_getDI();

		$tracer = array();

		$di->set('db', function() use (&$tracer) {

			require 'unit-tests/config.db.php';

			$eventsManager = new Phalcon\Events\Manager();

			$connection = new Phalcon\Db\Adapter\Pdo\Mysql($configMysql);

			$eventsManager->attach('db', function($event, $connection) use (&$tracer) {
				if ($event->getType() == 'beforeQuery') {
					$tracer[] = $connection->getSqlStatement();
				}
			});

			$connection->setEventsManager($eventsManager);

			return $connection;
		}, true);

		$this->_executeTestsNormal($di, $tracer);

		$tracer = array();
		$this->_executeTestsRenamed($di, $tracer);
	}

	protected function _executeTestsNormal($di, &$tracer)
	{

		$persona = Dynamic\Personas::findFirst();
		$this->assertTrue($persona->save());

		$this->assertEquals(count($tracer), 3);

		$persona->nombres = 'Other Name '.mt_rand(0, 150000);
        $this->assertEquals($persona->getChangedFields(), array('nombres'));
		$this->assertTrue($persona->save());

		$this->assertEquals('UPDATE `personas` SET `nombres` = ? WHERE `cedula` = ?', $tracer[3]);

		$persona->nombres = 'Other Name '.mt_rand(0, 150000);
		$persona->direccion = 'Address '.mt_rand(0, 150000);
        $this->assertEquals($persona->getChangedFields(), array('nombres', 'direccion'));
		$this->assertTrue($persona->save());

		$this->assertEquals('UPDATE `personas` SET `nombres` = ?, `direccion` = ? WHERE `cedula` = ?', $tracer[4]);
	}

	protected function _executeTestsRenamed($di, &$tracer)
	{
		$personer = Dynamic\Personers::findFirst();
		$this->assertTrue($personer->save());

		$this->assertEquals(count($tracer), 3);

		$personer->navnes = 'Other Name '.mt_rand(0, 150000);
        $this->assertEquals($personer->getChangedFields(), array('navnes'));
		$this->assertTrue($personer->save());

		$this->assertEquals('UPDATE `personas` SET `nombres` = ? WHERE `cedula` = ?', $tracer[3]);

		$personer->navnes = 'Other Name '.mt_rand(0, 150000);
		$personer->adresse = 'Address '.mt_rand(0, 150000);
        $this->assertEquals($personer->getChangedFields(), array('navnes', 'adresse'));
		$this->assertTrue($personer->save());

		$this->assertEquals('UPDATE `personas` SET `nombres` = ?, `direccion` = ? WHERE `cedula` = ?', $tracer[4]);
	}

}
