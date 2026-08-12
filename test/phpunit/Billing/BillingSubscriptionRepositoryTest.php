<?php
namespace HexForm\Test\Billing;

use DateTimeImmutable;
use GT\Database\Query\QueryCollection;
use GT\Database\Result\Row;
use HexForm\Billing\BillingSubscription;
use HexForm\Billing\BillingSubscriptionRepository;
use PHPUnit\Framework\TestCase;

class BillingSubscriptionRepositoryTest extends TestCase {
	public function testGetForUser():void {
		$expected = $this->subscription();
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())->method("fetch")
			->with("getForUser", "user-1")
			->willReturn($this->row($expected));

		$result = (new BillingSubscriptionRepository($db))->getForUser("user-1");

		self::assertEquals($expected, $result);
	}

	public function testSave():void {
		$subscription = $this->subscription();
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())->method("insert")->with("save", [
			"userId" => "user-1",
			"stripeCustomerId" => "cus_1",
			"stripeSubscriptionId" => "sub_1",
			"plan" => "developer",
			"status" => "active",
			"latestPaymentAmount" => 500,
			"latestPaymentAt" => "2026-08-01 12:00:00",
			"nextPaymentAmount" => 500,
			"nextPaymentAt" => "2026-09-01 12:00:00",
			"currency" => "gbp",
			"checkedAt" => "2026-08-01 12:05:00",
			"cancelAtPeriodEnd" => 0,
		]);

		(new BillingSubscriptionRepository($db))->save($subscription);
	}

	private function subscription():BillingSubscription {
		return new BillingSubscription(
			"user-1", "cus_1", "sub_1", "developer", "active",
			500, new DateTimeImmutable("2026-08-01 12:00:00"),
			500, new DateTimeImmutable("2026-09-01 12:00:00"),
			"gbp", new DateTimeImmutable("2026-08-01 12:05:00"),
		);
	}

	private function row(BillingSubscription $subscription):Row {
		return new Row([
			"userId" => $subscription->userId,
			"stripeCustomerId" => $subscription->stripeCustomerId,
			"stripeSubscriptionId" => $subscription->stripeSubscriptionId,
			"plan" => $subscription->plan,
			"status" => $subscription->status,
			"latestPaymentAmount" => (string)$subscription->latestPaymentAmount,
			"latestPaymentAt" => $subscription->latestPaymentAt?->format("Y-m-d H:i:s"),
			"nextPaymentAmount" => (string)$subscription->nextPaymentAmount,
			"nextPaymentAt" => $subscription->nextPaymentAt?->format("Y-m-d H:i:s"),
			"currency" => $subscription->currency,
			"checkedAt" => $subscription->checkedAt->format("Y-m-d H:i:s"),
			"cancelAtPeriodEnd" => $subscription->cancelAtPeriodEnd ? "1" : "0",
		]);
	}
}
