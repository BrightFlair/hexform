<?php
namespace HexForm\Billing;

use GT\Database\Query\QueryCollection;
use GT\Database\Result\Row;

readonly class BillingSubscriptionRepository {
	public function __construct(private QueryCollection $db) {}

	public function getForUser(string $userId):?BillingSubscription {
		return $this->rowToSubscription($this->db->fetch("getForUser", $userId));
	}

	public function getByStripeCustomerId(string $customerId):?BillingSubscription {
		return $this->rowToSubscription($this->db->fetch("getByStripeCustomerId", $customerId));
	}

	public function save(BillingSubscription $subscription):void {
		$this->db->insert("save", [
			"userId" => $subscription->userId,
			"stripeCustomerId" => $subscription->stripeCustomerId,
			"stripeSubscriptionId" => $subscription->stripeSubscriptionId,
			"plan" => $subscription->plan,
			"status" => $subscription->status,
			"latestPaymentAmount" => $subscription->latestPaymentAmount,
			"latestPaymentAt" => $subscription->latestPaymentAt?->format("Y-m-d H:i:s"),
			"nextPaymentAmount" => $subscription->nextPaymentAmount,
			"nextPaymentAt" => $subscription->nextPaymentAt?->format("Y-m-d H:i:s"),
			"currency" => $subscription->currency,
			"checkedAt" => $subscription->checkedAt->format("Y-m-d H:i:s"),
			"cancelAtPeriodEnd" => $subscription->cancelAtPeriodEnd ? 1 : 0,
			"pendingPlan" => $subscription->pendingPlan,
			"previousPaymentAmount" => $subscription->previousPaymentAmount,
			"previousPaymentAt" => $subscription->previousPaymentAt?->format("Y-m-d H:i:s"),
		]);
	}

	private function rowToSubscription(?Row $row):?BillingSubscription {
		if(!$row) {
			return null;
		}

		return new BillingSubscription(
			$row->getString("userId"),
			$row->getString("stripeCustomerId"),
			$row->getString("stripeSubscriptionId"),
			$row->getString("plan"),
			$row->getString("status"),
			$row->getInt("latestPaymentAmount"),
			$row->getDateTime("latestPaymentAt"),
			$row->getInt("nextPaymentAmount"),
			$row->getDateTime("nextPaymentAt"),
			$row->getString("currency"),
			$row->getDateTime("checkedAt"),
			$row->getBool("cancelAtPeriodEnd"),
			$row->getString("pendingPlan") ?: null,
			$row->getInt("previousPaymentAmount"),
			$row->getDateTime("previousPaymentAt"),
		);
	}
}
