<?php
namespace HexForm\Billing;

use DateTimeImmutable;
use HexForm\User\User;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;
use Stripe\Price;
use Stripe\StripeClient;
use Stripe\Subscription;
use Stripe\SubscriptionItem;

class StripeBillingGateway implements BillingGateway {
	/** @param array<string, string> $priceLookupKeys */
	public function __construct(
		private StripeClient $stripe,
		private string $productId,
		private array $priceLookupKeys,
		private ?LatestPaidInvoiceFinder $latestPaidInvoiceFinder = null,
	) {}

	public function createCheckout(
		User $user,
		string $plan,
		string $successUrl,
		string $cancelUrl,
		?string $customerId = null,
	):string {
		$price = $this->findPrice($plan);

		$params = [
			"mode" => "subscription",
			"client_reference_id" => $user->id,
			"metadata" => ["userId" => $user->id, "plan" => $plan],
			"subscription_data" => [
				"metadata" => ["userId" => $user->id, "plan" => $plan],
			],
			"line_items" => [["price" => $price->id, "quantity" => 1]],
			"success_url" => $successUrl,
			"cancel_url" => $cancelUrl,
		];
		if($customerId) {
			$params["customer"] = $customerId;
		}
		else {
			$params["customer_email"] = $user->email;
		}
		$session = $this->stripe->checkout->sessions->create($params);
		if(!$session->url) {
			throw new RuntimeException("Stripe did not provide a checkout URL.");
		}

		return $session->url;
	}

	public function changeSubscription(
		BillingSubscription $subscription,
		string $plan,
	):BillingSubscription {
		$price = $this->findPrice($plan);
		$stripeSubscription = $this->stripe->subscriptions->retrieve(
			$subscription->stripeSubscriptionId,
		);
		$item = $stripeSubscription->items->data[0] ?? null;
		if(!$item) {
			throw new RuntimeException("Stripe subscription has no price item.");
		}

		$stripeSubscription = $this->stripe->subscriptions->update(
			$stripeSubscription->id,
			[
				"items" => [["id" => $item->id, "price" => $price->id, "quantity" => 1]],
				"payment_behavior" => "pending_if_incomplete",
				"proration_behavior" => "always_invoice",
			],
		);

		return $this->createSnapshot(
			$stripeSubscription,
			$subscription->userId,
			$subscription,
		);
	}

	public function scheduleSubscriptionChange(
		BillingSubscription $subscription,
		string $plan,
	):BillingSubscription {
		$price = $this->findPrice($plan);
		$stripeSubscription = $this->stripe->subscriptions->retrieve(
			$subscription->stripeSubscriptionId,
		);
		$item = $stripeSubscription->items->data[0] ?? null;
		if(!$item) {
			throw new RuntimeException("Stripe subscription has no price item.");
		}
		$schedule = $this->stripe->subscriptionSchedules->create([
			"from_subscription" => $stripeSubscription->id,
		]);
		$currentPhase = $schedule->current_phase;
		if(!$currentPhase) {
			throw new RuntimeException("Stripe did not create an active subscription schedule.");
		}
		$this->stripe->subscriptionSchedules->update($schedule->id, [
			"end_behavior" => "release",
			"phases" => [
				[
					"start_date" => $currentPhase->start_date,
					"end_date" => $currentPhase->end_date,
					"items" => [["price" => $this->getId($item->price), "quantity" => 1]],
				],
				[
					"start_date" => $currentPhase->end_date,
					"duration" => ["interval" => "month", "interval_count" => 1],
					"items" => [["price" => $price->id, "quantity" => 1]],
				],
			],
		]);

		return new BillingSubscription(
			$subscription->userId,
			$subscription->stripeCustomerId,
			$subscription->stripeSubscriptionId,
			$subscription->plan,
			$subscription->status,
			$subscription->latestPaymentAmount,
			$subscription->latestPaymentAt,
			$price->unit_amount,
			$subscription->nextPaymentAt,
			$price->currency,
			new DateTimeImmutable(),
			false,
			$plan,
			$subscription->previousPaymentAmount,
			$subscription->previousPaymentAt,
		);
	}

	public function cancelSubscription(BillingSubscription $subscription):BillingSubscription {
		$stripeSubscription = $this->stripe->subscriptions->update(
			$subscription->stripeSubscriptionId,
			["cancel_at_period_end" => true],
		);

		return $this->createSnapshot(
			$stripeSubscription,
			$subscription->userId,
			$subscription,
		);
	}

	public function resumeSubscription(BillingSubscription $subscription):BillingSubscription {
		$stripeSubscription = $this->stripe->subscriptions->update(
			$subscription->stripeSubscriptionId,
			["cancel_at_period_end" => false],
		);

		return $this->createSnapshot(
			$stripeSubscription,
			$subscription->userId,
			$subscription,
		);
	}

	public function clearScheduledChange(BillingSubscription $subscription):BillingSubscription {
		$stripeSubscription = $this->stripe->subscriptions->retrieve(
			$subscription->stripeSubscriptionId,
			["expand" => ["schedule"]],
		);
		if($stripeSubscription->schedule) {
			$this->stripe->subscriptionSchedules->release(
				$this->getId($stripeSubscription->schedule),
			);
		}

		return $this->createSnapshot(
			$stripeSubscription,
			$subscription->userId,
			$subscription,
		);
	}

	public function completeCheckout(string $sessionId, User $user):BillingSubscription {
		/** @var Session $session */
		$session = $this->stripe->checkout->sessions->retrieve($sessionId, [
			"expand" => ["subscription"],
		]);
		if($session->client_reference_id !== $user->id) {
			throw new RuntimeException("Stripe checkout does not belong to this account.");
		}
		if(!$session->subscription instanceof Subscription) {
			throw new RuntimeException("Stripe checkout has no subscription.");
		}

		return $this->createSnapshot($session->subscription, $user->id);
	}

	public function retrieveSubscription(string $subscriptionId, string $userId):BillingSubscription {
		$subscription = $this->stripe->subscriptions->retrieve($subscriptionId);
		return $this->createSnapshot($subscription, $userId);
	}

	private function createSnapshot(
		Subscription $subscription,
		string $userId,
		?BillingSubscription $fallback = null,
	):BillingSubscription {
		$item = $subscription->items->data[0] ?? null;
		if(!$item) {
			throw new RuntimeException("Stripe subscription has no price item.");
		}
		$lookupKey = $item->price->lookup_key;
		$plan = array_search($lookupKey, $this->priceLookupKeys, true);
		if(!is_string($plan)) {
			throw new RuntimeException("Stripe subscription uses an unknown price.");
		}

		$paymentInvoices = $this->getPaymentInvoices($subscription, $fallback !== null);
		$latestInvoice = $paymentInvoices[0] ?? null;
		$previousInvoice = $paymentInvoices[1] ?? null;
		$cancelAtPeriodEnd = (bool)$subscription->cancel_at_period_end;
		$nextInvoice = $cancelAtPeriodEnd
			? null
			: $this->getNextInvoice($subscription, $fallback !== null);

		return new BillingSubscription(
			$userId,
			$this->getId($subscription->customer),
			$subscription->id,
			$plan,
			$subscription->status,
			$this->getLatestPaymentAmount($latestInvoice, $fallback),
			$this->dateFromTimestamp($latestInvoice?->status_transitions?->paid_at)
				?? $fallback?->latestPaymentAt,
			$cancelAtPeriodEnd ? null : $this->getNextPaymentAmount($nextInvoice, $fallback),
			$this->dateFromTimestamp($item->current_period_end),
			$this->getCurrency($nextInvoice, $latestInvoice, $item, $fallback),
			new DateTimeImmutable(),
			$cancelAtPeriodEnd,
			null,
			$this->getPreviousPaymentAmount($previousInvoice, $fallback),
			$this->getPreviousPaymentDate($previousInvoice, $fallback),
		);
	}

	private function findPrice(string $plan):Price {
		$lookupKey = $this->priceLookupKeys[$plan] ?? null;
		if(!$lookupKey) {
			throw new RuntimeException("Stripe price is not configured for '$plan'.");
		}

		$prices = $this->stripe->prices->all([
			"active" => true,
			"limit" => 1,
			"lookup_keys" => [$lookupKey],
			"product" => $this->productId,
		]);
		$price = $prices->first();
		if(!$price) {
			throw new RuntimeException("No active Stripe price found for '$plan'.");
		}

		return $price;
	}

	/** @return list<Invoice> */
	private function getPaymentInvoices(
		Subscription $subscription,
		bool $allowFailure,
	):array {
		try {
			$paidInvoices = $this->stripe->invoices->all([
				"subscription" => $subscription->id,
				"status" => "paid",
				"limit" => 100,
			]);
			$finder = $this->latestPaidInvoiceFinder ?? new LatestPaidInvoiceFinder();
			return $finder->findMany($paidInvoices->autoPagingIterator(), 2);
		}
		catch(ApiErrorException $exception) {
			if(!$allowFailure) {
				throw $exception;
			}
			return [];
		}
	}

	private function getNextInvoice(
		Subscription $subscription,
		bool $allowFailure,
	):?Invoice {
		if(!in_array($subscription->status, ["active", "trialing"], true)) {
			return null;
		}

		try {
			return $this->stripe->invoices->createPreview([
				"subscription" => $subscription->id,
			]);
		}
		catch(ApiErrorException $exception) {
			if(!$allowFailure) {
				throw $exception;
			}
			return null;
		}
	}

	private function getCurrency(
		?Invoice $nextInvoice,
		?Invoice $latestInvoice,
		SubscriptionItem $item,
		?BillingSubscription $fallback,
	):string {
		if($nextInvoice) {
			return $nextInvoice->currency;
		}
		if($latestInvoice) {
			return $latestInvoice->currency;
		}
		if($fallback) {
			return $fallback->currency;
		}

		return $item->price->currency;
	}

	private function getLatestPaymentAmount(
		?Invoice $latestInvoice,
		?BillingSubscription $fallback,
	):?int {
		return $latestInvoice
			? $latestInvoice->amount_paid
			: $fallback?->latestPaymentAmount;
	}

	private function getPreviousPaymentAmount(
		?Invoice $invoice,
		?BillingSubscription $fallback,
	):?int {
		return $invoice
			? $invoice->amount_paid
			: $fallback?->previousPaymentAmount;
	}

	private function getPreviousPaymentDate(
		?Invoice $invoice,
		?BillingSubscription $fallback,
	):?\DateTimeInterface {
		return $invoice
			? $this->dateFromTimestamp($invoice->status_transitions->paid_at)
			: $fallback?->previousPaymentAt;
	}

	private function getNextPaymentAmount(
		?Invoice $nextInvoice,
		?BillingSubscription $fallback,
	):?int {
		return $nextInvoice
			? $nextInvoice->amount_due
			: $fallback?->nextPaymentAmount;
	}

	private function getId(string|object $value):string {
		return is_string($value) ? $value : $value->id;
	}

	private function dateFromTimestamp(?int $timestamp):?DateTimeImmutable {
		return $timestamp ? (new DateTimeImmutable())->setTimestamp($timestamp) : null;
	}
}
