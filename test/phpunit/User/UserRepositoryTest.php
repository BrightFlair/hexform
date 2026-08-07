<?php
namespace HexForm\Test\User;

use Authwave\User as AuthUser;
use GT\Database\Query\QueryCollection;
use GT\Database\Result\Row;
use HexForm\User\User;
use HexForm\User\UserRepository;
use PHPUnit\Framework\TestCase;

class UserRepositoryTest extends TestCase {
	public function testFromAuthwaveUser_newUser():void {
		$userRow = self::createMock(Row::class);
		$userRow->method("getString")
			->willReturnMap([
				["id", "abc123"],
				["email", "test@hexform.io"],
				["subscriptionPlan", "free"],
			]);

		$db = self::createMock(QueryCollection::class);
		$db->expects(self::exactly(2))
			->method("fetch")
			->with("getById", "abc123")
			->willReturnOnConsecutiveCalls(null, $userRow);
		$db->expects(self::once())
			->method("insert")
			->with("create", ["id" => "abc123", "email" => "test@hexform.io"]);

		$authUser = new AuthUser("abc123", "test@hexform.io");

		$sut = new UserRepository($db);
		$user = $sut->fromAuthwaveUser($authUser);

		self::assertInstanceOf(User::class, $user);
		self::assertSame("abc123", $user->id);
		self::assertSame("test@hexform.io", $user->email);
		self::assertSame("free", $user->subscriptionPlan);
	}
}
