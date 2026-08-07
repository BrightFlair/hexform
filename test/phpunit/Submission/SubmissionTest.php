<?php
namespace HexForm\Test\Submission;

use DateTimeImmutable;
use DateTimeInterface;
use HexForm\Submission\Submission;
use PHPUnit\Framework\TestCase;

class SubmissionTest extends TestCase {
	private const string TEST_SUBMISSION_ID = "submission-1";
	private const string TEST_ENDPOINT_ID = "endpoint-1";
	private const string TEST_ENDPOINT_TITLE = "Contact form";
	private const string TEST_ENDPOINT_CODE = "form-code";
	private const string TEST_CREATED_AT = "2026-08-07 14:35:00";
	private const string TEST_MAIN_FIELD = "message";
	private const string TEST_SUBMITTER_IDENTITY_FIELD = "email";

	public function testGetDateDisplay():void {
		$sut = $this->createSubmission();

		self::assertSame("7 Aug 2026, 14:35", $sut->getDateDisplay());
	}

	public function testGetSubmitter_usesConfiguredField():void {
		$sut = $this->createSubmission([
			"email" => "person@example.com",
		]);

		self::assertSame("person@example.com", $sut->getSubmitter());
	}

	public function testGetSubmitter_withoutConfiguredField():void {
		$sut = $this->createSubmission(
			data: [],
			submitterIdentityField: null,
		);

		self::assertSame("Unknown", $sut->getSubmitter());
	}

	public function testGetSubmitter_whenConfiguredFieldIsMissing():void {
		$sut = $this->createSubmission([]);

		self::assertSame("Unknown", $sut->getSubmitter());
	}

	public function testGetSubmitter_serialisesStructuredValue():void {
		$sut = $this->createSubmission([
			"email" => ["address" => "person@example.com"],
		]);

		self::assertSame('{"address":"person@example.com"}', $sut->getSubmitter());
	}

	public function testGetSubmitter_returnsEmptyStringForUnencodableValue():void {
		$resource = fopen("php://memory", "r");
		$sut = $this->createSubmission(["email" => $resource]);

		try {
			self::assertSame("", $sut->getSubmitter());
		}
		finally {
			fclose($resource);
		}
	}

	public function testGetContents_usesConfiguredFieldAndTruncatesPreview():void {
		$sut = $this->createSubmission([
			"message" => str_repeat("a", 110),
		]);

		self::assertSame(str_repeat("a", 99) . "…", $sut->getContents());
	}

	public function testGetContents_withoutConfiguredField():void {
		$sut = $this->createSubmission(data: [], mainField: null);

		self::assertSame("No preview configured", $sut->getContents());
	}

	public function testGetContents_whenConfiguredFieldIsMissing():void {
		$sut = $this->createSubmission([]);

		self::assertSame("No preview configured", $sut->getContents());
	}

	public function testGetContents_serialisesStructuredValue():void {
		$sut = $this->createSubmission([
			"message" => ["first", "second"],
		]);

		self::assertSame('["first","second"]', $sut->getContents());
	}

	public function testGetDataRows_serialisesAllValues():void {
		$sut = $this->createSubmission([
			"email" => ["address" => "person@example.com"],
			"message" => ["first", "second"],
			"count" => 2,
		]);

		self::assertSame([
			["field" => "email", "value" => '{"address":"person@example.com"}'],
			["field" => "message", "value" => '["first","second"]'],
			["field" => "count", "value" => "2"],
		], $sut->getDataRows());
	}

	public function testGetDataRows_returnsEmptyStringForUnencodableValue():void {
		$resource = fopen("php://memory", "r");
		$sut = $this->createSubmission(["email" => $resource]);

		try {
			self::assertSame([["field" => "email", "value" => ""]], $sut->getDataRows());
		}
		finally {
			fclose($resource);
		}
	}

	/** @param array<string, mixed> $data */
	private function createSubmission(
		array $data = [],
		string $id = self::TEST_SUBMISSION_ID,
		string $endpointId = self::TEST_ENDPOINT_ID,
		string $endpointTitle = self::TEST_ENDPOINT_TITLE,
		string $endpointCode = self::TEST_ENDPOINT_CODE,
		bool $isJunk = false,
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
}
