<?php
namespace HexForm\Test\UI;

use GT\Session\SessionStoreInterface;
use HexForm\UI\Flash;
use PHPUnit\Framework\TestCase;

class FlashTest extends TestCase {
	public function testSetStoresMessage():void {
		$session = self::createMock(SessionStoreInterface::class);
		$session->expects(self::once())->method("set")->with("message", "Try again");

		(new Flash($session))->set("Try again");
	}

	public function testConsumeReturnsAndRemovesMessage():void {
		$session = self::createMock(SessionStoreInterface::class);
		$session->expects(self::once())->method("getString")
			->with("message")
			->willReturn("Try again");
		$session->expects(self::once())->method("remove")->with("message");

		self::assertSame("Try again", (new Flash($session))->consume());
	}
}
