<?php
namespace HexForm\Test\Forwarding;

use DateTimeImmutable;
use GT\Database\Query\QueryCollection;
use GT\Database\Result\Row;
use HexForm\Endpoint\Endpoint;
use HexForm\Forwarding\EmailForwarder;
use HexForm\Forwarding\EmailForwarderRepository;
use HexForm\Test\Helper\DatabaseTestHelper;
use HexForm\User\User;
use PHPUnit\Framework\TestCase;

class EmailForwarderRepositoryTest extends TestCase {
	private const EMAIL = "team@example.com";

	public function testCreateStoresPendingConfirmation():void {
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())->method("insert")->with("create", [
			"id" => "forwarder-1",
			"endpointId" => "endpoint-1",
			"email" => self::EMAIL,
			"confirmationCode" => "12345",
			"confirmationCreatedAt" => "2026-08-08 12:00:00",
		]);
		$sut = new EmailForwarderRepository($db);

		$sut->create(
			"forwarder-1",
			$this->endpoint(),
			self::EMAIL,
			"12345",
			new DateTimeImmutable("2026-08-08 12:00:00"),
		);
	}

	public function testGetForEndpointIsScopedToUser():void {
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())->method("fetchAll")
			->with("getForEndpointByUser", ["endpointId" => "endpoint-1", "userId" => "user-1"])
			->willReturn(DatabaseTestHelper::resultSet($this->row()));
		$sut = new EmailForwarderRepository($db);

		$list = $sut->getForEndpointByUser($this->endpoint(), new User("user-1", "owner@example.com"));

		self::assertCount(1, $list);
		self::assertSame(self::EMAIL, $list[0]->email);
		self::assertFalse($list[0]->isConfirmed());
	}

	public function testConfirmRejectsWrongCodeWithoutWriting():void {
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::never())->method("update");
		$sut = new EmailForwarderRepository($db);

		self::assertFalse($sut->confirm($this->forwarder(), "99999"));
	}

	public function testConfirmWritesMatchingCode():void {
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())->method("update")->with("confirm", [
			"id" => "forwarder-1",
			"confirmationCode" => "12345",
		]);
		$sut = new EmailForwarderRepository($db);

		self::assertTrue($sut->confirm($this->forwarder(), "12345"));
	}

	public function testDeleteRemovesForwarder():void {
		$forwarder = $this->forwarder();
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())->method("delete")->with("delete", $forwarder->id);
		$sut = new EmailForwarderRepository($db);

		$sut->delete($forwarder);
	}

	public function testResendRejectsRequestBeforeDelay():void {
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::never())->method("update");
		$sut = new EmailForwarderRepository($db);

		self::assertFalse($sut->resend(
			$this->forwarder(),
			"54321",
			new DateTimeImmutable("2026-08-08 12:01:59"),
		));
	}

	private function forwarder():EmailForwarder {
		return new EmailForwarder(
			"forwarder-1", "endpoint-1", self::EMAIL, null, "12345",
			new DateTimeImmutable("2026-08-08 12:00:00"),
		);
	}

	private function row():Row {
		return new Row([
			"id" => "forwarder-1",
			"endpointId" => "endpoint-1",
			"email" => self::EMAIL,
			"confirmationCode" => "12345",
			"confirmationCreatedAt" => "2026-08-08 12:00:00",
		]);
	}

	private function endpoint():Endpoint {
		return new Endpoint(
			"endpoint-1", "user-1", "code", "Contact form", "https://example.com",
			null, true, "company", "message", "email", 1, 50, null,
		);
	}
}
