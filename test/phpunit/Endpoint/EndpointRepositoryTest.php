<?php
namespace HexForm\Test\Endpoint;

use DateTimeImmutable;
use DateTimeInterface;
use GT\Database\Query\QueryCollection;
use GT\Database\Result\Row;
use HexForm\Endpoint\Endpoint;
use HexForm\Endpoint\EndpointRepository;
use HexForm\Test\Helper\DatabaseTestHelper;
use HexForm\User\User;
use PHPUnit\Framework\TestCase;

class EndpointRepositoryTest extends TestCase {
	private const string TEST_ENDPOINT_ID = "endpoint-1";
	private const string TEST_USER_ID = "user-1";
	private const string TEST_USER_EMAIL = "person@example.com";
	private const string TEST_CODE = "form-code";
	private const string TEST_TITLE = "Contact form";
	private const string TEST_CLIENT_HOST = "https://example.com";
	private const string TEST_CONFIRMATION_URL = "https://example.com/thanks";
	private const string TEST_JUNK_FIELD_NAME = "company";
	private const string TEST_MAIN_FIELD = "message";
	private const string TEST_SUBMITTER_IDENTITY_FIELD = "email";
	private const string TEST_FORWARDER_URL = "https://example.com/hook";
	private const string TEST_LAST_SUBMITTED = "2026-08-07 14:35:00";

	public function testCreate():void {
		$endpoint = $this->createEndpoint();
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())
			->method("insert")
			->with("create", $this->expectedParameters($endpoint));
		$sut = new EndpointRepository($db);

		$sut->create($endpoint);
	}

	public function testUpdate():void {
		$endpoint = $this->createEndpoint();
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())
			->method("update")
			->with("update", $this->expectedParameters($endpoint));
		$sut = new EndpointRepository($db);

		$sut->update($endpoint);
	}

	public function testDelete():void {
		$endpoint = $this->createEndpoint();
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())->method("delete")->with("delete", $endpoint->id);
		$sut = new EndpointRepository($db);

		$sut->delete($endpoint);
	}

	public function testDeleteByIdForUser_deletesOwnedEndpoint():void {
		$endpoint = $this->createEndpoint();
		$user = $this->createUser();
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())
			->method("fetch")
			->with("getByIdForUser", ["id" => $endpoint->id, "userId" => $user->id])
			->willReturn($this->createRow($endpoint));
		$db->expects(self::once())->method("delete")->with("delete", $endpoint->id);
		$sut = new EndpointRepository($db);

		$result = $sut->deleteByIdForUser($endpoint->id, $user);

		self::assertTrue($result);
	}

	public function testDeleteByIdForUser_doesNotDeleteMissingEndpoint():void {
		$user = $this->createUser();
		$db = self::createMock(QueryCollection::class);
		$db->method("fetch")->willReturn(null);
		$db->expects(self::never())->method("delete");
		$sut = new EndpointRepository($db);

		$result = $sut->deleteByIdForUser("missing", $user);
		self::assertFalse($result);
	}

	public function testGetForUser_mapsRows():void {
		$endpoint = $this->createEndpoint();
		$user = $this->createUser();
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())
			->method("fetchAll")
			->with("getForUser", $user->id)
			->willReturn(DatabaseTestHelper::resultSet($this->createRow($endpoint)));
		$sut = new EndpointRepository($db);

		$list = $sut->getForUser($user);

		self::assertCount(1, $list);
		self::assertEquals($endpoint, $list[0]);
	}

	public function testGetByIdForUser_mapsRow():void {
		$expected = $this->createEndpoint();
		$user = $this->createUser();
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())
			->method("fetch")
			->with("getByIdForUser", ["id" => $expected->id, "userId" => $user->id])
			->willReturn($this->createRow($expected));
		$sut = new EndpointRepository($db);

		$endpoint = $sut->getByIdForUser($expected->id, $user);

		self::assertEquals($expected, $endpoint);
	}

	public function testGetByCode_returnsNullWhenMissing():void {
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())
			->method("fetch")
			->with("getByCode", "missing")
			->willReturn(null);
		$sut = new EndpointRepository($db);

		self::assertNull($sut->getByCode("missing"));
	}

	private function createEndpoint(
		string $id = self::TEST_ENDPOINT_ID,
		string $userId = self::TEST_USER_ID,
		string $code = self::TEST_CODE,
		string $title = self::TEST_TITLE,
		string $clientHost = self::TEST_CLIENT_HOST,
		?string $confirmationUrl = self::TEST_CONFIRMATION_URL,
		bool $junkDetection = true,
		?string $junkFieldName = self::TEST_JUNK_FIELD_NAME,
		?string $mainField = self::TEST_MAIN_FIELD,
		?string $submitterIdentityField = self::TEST_SUBMITTER_IDENTITY_FIELD,
		?int $retentionMonths = 6,
		int $maximumSubmissionsPerMonth = 100,
		?string $forwarderUrl = self::TEST_FORWARDER_URL,
		string $ignoredKeys = Endpoint::DEFAULT_IGNORED_KEYS,
		int $submissionCount = 7,
		?DateTimeInterface $lastSubmitted = new DateTimeImmutable(self::TEST_LAST_SUBMITTED),
	):Endpoint {
		return new Endpoint(
			$id,
			$userId,
			$code,
			$title,
			$clientHost,
			$confirmationUrl,
			$junkDetection,
			$junkFieldName,
			$mainField,
			$submitterIdentityField,
			$retentionMonths,
			$maximumSubmissionsPerMonth,
			$forwarderUrl,
			$ignoredKeys,
			$submissionCount,
			$lastSubmitted,
		);
	}

	private function createUser():User {
		return new User(self::TEST_USER_ID, self::TEST_USER_EMAIL);
	}

	/** @return array<string, bool|int|string|null> */
	private function expectedParameters(Endpoint $endpoint):array {
		return [
			"id" => $endpoint->id,
			"userId" => $endpoint->userId,
			"code" => $endpoint->code,
			"title" => $endpoint->title,
			"clientHost" => $endpoint->clientHost,
			"confirmationUrl" => $endpoint->confirmationUrl,
			"junkDetection" => $endpoint->junkDetection,
			"junkFieldName" => $endpoint->junkFieldName,
			"mainField" => $endpoint->mainField,
			"submitterIdentityField" => $endpoint->submitterIdentityField,
			"retentionMonths" => $endpoint->retentionMonths,
			"maximumSubmissionsPerMonth" => $endpoint->maximumSubmissionsPerMonth,
			"forwarderUrl" => $endpoint->forwarderUrl,
			"ignoredKeys" => $endpoint->ignoredKeys,
		];
	}

	private function createRow(Endpoint $endpoint):Row {
		$data = array_map(
			fn(bool|int|string $value):string => (string)$value,
			array_filter(
				[
					...$this->expectedParameters($endpoint),
					"submissionCount" => $endpoint->submissionCount,
					"lastSubmitted" => $endpoint->lastSubmitted?->format("Y-m-d H:i:s"),
				],
				fn(mixed $value):bool => $value !== null,
			),
		);

		return new Row($data);
	}
}
