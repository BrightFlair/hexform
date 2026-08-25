<?php
namespace HexForm\Test\Endpoint;

use DateTimeImmutable;
use DateTimeInterface;
use HexForm\Endpoint\Endpoint;
use PHPUnit\Framework\TestCase;

class EndpointTest extends TestCase {
	private const string TEST_ENDPOINT_ID = "endpoint-1";
	private const string TEST_USER_ID = "user-1";
	private const string TEST_CODE = "form-code";
	private const string TEST_TITLE = "Contact form";
	private const string TEST_CLIENT_HOST = "https://example.com";

	public function testGetActionUrl():void {
		$sut = $this->createEndpoint();

		self::assertSame("https://hexform.io/f/$sut->code", $sut->getActionUrl());
	}

	public function testGetLastSubmittedDisplay():void {
		$sut = $this->createEndpoint(
			lastSubmitted: new DateTimeImmutable("2026-08-07 14:35:00"),
		);

		self::assertSame("7 Aug 2026, 14:35", $sut->getLastSubmittedDisplay());
	}

	public function testGetLastSubmittedDisplay_withoutSubmission():void {
		$sut = $this->createEndpoint();

		self::assertSame("Never", $sut->getLastSubmittedDisplay());
	}

	public function testGetRetentionValue():void {
		$sut = $this->createEndpoint(retentionMonths: 6);

		self::assertSame((string)$sut->retentionMonths, $sut->getRetentionValue());
	}

	public function testGetRetentionValue_withoutLimit():void {
		$sut = $this->createEndpoint();

		self::assertSame("forever", $sut->getRetentionValue());
	}

	public function testGetIgnoredKeyList():void {
		$sut = $this->createEndpoint(ignoredKeys: " do,csrf-token, ,__component ");

		self::assertSame(
			["do", "csrf-token", "__component"],
			$sut->getIgnoredKeyList(),
		);
	}

	public function testEnabledForwarders():void {
		$sut = $this->createEndpoint(enabledForwarders: "email,slack");

		self::assertSame(["email", "slack"], $sut->getEnabledForwarderList());
		self::assertTrue($sut->hasForwarder("email"));
		self::assertFalse($sut->hasForwarder("webhook"));
	}

	private function createEndpoint(
		string $id = self::TEST_ENDPOINT_ID,
		string $userId = self::TEST_USER_ID,
		string $code = self::TEST_CODE,
		string $title = self::TEST_TITLE,
		string $clientHost = self::TEST_CLIENT_HOST,
		?string $confirmationUrl = null,
		bool $junkDetection = true,
		?string $junkFieldName = "company",
		?string $mainField = null,
		?string $submitterIdentityField = null,
		?int $retentionMonths = null,
		int $maximumSubmissionsPerMonth = 50,
		?string $forwarderUrl = null,
		string $ignoredKeys = Endpoint::DEFAULT_IGNORED_KEYS,
		int $submissionCount = 3,
		?DateTimeInterface $lastSubmitted = null,
		string $enabledForwarders = Endpoint::DEFAULT_ENABLED_FORWARDERS,
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
			$enabledForwarders,
		);
	}
}
