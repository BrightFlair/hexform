<?php
namespace HexForm\Test\Submission;

use DateTimeImmutable;
use DateTimeInterface;
use GT\Database\Query\QueryCollection;
use GT\Database\Result\Row;
use HexForm\Endpoint\Endpoint;
use HexForm\Submission\Submission;
use HexForm\Submission\SubmissionRepository;
use HexForm\Test\Helper\DatabaseTestHelper;
use HexForm\User\User;
use PHPUnit\Framework\TestCase;

class SubmissionRepositoryTest extends TestCase {
	private const string TEST_SUBMISSION_ID = "submission-1";
	private const string TEST_ENDPOINT_ID = "endpoint-1";
	private const string TEST_USER_ID = "user-1";
	private const string TEST_USER_EMAIL = "person@example.com";
	private const string TEST_ENDPOINT_TITLE = "Contact form";
	private const string TEST_ENDPOINT_CODE = "form-code";
	private const string TEST_CREATED_AT = "2026-08-07 14:35:00";
	private const string TEST_MAIN_FIELD = "message";
	private const string TEST_SUBMITTER_IDENTITY_FIELD = "email";
	private const array TEST_DATA = [
		"email" => self::TEST_USER_EMAIL,
		"message" => "Hello",
	];

	public function testCreate():void {
		$endpoint = $this->createEndpoint();
		$data = ["email" => self::TEST_USER_EMAIL];
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())->method("insert")->with("create", [
			"id" => self::TEST_SUBMISSION_ID,
			"endpointId" => $endpoint->id,
			"data" => json_encode($data),
			"isJunk" => true,
		]);
		$sut = new SubmissionRepository($db);

		$sut->create(
			self::TEST_SUBMISSION_ID,
			$endpoint,
			$data,
			true,
		);
	}

	public function testGetForUser_mapsRowsAndFilters():void {
		$expected = $this->createSubmission();
		$user = $this->createUser();
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())
			->method("fetchAll")
			->with("getForUser", [
				"userId" => $user->id,
				"isJunk" => true,
				"endpointId" => self::TEST_ENDPOINT_ID,
			])
			->willReturn(DatabaseTestHelper::resultSet($this->createRow($expected)));
		$sut = new SubmissionRepository($db);

		$list = $sut->getForUser($user, true, self::TEST_ENDPOINT_ID);

		self::assertCount(1, $list);
		self::assertEquals($expected, $list[0]);
	}

	public function testGetByIdForUser_mapsRow():void {
		$expected = $this->createSubmission();
		$user = $this->createUser();
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())
			->method("fetch")
			->with("getByIdForUser", ["id" => $expected->id, "userId" => $user->id])
			->willReturn($this->createRow($expected));
		$sut = new SubmissionRepository($db);

		$submission = $sut->getByIdForUser($expected->id, $user);

		self::assertEquals($expected, $submission);
	}

	public function testGetByIdForUser_returnsNullWhenMissing():void {
		$user = $this->createUser();
		$db = self::createMock(QueryCollection::class);
		$db->method("fetch")->willReturn(null);
		$sut = new SubmissionRepository($db);

		self::assertNull($sut->getByIdForUser("missing", $user));
	}

	public function testDelete():void {
		$submission = $this->createSubmission();
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())->method("delete")->with("delete", $submission->id);
		$sut = new SubmissionRepository($db);

		$sut->delete($submission);
	}

	public function testDeleteByIdForUser_deletesOwnedSubmission():void {
		$submission = $this->createSubmission();
		$user = $this->createUser();
		$db = self::createMock(QueryCollection::class);
		$db->method("fetch")->willReturn($this->createRow($submission));
		$db->expects(self::once())->method("delete")->with("delete", $submission->id);
		$sut = new SubmissionRepository($db);

		$result = $sut->deleteByIdForUser($submission->id, $user);

		self::assertTrue($result);
	}

	public function testDeleteByIdForUser_doesNotDeleteMissingSubmission():void {
		$user = $this->createUser();
		$db = self::createMock(QueryCollection::class);
		$db->method("fetch")->willReturn(null);
		$db->expects(self::never())->method("delete");
		$sut = new SubmissionRepository($db);

		$result = $sut->deleteByIdForUser("missing", $user);

		self::assertFalse($result);
	}

	public function testMarkNotJunk():void {
		$submission = $this->createSubmission();
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())->method("update")->with("markNotJunk", $submission->id);
		$sut = new SubmissionRepository($db);

		$sut->markNotJunk($submission);
	}

	public function testMarkNotJunkByIdForUser_updatesOwnedSubmission():void {
		$submission = $this->createSubmission();
		$user = $this->createUser();
		$db = self::createMock(QueryCollection::class);
		$db->method("fetch")->willReturn($this->createRow($submission));
		$db->expects(self::once())->method("update")->with("markNotJunk", $submission->id);
		$sut = new SubmissionRepository($db);

		$result = $sut->markNotJunkByIdForUser($submission->id, $user);

		self::assertTrue($result);
	}

	public function testMarkNotJunkByIdForUser_doesNotUpdateMissingSubmission():void {
		$user = $this->createUser();
		$db = self::createMock(QueryCollection::class);
		$db->method("fetch")->willReturn(null);
		$db->expects(self::never())->method("update");
		$sut = new SubmissionRepository($db);

		$result = $sut->markNotJunkByIdForUser("missing", $user);

		self::assertFalse($result);
	}

	public function testGetDashboard_mapsSummaryAndDailyCounts():void {
		$user = $this->createUser();
		$filter = ["userId" => $user->id, "endpointId" => self::TEST_ENDPOINT_ID];
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())
			->method("fetch")
			->with("getDashboardSummary", $filter)
			->willReturn(new Row(["total" => "12", "junk" => "3", "thisMonth" => "8"]));
		$db->expects(self::once())
			->method("fetchAll")
			->with("getDailyCounts", $filter)
			->willReturn(DatabaseTestHelper::resultSet(
				new Row(["day" => "2026-08-06", "submissionCount" => "2"]),
				new Row(["day" => "2026-08-07", "submissionCount" => "4"]),
			));
		$sut = new SubmissionRepository($db);

		$dashboard = $sut->getDashboard($user, self::TEST_ENDPOINT_ID);

		self::assertSame([
			"total" => 12,
			"junk" => 3,
			"thisMonth" => 8,
			"daily" => [["2026-08-06", 2], ["2026-08-07", 4]],
		], $dashboard);
	}

	public function testGetDashboard_defaultsMissingSummaryToZero():void {
		$user = $this->createUser();
		$db = self::createMock(QueryCollection::class);
		$db->method("fetch")->willReturn(null);
		$db->method("fetchAll")->willReturn(DatabaseTestHelper::resultSet());
		$sut = new SubmissionRepository($db);

		$dashboard = $sut->getDashboard($user);

		self::assertSame([
			"total" => 0,
			"junk" => 0,
			"thisMonth" => 0,
			"daily" => [],
		], $dashboard);
	}

	private function createEndpoint(
		string $id = self::TEST_ENDPOINT_ID,
		string $userId = self::TEST_USER_ID,
	):Endpoint {
		return new Endpoint(
			$id,
			$userId,
			self::TEST_ENDPOINT_CODE,
			self::TEST_ENDPOINT_TITLE,
			"https://example.com",
			null,
			true,
			"company",
			self::TEST_MAIN_FIELD,
			self::TEST_SUBMITTER_IDENTITY_FIELD,
			6,
			100,
			null,
		);
	}

	/** @param array<string, mixed> $data */
	private function createSubmission(
		string $id = self::TEST_SUBMISSION_ID,
		string $endpointId = self::TEST_ENDPOINT_ID,
		string $endpointTitle = self::TEST_ENDPOINT_TITLE,
		string $endpointCode = self::TEST_ENDPOINT_CODE,
		array $data = self::TEST_DATA,
		bool $isJunk = true,
		DateTimeInterface $createdAt = new DateTimeImmutable(self::TEST_CREATED_AT),
		?string $mainField = self::TEST_MAIN_FIELD,
		?string $submitterIdentityField = self::TEST_SUBMITTER_IDENTITY_FIELD,
	):Submission {
		return new Submission(
			$id,
			$endpointId,
			$endpointTitle,
			$endpointCode,
			$data,
			$isJunk,
			$createdAt,
			$mainField,
			$submitterIdentityField,
		);
	}

	private function createUser():User {
		return new User(self::TEST_USER_ID, self::TEST_USER_EMAIL);
	}

	private function createRow(Submission $submission):Row {
		return new Row(array_map(
			fn(bool|string $value):string => (string)$value,
			array_filter([
				"id" => $submission->id,
				"endpointId" => $submission->endpointId,
				"endpointTitle" => $submission->endpointTitle,
				"endpointCode" => $submission->endpointCode,
				"data" => json_encode($submission->data),
				"isJunk" => $submission->isJunk,
				"createdAt" => $submission->createdAt->format("Y-m-d H:i:s"),
				"mainField" => $submission->mainField,
				"submitterIdentityField" => $submission->submitterIdentityField,
			], fn(mixed $value):bool => $value !== null),
		));
	}
}
