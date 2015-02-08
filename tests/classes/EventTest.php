<?php
class EventTest extends PHPUnit_Framework_TestCase
{
	protected static $event = null;
	
	public static function setUpBeforeClass() {
		self::$event = new \vakata\event\Event();
	}
	public static function tearDownAfterClass() {
	}
	protected function setUp() {
	}
	protected function tearDown() {
	}

	public function testListenTrigger() {
		$cnt = 0;
		self::$event->listen('event', function ($increment) use (&$cnt) {
			$cnt += $increment;
		});
		self::$event->trigger('event', 2);
		$this->assertEquals(2, $cnt);
		self::$event->listen('event', function ($increment) use (&$cnt) {
			$cnt += ($increment * 2);
		});
		self::$event->trigger('event', 1);
		$this->assertEquals(5, $cnt);
	}
}