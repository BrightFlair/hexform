<?php
use Gt\Http\Request;
use HexForm\Billing\StripeWebhookService;

function go(Request $request, StripeWebhookService $webhook):void {
	// TODO: Move to /api and test with the Stripe Webhook API thingy.
	$webhook->handle(
		(string)$request->getBody(),
		$request->getHeaderLine("Stripe-Signature"),
	);
}
