<?php
use Gt\DomTemplate\Binder;
use Gt\Dom\HTMLDocument;
use GT\Http\Response;
use Gt\Input\Input;
use HexForm\UI\Flash;
use HexForm\User\User;
use HexForm\User\UserRepository;

function do_choose_subscription(
	User $user,
	UserRepository $users,
	Input $input,
	Response $response,
	Flash $flash,
):void {
	$plan = $input->getString("subscriptionPlan");
	if($plan === "free") {
		$users->setSubscriptionPlan($user, $plan);
		$response->redirect("/app/");
		return;
	}

	if(in_array($plan, ["developer", "enterprise"], true)) {
		$flash->set("Stripe checkout is not configured yet.");
		$response->redirect("/app/account/?signup=" . $plan);
		return;
	}

	$flash->set("Please choose a valid subscription plan.");
	$response->redirect("/app/account/");
}

function go(
	User $user,
	Binder $binder,
	HTMLDocument $document,
	Input $input,
	Flash $flash,
): void {
	$binder->bindData($user);
	$message = $flash->consume();
	if($message) {
		$binder->bindKeyValue("subscription-message", $message);
	}
	else {
		$document->querySelector("[data-subscription-message]")?->remove();
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
