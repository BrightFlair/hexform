<?php
use Throwable;
use Authwave\Authenticator;
use GT\Http\Response;
use GT\Http\Uri;
use Gt\Input\Input;
use HexForm\UI\Flash;
use HexForm\User\User;
use HexForm\User\UserRepository;
use HexForm\Billing\BillingService;

function go_before(
	?User $user,
	Authenticator $authenticator,
	Input $input,
	Response $response,
	Uri $uri,
	UserRepository $users,
	Flash $flash,
	BillingService $billing,
): void {
	if (!$user) {
		$authenticator->login();
		return;
	}

	if(in_array($user->subscriptionPlan, ["developer", "enterprise"], true)) {
		try {
			$billing->refreshIfDue($user);
		}
		catch(Throwable) {
			$flash->set("Billing information is temporarily unavailable. Please try again later.");
		}
	}

	$signup = $input->getString("signup");
	if(!$user->subscriptionPlan && $signup === "free") {
		$users->setSubscriptionPlan($user, "free");
		$response->redirect("/app/");
		return;
	}

	if(
		!$user->subscriptionPlan
		&& $uri->getPath() !== "/app/account/"
		&& in_array($signup, ["developer", "enterprise"], true)
	) {
		$response->redirect("/app/account/?signup=" . $signup);
	}

	if(!$user->subscriptionPlan && $uri->getPath() !== "/app/account/") {
		$flash->set(
			"You currently do not have an active subscription. "
			. "Please choose a plan to continue.",
		);

		$response->redirect("/app/account/");
	}
}
