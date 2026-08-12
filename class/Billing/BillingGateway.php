<?php
namespace HexForm\Billing;

use HexForm\User\User;

interface BillingGateway {
	public function createCheckout(
		User $user,
		string $plan,
		string $successUrl,
		string $cancelUrl,
		?string $customerId = null,
	):string;

	public function changeSubscription(
		BillingSubscription $subscription,
		string $plan,
	):BillingSubscription;

	public function cancelSubscription(BillingSubscription $subscription):BillingSubscription;

	public function completeCheckout(string $sessionId, User $user):BillingSubscription;

	public function retrieveSubscription(string $subscriptionId, string $userId):BillingSubscription;
}
