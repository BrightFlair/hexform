<?php
namespace HexForm\Test\User;

use HexForm\User\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase {
	private const string TEST_USER_ID = "user-1";
	private const string TEST_USER_EMAIL = "person@example.com";

	public function testConstruct_defaultsToFreeSubscription():void {
		$sut = new User(self::TEST_USER_ID, self::TEST_USER_EMAIL);

		self::assertSame(self::TEST_USER_ID, $sut->id);
		self::assertSame(self::TEST_USER_EMAIL, $sut->email);
		self::assertSame("free", $sut->subscriptionPlan);
	}

	public function testConstruct_acceptsSubscriptionPlan():void {
		$sut = new User(self::TEST_USER_ID, self::TEST_USER_EMAIL, "developer");

		self::assertSame("developer", $sut->subscriptionPlan);
	}
}
