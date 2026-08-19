<?php
namespace HexForm\Test\Billing;

use DateTimeImmutable;
use HexForm\Billing\BillingGateway;
use HexForm\Billing\BillingService;
use HexForm\Billing\BillingSubscription;
use HexForm\Billing\BillingSubscriptionRepository;
use HexForm\User\User;
use HexForm\User\UserRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BillingServiceTest extends TestCase {
	public function testStartCheckout():void {
		$user = new User("user-1", "person@example.com");
		$gateway = self::createMock(BillingGateway::class);
		$gateway->expects(self::once())->method("createCheckout")
			->with($user, "developer", "success", "cancel", null)
			->willReturn("https://checkout.stripe.test/session");

		$url = $this->service($gateway)->startCheckout($user, "developer", "success", "cancel");

		self::assertSame("https://checkout.stripe.test/session", $url);
	}

	public function testSelectPaidPlan_changesExistingSubscriptionInsteadOfCreatingAnother():void {
		$user = new User("user-1", "person@example.com", "developer");
		$current = $this->subscription();
		$changed = $this->subscription(plan: "enterprise");
		$gateway = self::createMock(BillingGateway::class);
		$gateway->expects(self::never())->method("createCheckout");
		$gateway->expects(self::once())->method("changeSubscription")
			->with($current, "enterprise")->willReturn($changed);
		$subscriptions = self::createMock(BillingSubscriptionRepository::class);
		$subscriptions->method("getForUser")->willReturn($current);
		$subscriptions->expects(self::once())->method("save")->with($changed);
		$users = self::createMock(UserRepository::class);
		$users->expects(self::once())->method("setSubscriptionPlan")
			->with($user, "enterprise");

		$result = (new BillingService($gateway, $subscriptions, $users))->selectPaidPlan(
			$user,
			"enterprise",
			"success",
			"cancel",
		);

		self::assertNull($result);
	}

	public function testSelectPaidPlan_doesNothingWhenPlanIsAlreadyActive():void {
		$user = new User("user-1", "person@example.com", "developer");
		$gateway = self::createMock(BillingGateway::class);
		$gateway->expects(self::never())->method("createCheckout");
		$gateway->expects(self::never())->method("changeSubscription");
		$subscriptions = self::createStub(BillingSubscriptionRepository::class);
		$subscriptions->method("getForUser")->willReturn($this->subscription());

		$result = $this->service($gateway, $subscriptions)->selectPaidPlan(
			$user,
			"developer",
			"success",
			"cancel",
		);

		self::assertNull($result);
	}

	public function testSelectPaidPlan_doesNotClaimSuccessForPendingStripeUpdate():void {
		$user = new User("user-1", "person@example.com", "developer");
		$current = $this->subscription();
		$gateway = self::createStub(BillingGateway::class);
		$gateway->method("changeSubscription")->willReturn($current);
		$subscriptions = self::createMock(BillingSubscriptionRepository::class);
		$subscriptions->method("getForUser")->willReturn($current);
		$subscriptions->expects(self::never())->method("save");
		$users = self::createMock(UserRepository::class);
		$users->expects(self::never())->method("setSubscriptionPlan");
		$this->expectException(\RuntimeException::class);

		(new BillingService($gateway, $subscriptions, $users))->selectPaidPlan(
			$user,
			"enterprise",
			"success",
			"cancel",
		);
	}

	public function testSelectFreePlan_schedulesCancellationAndKeepsPaidAccess():void {
		$user = new User("user-1", "person@example.com", "developer");
		$current = $this->subscription();
		$cancelled = $this->subscription(cancelAtPeriodEnd: true);
		$gateway = self::createMock(BillingGateway::class);
		$gateway->expects(self::once())->method("cancelSubscription")
			->with($current)->willReturn($cancelled);
		$subscriptions = self::createMock(BillingSubscriptionRepository::class);
		$subscriptions->method("getForUser")->willReturn($current);
		$subscriptions->expects(self::once())->method("save")
			->with($cancelled->withPendingPlan("free"));
		$users = self::createMock(UserRepository::class);
		$users->expects(self::never())->method("setSubscriptionPlan");

		(new BillingService($gateway, $subscriptions, $users))->selectFreePlan($user);
	}

	public function testSelectPaidPlan_withdrawsScheduledCancellationForCurrentPlan():void {
		$user = new User("user-1", "person@example.com", "developer");
		$current = $this->subscription(cancelAtPeriodEnd: true);
		$resumed = $this->subscription();
		$gateway = self::createMock(BillingGateway::class);
		$gateway->expects(self::never())->method("changeSubscription");
		$gateway->expects(self::once())->method("resumeSubscription")
			->with($current)->willReturn($resumed);
		$subscriptions = self::createMock(BillingSubscriptionRepository::class);
		$subscriptions->method("getForUser")->willReturn($current);
		$subscriptions->expects(self::once())->method("save")->with($resumed);
		$users = self::createMock(UserRepository::class);
		$users->expects(self::once())->method("setSubscriptionPlan")
			->with($user, "developer");

		(new BillingService($gateway, $subscriptions, $users))->selectPaidPlan(
			$user,
			"developer",
			"success",
			"cancel",
		);
	}

	public function testSelectPaidPlan_schedulesPaidDowngradeForNextBillingPeriod():void {
		$user = new User("user-1", "person@example.com", "enterprise");
		$current = $this->subscription(plan: "enterprise");
		$scheduled = $this->subscription(plan: "enterprise", pendingPlan: "developer");
		$gateway = self::createMock(BillingGateway::class);
		$gateway->expects(self::never())->method("changeSubscription");
		$gateway->expects(self::once())->method("scheduleSubscriptionChange")
			->with($current, "developer")->willReturn($scheduled);
		$subscriptions = self::createMock(BillingSubscriptionRepository::class);
		$subscriptions->method("getForUser")->willReturn($current);
		$subscriptions->expects(self::once())->method("save")->with($scheduled);
		$users = self::createMock(UserRepository::class);
		$users->expects(self::once())->method("setSubscriptionPlan")
			->with($user, "enterprise");

		(new BillingService($gateway, $subscriptions, $users))->selectPaidPlan(
			$user,
			"developer",
			"success",
			"cancel",
		);
	}

	public function testSelectPaidPlan_withdrawsPendingDowngradeForCurrentPlan():void {
		$user = new User("user-1", "person@example.com", "enterprise");
		$scheduled = $this->subscription(plan: "enterprise", pendingPlan: "developer");
		$current = $this->subscription(plan: "enterprise");
		$gateway = self::createMock(BillingGateway::class);
		$gateway->expects(self::once())->method("clearScheduledChange")
			->with($scheduled)->willReturn($current);
		$gateway->expects(self::never())->method("changeSubscription");
		$subscriptions = self::createMock(BillingSubscriptionRepository::class);
		$subscriptions->method("getForUser")->willReturn($scheduled);
		$subscriptions->expects(self::once())->method("save")->with($current);
		$users = self::createMock(UserRepository::class);
		$users->expects(self::once())->method("setSubscriptionPlan")
			->with($user, "enterprise");

		(new BillingService($gateway, $subscriptions, $users))->selectPaidPlan(
			$user,
			"enterprise",
			"success",
			"cancel",
		);
	}

	public function testSelectFreePlan_keepsPaidPlanWhenStripeCancellationFails():void {
		$user = new User("user-1", "person@example.com", "developer");
		$gateway = self::createStub(BillingGateway::class);
		$gateway->method("cancelSubscription")->willThrowException(new \RuntimeException());
		$subscriptions = self::createStub(BillingSubscriptionRepository::class);
		$subscriptions->method("getForUser")->willReturn($this->subscription());
		$users = self::createMock(UserRepository::class);
		$users->expects(self::never())->method("setSubscriptionPlan");
		$this->expectException(\RuntimeException::class);

		(new BillingService($gateway, $subscriptions, $users))->selectFreePlan($user);
	}

	public function testStartCheckout_rejectsUnknownPlan():void {
		$gateway = self::createStub(BillingGateway::class);
		$this->expectException(InvalidArgumentException::class);

		$this->service($gateway)->startCheckout(
			new User("user-1", "person@example.com"),
			"free",
			"success",
			"cancel",
		);
	}

	public function testCompleteCheckout_storesActivePlan():void {
		$user = new User("user-1", "person@example.com");
		$subscription = $this->subscription();
		$gateway = self::createMock(BillingGateway::class);
		$gateway->expects(self::once())->method("completeCheckout")
			->with("cs_1", $user)->willReturn($subscription);
		$subscriptions = self::createMock(BillingSubscriptionRepository::class);
		$subscriptions->expects(self::once())->method("save")->with($subscription);
		$users = self::createMock(UserRepository::class);
		$users->expects(self::once())->method("setSubscriptionPlan")
			->with($user, "developer");

		$result = (new BillingService($gateway, $subscriptions, $users))
			->completeCheckout("cs_1", $user);

		self::assertSame($subscription, $result);
	}

	public function testCompleteCheckout_removesAccessForInactiveSubscription():void {
		$user = new User("user-1", "person@example.com", "developer");
		$subscription = $this->subscription(status: "past_due");
		$gateway = self::createMock(BillingGateway::class);
		$gateway->method("completeCheckout")->willReturn($subscription);
		$subscriptions = self::createMock(BillingSubscriptionRepository::class);
		$users = self::createMock(UserRepository::class);
		$users->expects(self::once())->method("setSubscriptionPlan")->with($user, null);

		(new BillingService($gateway, $subscriptions, $users))->completeCheckout("cs_1", $user);
	}

	public function testRefreshIfDue_usesCacheBeforePaymentDate():void {
		$user = new User("user-1", "person@example.com", "developer");
		$subscription = $this->subscription(nextPaymentAt: new DateTimeImmutable("tomorrow"));
		$gateway = self::createMock(BillingGateway::class);
		$gateway->expects(self::never())->method("retrieveSubscription");
		$subscriptions = self::createMock(BillingSubscriptionRepository::class);
		$subscriptions->method("getForUser")->with($user->id)->willReturn($subscription);

		$result = $this->service($gateway, $subscriptions)->refreshIfDue($user);

		self::assertSame($subscription, $result);
	}

	public function testCompleteWebhookCheckout_storesSubscriptionWithoutBrowserReturn():void {
		$user = new User("user-1", "person@example.com");
		$subscription = $this->subscription();
		$gateway = self::createMock(BillingGateway::class);
		$gateway->expects(self::once())->method("retrieveSubscription")
			->with("sub_1", $user->id)->willReturn($subscription);
		$subscriptions = self::createMock(BillingSubscriptionRepository::class);
		$subscriptions->expects(self::once())->method("save")->with($subscription);
		$users = self::createMock(UserRepository::class);
		$users->method("getById")->with($user->id)->willReturn($user);
		$users->expects(self::once())->method("setSubscriptionPlan")->with($user, "developer");

		(new BillingService($gateway, $subscriptions, $users))
			->completeWebhookCheckout($user->id, "sub_1");
	}

	public function testRefreshByCustomerId_removesPaidAccessForCancelledSubscription():void {
		$user = new User("user-1", "person@example.com", "developer");
		$current = $this->subscription();
		$cancelled = $this->subscription(status: "canceled");
		$gateway = self::createMock(BillingGateway::class);
		$gateway->method("retrieveSubscription")->willReturn($cancelled);
		$subscriptions = self::createMock(BillingSubscriptionRepository::class);
		$subscriptions->method("getByStripeCustomerId")->with("cus_1")->willReturn($current);
		$subscriptions->expects(self::once())->method("save")->with($cancelled);
		$users = self::createMock(UserRepository::class);
		$users->method("getById")->with($user->id)->willReturn($user);
		$users->expects(self::once())->method("setSubscriptionPlan")->with($user, null);

		(new BillingService($gateway, $subscriptions, $users))->refreshByCustomerId("cus_1");
	}

	public function testRefreshIfDue_retrievesAndStoresNewPaymentPeriod():void {
		$user = new User("user-1", "person@example.com", "developer");
		$stale = $this->subscription(nextPaymentAt: new DateTimeImmutable("yesterday"));
		$fresh = $this->subscription(nextPaymentAt: new DateTimeImmutable("next month"));
		$gateway = self::createMock(BillingGateway::class);
		$gateway->expects(self::once())->method("retrieveSubscription")
			->with("sub_1", $user->id)->willReturn($fresh);
		$subscriptions = self::createMock(BillingSubscriptionRepository::class);
		$subscriptions->method("getForUser")->willReturn($stale);
		$subscriptions->expects(self::once())->method("save")->with($fresh);
		$users = self::createMock(UserRepository::class);
		$users->expects(self::once())->method("setSubscriptionPlan")->with($user, "developer");

		$result = (new BillingService($gateway, $subscriptions, $users))->refreshIfDue($user);

		self::assertSame($fresh, $result);
	}

	private function service(
		BillingGateway $gateway,
		?BillingSubscriptionRepository $subscriptions = null,
	):BillingService {
		return new BillingService(
			$gateway,
			$subscriptions ?? self::createStub(BillingSubscriptionRepository::class),
			self::createStub(UserRepository::class),
		);
	}

	private function subscription(
		?DateTimeImmutable $nextPaymentAt = new DateTimeImmutable("next month"),
		string $status = "active",
		string $plan = "developer",
		bool $cancelAtPeriodEnd = false,
		?string $pendingPlan = null,
	):BillingSubscription {
		return new BillingSubscription(
			"user-1", "cus_1", "sub_1", $plan, $status,
			1200, new DateTimeImmutable("first day of this month"),
			1200, $nextPaymentAt, "gbp", new DateTimeImmutable(), $cancelAtPeriodEnd,
			$pendingPlan,
		);
	}
}
