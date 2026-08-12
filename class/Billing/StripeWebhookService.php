<?php
namespace HexForm\Billing;

use Stripe\Event;
use Stripe\Webhook;

class StripeWebhookService {
	public function __construct(
		private BillingService $billing,
		private string $signingSecret,
	) {}

	/** @SuppressWarnings("PHPMD.StaticAccess") */
	public function handle(string $payload, string $signature):void {
		$event = Webhook::constructEvent($payload, $signature, $this->signingSecret);
		if($event->type === "checkout.session.completed") {
			$this->completeCheckout($event);
			return;
		}
		if(!$this->affectsSubscription($event)) {
			return;
		}

		$customer = $event->data->object->customer ?? null;
		$customerId = is_string($customer) ? $customer : $customer?->id;
		if($customerId) {
			$this->billing->refreshByCustomerId($customerId);
		}
	}

	private function completeCheckout(Event $event):void {
		$session = $event->data->object;
		$userId = $session->client_reference_id ?? null;
		$subscription = $session->subscription ?? null;
		$subscriptionId = is_string($subscription) ? $subscription : $subscription?->id;
		if($userId && $subscriptionId) {
			$this->billing->completeWebhookCheckout($userId, $subscriptionId);
		}
	}

	private function affectsSubscription(Event $event):bool {
		return str_starts_with($event->type, "invoice.")
			|| str_starts_with($event->type, "customer.subscription.");
	}
}
