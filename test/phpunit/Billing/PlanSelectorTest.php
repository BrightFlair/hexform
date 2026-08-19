<?php
namespace HexForm\Test\Billing;

use HexForm\Audit\AuditLog;
use HexForm\Billing\BillingService;
use HexForm\Billing\PlanSelector;
use HexForm\User\User;
use PHPUnit\Framework\TestCase;

class PlanSelectorTest extends TestCase {
	public function testSelect_successfulFreePlanCancellation():void {
		$user = new User("user-1", "person@example.com", "developer");
		$billing = self::createMock(BillingService::class);
		$billing->expects(self::once())->method("selectFreePlan")
			->with($user)->willReturn(true);
		$audit = $this->successfulAudit($user, "free");

		$result = (new PlanSelector($billing, $audit))->select(
			$user,
			"free",
			"https://hexform.test",
		);

		self::assertSame("/app/account/", $result->redirectUrl);
		self::assertSame(
			"Your subscription will change to Free after your current paid period ends.",
			$result->message,
		);
	}

	public function testSelect_successfulPaidPlanChange():void {
		$user = new User("user-1", "person@example.com", "developer");
		$billing = self::createMock(BillingService::class);
		$billing->expects(self::once())->method("selectPaidPlan")
			->with(
				$user,
				"enterprise",
				"https://hexform.test/app/account/?checkout=success&session_id={CHECKOUT_SESSION_ID}",
				"https://hexform.test/app/account/?checkout=cancelled&signup=enterprise",
			)->willReturn(null);
		$billing->expects(self::once())->method("getSubscription")
			->with($user)->willReturn(null);
		$audit = $this->successfulAudit($user, "enterprise");

		$result = (new PlanSelector($billing, $audit))->select(
			$user,
			"enterprise",
			"https://hexform.test",
		);

		self::assertSame("/app/account/", $result->redirectUrl);
		self::assertSame("Your subscription plan is now Enterprise.", $result->message);
	}

	private function successfulAudit(User $user, string $plan):AuditLog {
		$audit = self::createMock(AuditLog::class);
		$audit->expects(self::once())->method("record")->with(
			$user->id,
			null,
			"subscription",
			$user->id,
			"change-plan",
			"succeeded",
			["plan" => $plan],
		);
		return $audit;
	}
}
