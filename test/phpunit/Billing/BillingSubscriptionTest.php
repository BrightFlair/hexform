<?php
namespace HexForm\Test\Billing;

use DateTimeImmutable;
use HexForm\Billing\BillingSubscription;
use PHPUnit\Framework\TestCase;

class BillingSubscriptionTest extends TestCase {
	public function testIsActive_statuses():void {
		self::assertTrue($this->subscription(status: "active")->isActive());
		self::assertTrue($this->subscription(status: "trialing")->isActive());
		self::assertFalse($this->subscription(status: "past_due")->isActive());
	}

	public function testNeedsRefresh_atNextPaymentDate():void {
		$now = new DateTimeImmutable("2026-09-01 12:00:00");

		self::assertFalse($this->subscription(nextPaymentAt: $now->modify("+1 second"))->needsRefresh($now));
		self::assertTrue($this->subscription(nextPaymentAt: $now)->needsRefresh($now));
		self::assertFalse($this->subscription(nextPaymentAt: null)->needsRefresh($now));
		self::assertTrue($this->subscription(
			nextPaymentAt: null,
			checkedAt: $now->modify("-1 month"),
		)->needsRefresh($now));
	}

	public function testFormatAmount():void {
		$sut = $this->subscription(latestPaymentAmount: 1299);

		self::assertSame("GBP 12.99", $sut->formatAmount($sut->latestPaymentAmount));
		self::assertSame("Not available", $sut->formatAmount(null));
	}

	private function subscription(
		string $status = "active",
		?int $latestPaymentAmount = 1200,
		?DateTimeImmutable $nextPaymentAt = new DateTimeImmutable("2026-09-01"),
		DateTimeImmutable $checkedAt = new DateTimeImmutable("2026-08-15"),
	):BillingSubscription {
		return new BillingSubscription(
			"user-1", "cus_1", "sub_1", "developer", $status,
			$latestPaymentAmount, new DateTimeImmutable("2026-08-01"),
			1200, $nextPaymentAt, "gbp", $checkedAt,
		);
	}
}
