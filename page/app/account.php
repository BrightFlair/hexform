<?php
use Gt\DomTemplate\Binder;
use Gt\Dom\HTMLDocument;
use GT\Http\Response;
use Gt\Input\Input;
use HexForm\UI\Flash;
use HexForm\User\User;
use HexForm\Audit\AuditLog;
use HexForm\Billing\BillingService;
use GT\Http\Uri;
use Throwable;

function do_select_plan(
	User $user,
	BillingService $billing,
	Input $input,
	Response $response,
	Flash $flash,
	Uri $uri,
	AuditLog $audit,
):void {
	$plan = $input->getString("subscriptionPlan");
	if($plan === "free") {
		try {
			$billing->selectFreePlan($user);
			$audit->record($user->id, null, "subscription", $user->id, "change-plan", "succeeded", ["plan" => $plan]);
			$flash->set("Your subscription will change to Free after your current paid period ends.");
			$response->redirect("/app/account/");
		}
		catch(Throwable) {
			$audit->record($user->id, null, "subscription", $user->id, "change-plan", "failed", ["plan" => $plan]);
			$flash->set("Your paid subscription could not be cancelled. Your plan has not changed.");
			$response->redirect("/app/account/");
		}
		return;
	}

	if(in_array($plan, ["developer", "enterprise"], true)) {
		$origin = $uri->getScheme() . "://" . $uri->getAuthority();
		try {
			$checkoutUrl = $billing->selectPaidPlan(
				$user,
				$plan,
				$origin . "/app/account/?checkout=success&session_id={CHECKOUT_SESSION_ID}",
				$origin . "/app/account/?checkout=cancelled&signup=" . $plan,
			);
			$audit->record($user->id, null, "subscription", $user->id, "change-plan", "succeeded", ["plan" => $plan]);
			if($checkoutUrl) {
				$response->redirect($checkoutUrl);
			}
			else {
				$flash->set("Your subscription plan is now " . ucfirst($plan) . ".");
				$response->redirect("/app/account/");
			}
		}
		catch(Throwable) {
			$audit->record($user->id, null, "subscription", $user->id, "change-plan", "failed", ["plan" => $plan]);
			$flash->set("Your subscription could not be changed. Your existing plan remains active.");
			$response->redirect("/app/account/?signup=" . $plan);
		}
		return;
	}

	$audit->record($user->id, null, "subscription", $user->id, "select-plan", "rejected", ["plan" => $plan]);
	$flash->set("Please choose a valid subscription plan.");
	$response->redirect("/app/account/");
}

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
	}
	else {
		$document->querySelector("[data-payment-summary]")?->remove();
		$document->querySelector("[data-subscription-cancellation]")?->remove();
	}

	$signup = $input->getString("signup");
	$selectedPlan = in_array($signup, ["free", "developer", "enterprise"], true)
		? $signup
		: ($user->subscriptionPlan ?: "free");
	foreach($document->querySelectorAll("[name='subscriptionPlan']") as $option) {
		if($option->getAttribute("value") === $selectedPlan) {
			$option->setAttribute("checked", "checked");
		}
	}
}
