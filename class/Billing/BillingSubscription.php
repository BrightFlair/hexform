<?php
namespace HexForm\Billing;

use DateTimeInterface;
use DateTimeImmutable;

readonly class BillingSubscription {
	/** @SuppressWarnings("PHPMD.ExcessiveParameterList") */
	public function __construct(
		public string $userId,
		public string $stripeCustomerId,
		public string $stripeSubscriptionId,
		public string $plan,
		public string $status,
		public ?int $latestPaymentAmount,
		public ?DateTimeInterface $latestPaymentAt,
		public ?int $nextPaymentAmount,
		public ?DateTimeInterface $nextPaymentAt,
		public string $currency,
		public DateTimeInterface $checkedAt,
		public bool $cancelAtPeriodEnd = false,
	) {}

	public function isActive():bool {
		return in_array($this->status, ["active", "trialing"], true);
	}

	public function needsRefresh(DateTimeInterface $now):bool {
		$refreshAt = $this->nextPaymentAt
			?? DateTimeImmutable::createFromInterface($this->checkedAt)->modify("+1 month");
		return $now >= $refreshAt;
	}

	public function formatAmount(?int $amount):string {
		if($amount === null) {
			return "Not available";
		}

		return strtoupper($this->currency) . " " . number_format($amount / 100, 2);
	}
}
