<?php
namespace HexForm\Test\Forwarding;

use DateTimeImmutable;
use HexForm\Forwarding\EmailForwarder;
use PHPUnit\Framework\TestCase;

class EmailForwarderTest extends TestCase {
	public function testPendingForwarderCanBeResentAfterTwoMinutes():void {
		$sut = $this->createForwarder();

		self::assertSame("Pending", $sut->getStatus());
		self::assertFalse($sut->canResend(new DateTimeImmutable("2026-08-08 12:01:59")));
		self::assertTrue($sut->canResend(new DateTimeImmutable("2026-08-08 12:02:00")));
	}

	public function testConfirmedForwarderCanNeverBeResent():void {
		$sut = $this->createForwarder(new DateTimeImmutable("2026-08-08 12:01:00"));

		self::assertSame("Confirmed", $sut->getStatus());
		self::assertFalse($sut->canResend(new DateTimeImmutable("2026-08-09 12:00:00")));
	}

	private function createForwarder(?DateTimeImmutable $confirmedAt = null):EmailForwarder {
		return new EmailForwarder(
			"forwarder-1",
			"endpoint-1",
			"team@example.com",
			$confirmedAt,
			"12345",
			new DateTimeImmutable("2026-08-08 12:00:00"),
		);
	}
}
