<?php
use Gt\DomTemplate\Binder;
use Gt\Dom\HTMLDocument;
use GT\Http\Response;
use Gt\Input\Input;
use HexForm\UI\Flash;
use HexForm\User\User;
use HexForm\Audit\AuditLog;
use HexForm\Billing\BillingService;
use HexForm\Billing\PlanSelector;
use GT\Http\Uri;

function go(
	User $user,
	Binder $binder,
	HTMLDocument $document,
	Input $input,
	Flash $flash,
	BillingService $billing,
	Response $response,
	AuditLog $audit,
): void {
	$checkout = $input->getString("checkout");
	if($checkout === "cancelled") {
		$audit->record($user->id, null, "subscription", $user->id, "checkout", "cancelled");
		$flash->set("Payment was cancelled. Your subscription has not changed.");
		$response->redirect("/app/account/?signup=" . urlencode($input->getString("signup")));
		return;
	}
	if($checkout === "success") {
		try {
			$billing->completeCheckout($input->getString("session_id"), $user);
			$audit->record($user->id, null, "subscription", $user->id, "checkout", "succeeded");
			$flash->set("Payment successful. Your subscription is active.");
		}
		catch(Throwable) {
			$audit->record($user->id, null, "subscription", $user->id, "checkout", "failed");
			$flash->set("We could not verify your payment. Please contact support if you were charged.");
		}
		$response->redirect("/app/account/");
		return;
	}

	$subscription = null;

	try {
		$subscription = $billing->refreshIfDue($user);
	}
	catch(Throwable) {
		$flash->set("Billing information is temporarily unavailable. Please try again later.");
	}

	$binder->bindData($user);
	$message = $flash->consume();

	if($message) {
		$binder->bindKeyValue("subscription-message", $message);
	}
	else {
		$document->querySelector("[data-subscription-message]")?->remove();
	}
	if($subscription) {
		if($subscription->previousPaymentAmount !== null) {
			$binder->bindKeyValue(
				"previous-payment-amount",
				$subscription->formatAmount($subscription->previousPaymentAmount)
			);
			$binder->bindKeyValue(
				"previous-payment-date",
				$subscription->previousPaymentAt?->format("j F Y") ?? "Not available"
			);
		}
		else {
			$document->querySelector("[data-previous-payment]")?->remove();
		}
		$binder->bindKeyValue("latest-payment-amount", $subscription->formatAmount($subscription->latestPaymentAmount));
		$binder->bindKeyValue("latest-payment-date", $subscription->latestPaymentAt?->format("j F Y") ?? "Not available");
		$binder->bindKeyValue("next-payment-amount", $subscription->formatAmount($subscription->nextPaymentAmount));
		$binder->bindKeyValue("next-payment-date", $subscription->nextPaymentAt?->format("j F Y") ?? "Not available");
		if($subscription->cancelAtPeriodEnd) {
			$binder->bindKeyValue(
				"subscription-end-date",
				$subscription->nextPaymentAt?->format("j F Y") ?? "the end of the current period",
			);
			$document->querySelector("[data-next-payment]")?->remove();
		}
		else {
			$document->querySelector("[data-subscription-cancellation]")?->remove();
		}
		if($subscription->pendingPlan) {
			$binder->bindKeyValue("pending-plan", ucfirst($subscription->pendingPlan));
		}
		else {
			$document->querySelector("[data-pending-plan]")?->remove();
		}
	}
	else {
		$document->querySelector("[data-payment-summary]")?->remove();
		$document->querySelector("[data-subscription-cancellation]")?->remove();
		$document->querySelector("[data-pending-plan]")?->remove();
	}

	$signup = $input->getString("signup");
	$selectedPlan = in_array($signup, ["free", "developer", "enterprise"], true)
		? $signup
		: ($subscription?->pendingPlan ?? $user->subscriptionPlan);

	foreach($document->querySelectorAll("[name='subscriptionPlan']") as $option) {
		if($option->getAttribute("value") === $selectedPlan) {
			$option->setAttribute("checked", "checked");
		}
	}
}

function do_select_plan(
	User $user,
	PlanSelector $plans,
	Input $input,
	Response $response,
	Flash $flash,
	Uri $uri,
):void {
	$plan = $input->getString("subscriptionPlan");
	$origin = $uri->getScheme() . "://" . $uri->getAuthority();
	$result = $plans->select($user, $plan, $origin);

	if($result->message !== "") {
		$flash->set($result->message);
	}

	$response->redirect($result->redirectUrl);
}
