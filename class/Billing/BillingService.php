<?php
namespace HexForm\Billing;

use DateTimeImmutable;
use HexForm\User\User;
use HexForm\User\UserRepository;
use InvalidArgumentException;
use RuntimeException;

readonly class BillingService {
	public function __construct(
		private BillingGateway $gateway,
		private BillingSubscriptionRepository $subscriptions,
		private UserRepository $users,
	) {}

	public function startCheckout(
		User $user,
		string $plan,
		string $successUrl,
		string $cancelUrl,
	):string {
		if(!in_array($plan, ["developer", "enterprise"], true)) {
			throw new InvalidArgumentException("Unknown paid subscription plan: $plan");
		}

		$subscription = $this->subscriptions->getForUser($user->id);
		return $this->gateway->createCheckout(
			$user,
			$plan,
			$successUrl,
			$cancelUrl,
			$subscription?->stripeCustomerId,
		);
	}

	public function getSubscription(User $user):?BillingSubscription {
		return $this->subscriptions->getForUser($user->id);
	}

	public function selectPaidPlan(
		User $user,
		string $plan,
		string $successUrl,
		string $cancelUrl,
	):?string {
		if(!in_array($plan, ["developer", "enterprise"], true)) {
			throw new InvalidArgumentException("Unknown paid subscription plan: $plan");
		}

		$current = $this->subscriptions->getForUser($user->id);
		if(!$current || !$current->isActive()) {
			return $this->startCheckout($user, $plan, $successUrl, $cancelUrl);
		}
		if($current->pendingPlan) {
			if($current->pendingPlan === $plan) {
				return null;
			}
			$current = $this->gateway->clearScheduledChange($current);
			$this->store($user, $current);
		}
		if($current->plan === $plan) {
			if(!$current->cancelAtPeriodEnd) {
				return null;
			}
			$updated = $this->gateway->resumeSubscription($current);
		}
		elseif($this->isDowngrade($current->plan, $plan)) {
			$updated = $this->gateway->scheduleSubscriptionChange($current, $plan);
		}
		else {
			$updated = $this->gateway->changeSubscription($current, $plan);
			if(
				$updated->plan === $plan
				&& $updated->isActive()
				&& $updated->cancelAtPeriodEnd
			) {
				$updated = $this->gateway->resumeSubscription($updated);
			}
		}
		if(
			$updated->plan !== $plan
			&& $updated->pendingPlan !== $plan
		) {
			throw new RuntimeException("Stripe did not complete the subscription change.");
		}
		if(!$updated->isActive() || $updated->cancelAtPeriodEnd) {
			throw new RuntimeException("Stripe did not complete the subscription change.");
		}

		$this->store($user, $updated);
		return null;
	}

	private function isDowngrade(string $currentPlan, string $newPlan):bool {
		$rank = ["developer" => 1, "enterprise" => 2];
		return ($rank[$newPlan] ?? 0) < ($rank[$currentPlan] ?? 0);
	}

	public function selectFreePlan(User $user):bool {
		$current = $this->subscriptions->getForUser($user->id);
		if($current?->pendingPlan) {
			$current = $this->gateway->clearScheduledChange($current);
			$this->store($user, $current);
		}
		if($current && $current->isActive()) {
			$cancelled = $this->gateway->cancelSubscription($current)->withPendingPlan("free");
			if(!$cancelled->cancelAtPeriodEnd) {
				throw new RuntimeException("Stripe did not schedule the subscription cancellation.");
			}
			$this->subscriptions->save($cancelled);
			return true;
		}
		$this->users->setSubscriptionPlan($user, "free");
		return false;
	}

	public function completeCheckout(string $sessionId, User $user):BillingSubscription {
		$subscription = $this->gateway->completeCheckout($sessionId, $user);
		$this->store($user, $subscription);
		return $subscription;
	}

	public function refreshIfDue(User $user):?BillingSubscription {
		$subscription = $this->subscriptions->getForUser($user->id);
		if(!$subscription || !$subscription->needsRefresh(new DateTimeImmutable())) {
			return $subscription;
		}

		$subscription = $this->gateway->retrieveSubscription(
			$subscription->stripeSubscriptionId,
			$user->id,
		);
		$this->store($user, $subscription);
		return $subscription;
	}

	public function refreshByCustomerId(string $customerId):void {
		$current = $this->subscriptions->getByStripeCustomerId($customerId);
		if(!$current) {
			return;
		}

		$subscription = $this->gateway->retrieveSubscription(
			$current->stripeSubscriptionId,
			$current->userId,
		);
		$user = $this->users->getById($current->userId);
		if($user) {
			$this->store($user, $subscription);
		}
	}

	public function completeWebhookCheckout(string $userId, string $subscriptionId):void {
		$user = $this->users->getById($userId);
		if(!$user) {
			return;
		}

		$subscription = $this->gateway->retrieveSubscription($subscriptionId, $userId);
		$this->store($user, $subscription);
	}

	private function store(User $user, BillingSubscription $subscription):void {
		$this->subscriptions->save($subscription);
		$this->users->setSubscriptionPlan(
			$user,
			$subscription->isActive() ? $subscription->plan : null,
		);
	}
}
