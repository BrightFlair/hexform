<?php
namespace HexForm\Billing;

use HexForm\Audit\AuditLog;
use HexForm\User\User;
use Throwable;

readonly class PlanSelector {
	/** @var string[] */
	private const array PAID_PLANS = ["developer", "enterprise"];

	public function __construct(
		private BillingService $billing,
		private AuditLog $audit,
	) {}

	public function select(User $user, string $plan, string $origin):PlanSelection {
		if($plan === "free") {
			return $this->selectFreePlan($user);
		}
		if(in_array($plan, self::PAID_PLANS, true)) {
			return $this->selectPaidPlan($user, $plan, $origin);
		}

		$this->record($user, "select-plan", "rejected", $plan);
		return new PlanSelection(
			"/app/account/",
			"Please choose a valid subscription plan.",
		);
	}

	private function selectFreePlan(User $user):PlanSelection {
		try {
			$cancellationScheduled = $this->billing->selectFreePlan($user);
			$this->record($user, "change-plan", "succeeded", "free");
		}
		catch(Throwable) {
			$this->record($user, "change-plan", "failed", "free");
			return new PlanSelection(
				"/app/account/",
				"Your paid subscription could not be cancelled. Your plan has not changed.",
			);
		}

		return new PlanSelection(
			"/app/account/",
			$cancellationScheduled
				? "Your subscription will change to Free after your current paid period ends."
				: "Your subscription plan is now Free.",
		);
	}

	private function selectPaidPlan(User $user, string $plan, string $origin):PlanSelection {
		try {
			$checkoutUrl = $this->billing->selectPaidPlan(
				$user,
				$plan,
				$origin . "/app/account/?checkout=success&session_id={CHECKOUT_SESSION_ID}",
				$origin . "/app/account/?checkout=cancelled&signup=" . $plan,
			);
			$this->record($user, "change-plan", "succeeded", $plan);
		}
		catch(Throwable) {
			$this->record($user, "change-plan", "failed", $plan);
			return new PlanSelection(
				"/app/account/?signup=" . $plan,
				"Your subscription could not be changed. Your existing plan remains active.",
			);
		}

		if($checkoutUrl) {
			return new PlanSelection($checkoutUrl, "");
		}

		$current = $this->billing->getSubscription($user);
		$message = $current?->pendingPlan === $plan
			? "Your subscription will change to " . ucfirst($plan) . " at the next renewal."
			: "Your subscription plan is now " . ucfirst($plan) . ".";
		return new PlanSelection("/app/account/", $message);
	}

	private function record(User $user, string $action, string $outcome, string $plan):void {
		$this->audit->record(
			$user->id,
			null,
			"subscription",
			$user->id,
			$action,
			$outcome,
			["plan" => $plan],
		);
	}
}
