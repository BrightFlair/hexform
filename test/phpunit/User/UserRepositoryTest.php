<?php
namespace HexForm\Test\User;

use Authwave\User as AuthUser;
use GT\Database\Query\QueryCollection;
use GT\Database\Result\Row;
use HexForm\User\User;
use HexForm\User\UserRepository;
use PHPUnit\Framework\TestCase;

class UserRepositoryTest extends TestCase {
	private const string TEST_USER_ID = "abc123";
	private const string TEST_USER_EMAIL = "test@hexform.io";
	private const string TEST_SUBSCRIPTION_PLAN = "free";

	public function testFromAuthwaveUser_newUser():void {
		$authUser = $this->createAuthUser();
		$expected = $this->createUser();
		$userRow = $this->createRow($expected);

		$db = self::createMock(QueryCollection::class);
		$db->expects(self::exactly(2))
			->method("fetch")
			->with("getById", $authUser->id)
			->willReturnOnConsecutiveCalls(null, $userRow);
		$db->expects(self::once())
			->method("insert")
			->with("create", ["id" => $authUser->id, "email" => $authUser->email]);

		$sut = new UserRepository($db);
		$user = $sut->fromAuthwaveUser($authUser);

		self::assertEquals($expected, $user);
	}

	public function testFromAuthwaveUser_existingUser():void {
		$expected = $this->createUser();
		$authUser = $this->createAuthUser(email: "changed@hexform.io");
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())
			->method("fetch")
			->with("getById", $authUser->id)
			->willReturn($this->createRow($expected));
		$db->expects(self::never())->method("insert");
		$sut = new UserRepository($db);

		$user = $sut->fromAuthwaveUser($authUser);

		self::assertEquals($expected, $user);
	}

	public function testGetById_returnsNullWhenMissing():void {
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())
			->method("fetch")
			->with("getById", "missing")
			->willReturn(null);
		$sut = new UserRepository($db);

		self::assertNull($sut->getById("missing"));
	}

	private function createAuthUser(
		string $id = self::TEST_USER_ID,
		string $email = self::TEST_USER_EMAIL,
	):AuthUser {
		return new AuthUser($id, $email);
	}

	private function createUser(
		string $id = self::TEST_USER_ID,
		string $email = self::TEST_USER_EMAIL,
		string $subscriptionPlan = self::TEST_SUBSCRIPTION_PLAN,
	):User {
		return new User($id, $email, $subscriptionPlan);
	}

	private function createRow(User $user):Row {
		return new Row([
			"id" => $user->id,
			"email" => $user->email,
			"subscriptionPlan" => $user->subscriptionPlan,
		]);
	}
}
