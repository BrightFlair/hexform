<?php

use DateTimeImmutable;
use Gt\Dom\HTMLDocument;
use Gt\Dom\Element;
use Gt\DomTemplate\Binder;
use Gt\Http\Response;
use Gt\Input\Input;
use Gt\Routing\Path\DynamicPath;
use HexForm\Email\ConfirmationCode;
use HexForm\Endpoint\Endpoint;
use HexForm\Endpoint\EndpointRepository;
use HexForm\Audit\AuditLog;
use HexForm\Email\Emailer;
use HexForm\Email\SmtpConfiguration;
use HexForm\Email\SmtpConfigurationRepository;
use HexForm\Forwarding\EmailForwarderRepository;
use HexForm\User\User;
use Gt\Ulid\Ulid;
use HexForm\UI\Flash;

function go(
	EndpointRepository $endpointRepository,
	DynamicPath $path,
	User $user,
	Response $response,
	Binder $binder,
	HTMLDocument $document,
	EmailForwarderRepository $forwarders,
	SmtpConfigurationRepository $smtpConfigurations,
	Flash $flash,
): void {
	$endpoint = $endpointRepository->getByIdForUser($path->get("id"), $user);
	if(!$endpoint) {
		$response->redirect("/app/endpoints/");
		return;
	}

	$binder->bindData($endpoint);
	$flashMessage = $flash->consume();
	$paidError = $flashMessage === "This feature is only available on paid accounts.";
	$smtpError = str_starts_with($flashMessage ?? "", "SMTP: ");
	$smtpSaved = in_array($flashMessage, ["SMTP saved", "SMTP deleted"], true);

	if($flashMessage && !$paidError && !$smtpError && !$smtpSaved) {
		$binder->bindKeyValue("confirmation-error-message", $flashMessage);
	}
	else {
		$document->querySelector("[data-confirmation-error]")?->remove();
	}
	if(!$paidError) {
		$document->querySelector("[data-paid-error]")?->removeAttribute("open");
	}
	if($smtpError) {
		$binder->bindKeyValue("smtp-error-message", substr($flashMessage, 6));
	}
	else {
		$document->querySelector("[data-smtp-error]")?->removeAttribute("open");
	}
	if($smtpError || $smtpSaved) {
		$document->querySelector('[data-forwarder-card="email"]')?->setAttribute("open", "open");
		$document->querySelector("[data-smtp-settings]")?->setAttribute("open", "open");
	}

	$enabledForwarders = $endpoint->getEnabledForwarderList();
	foreach($document->querySelectorAll("[data-forwarder-choice]") as $choice) {
		$name = $choice->getAttribute("value");
		if(in_array($name, $enabledForwarders, true)) {
			$choice->setAttribute("checked", "checked");
		}
	}
	foreach($document->querySelectorAll("[data-forwarder-card]") as $card) {
		if(!in_array($card->getAttribute("data-forwarder-card"), $enabledForwarders, true)) {
			$card->setAttribute("hidden", "hidden");
		}
	}
	$document->querySelector("[data-inbox]")
		?->setAttribute("href", "/app/submissions/?endpoint=" . urlencode($endpoint->id));

	if($endpoint->junkDetection) {
		$document->querySelector("[data-junk-detection]")?->setAttribute("checked", "checked");
	}

	foreach($document->querySelectorAll("[data-retention] option") as $option) {
		if($option->getAttribute("value") === $endpoint->getRetentionValue()) {
			$option->setAttribute("selected", "selected");
		}
	}

	$smtp = $smtpConfigurations->getForEndpointByUser($endpoint, $user);
	$binder->bindKeyValue("smtp-host", $smtp?->host ?? "");
	$binder->bindKeyValue("smtp-port", (string)($smtp?->port ?? 587));
	$binder->bindKeyValue("smtp-username", $smtp?->username ?? "");
	$binder->bindKeyValue("smtp-from-address", $smtp?->fromAddress ?? "");
	$binder->bindKeyValue("smtp-from-name", $smtp?->fromName ?? "");
	foreach($document->querySelectorAll("[data-smtp-security] option") as $option) {
		if($option->getAttribute("value") === ($smtp?->security ?? SmtpConfiguration::SECURITY_STARTTLS)) {
			$option->setAttribute("selected", "selected");
		}
	}
	if(!$smtp) {
		$document->querySelector("[data-smtp-custom]")?->remove();
	}
	else {
		$document->querySelector("[data-smtp-default]")?->remove();
	}

	$list = $forwarders->getForEndpointByUser($endpoint, $user);
	if($list) {
		$document->querySelector("[data-no-email-forwarders]")?->remove();
	}

	$binder->bindListCallback(
		array_map(
			fn($forwarder):array => [
				...get_object_vars($forwarder),
				"status" => $forwarder->getStatus(),
				"canResend" => $forwarder->canResend(),
			],
			$list,
		),
		function(Element $row, array $data):array {
			$isConfirmed = $data["confirmedAt"] !== null;
			$row->classList->add($isConfirmed ? "confirmed" : "pending");
			if($isConfirmed) {
				$row->querySelector("[data-confirm-forwarder]")?->remove();
				$row->querySelector("[data-resend-forwarder]")?->remove();
			}
			elseif(!$data["canResend"]) {
				$row->querySelector("[data-resend-forwarder]")?->remove();
			}

			return $data;
		},
		$document->querySelector("[data-email-forwarders]"),
	);
}

function do_save_smtp(
	EndpointRepository $endpoints,
	SmtpConfigurationRepository $smtpConfigurations,
	DynamicPath $path,
	User $user,
	Response $response,
	Input $input,
	Flash $flash,
):void {
	$endpoint = $endpoints->getByIdForUser($path->get("id"), $user);
	if(!$endpoint) {
		$response->redirect("/app/endpoints/");
		return;
	}

	$host = trim($input->getString("smtpHost") ?? "");
	$port = $input->getInt("smtpPort");
	$security = $input->getString("smtpSecurity") ?? "";
	$fromAddress = trim($input->getString("smtpFromAddress") ?? "");
	$validHost = filter_var($host, FILTER_VALIDATE_IP) !== false
		|| filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
	if(
		!$validHost
		|| mb_strlen($host) > 253
		|| !$port
		|| $port > 65535
		|| !in_array($security, SmtpConfiguration::securityList(), true)
		|| !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)
	) {
		$flash->set("SMTP: Check the host, port, security, and from address.");
		$response->reload();
		return;
	}

	$existing = $smtpConfigurations->getForEndpointByUser($endpoint, $user);
	$password = $input->getString("smtpPassword") ?? "";
	$smtpConfigurations->save(new SmtpConfiguration(
		$endpoint->id,
		$host,
		$port,
		smtpNullIfEmpty($input->getString("smtpUsername")),
		$password === "" ? $existing?->password : $password,
		$security,
		$fromAddress,
		smtpNullIfEmpty($input->getString("smtpFromName")),
	));
	$flash->set("SMTP saved");
	$response->reload();
}

function do_delete_smtp(
	EndpointRepository $endpoints,
	SmtpConfigurationRepository $smtpConfigurations,
	DynamicPath $path,
	User $user,
	Response $response,
	Flash $flash,
):void {
	$endpoint = $endpoints->getByIdForUser($path->get("id"), $user);
	if(!$endpoint) {
		$response->redirect("/app/endpoints/");
		return;
	}

	$smtpConfigurations->deleteForEndpointByUser($endpoint, $user);
	$flash->set("SMTP deleted");
	$response->reload();
}

function smtpNullIfEmpty(?string $value):?string {
	$value = trim($value ?? "");
	return $value === "" ? null : $value;
}

function do_save_forwarders(
	EndpointRepository $repository,
	DynamicPath $path,
	User $user,
	Response $response,
	Input $input,
	Flash $flash,
):void {
	$endpoint = $repository->getByIdForUser($path->get("id"), $user);
	if(!$endpoint) {
		$response->redirect("/app/endpoints/");
		return;
	}

	$available = ["email", "webhook", "zapier", "google-sheets", "trello", "github", "brevo", "slack"];
	$free = ["email", "webhook"];
	$requested = array_values(array_intersect(
		$available,
		$input->getMultipleString("forwarders"),
	));
	$hasPaidPlan = in_array($user->subscriptionPlan, ["developer", "enterprise"], true);
	if(!$hasPaidPlan && array_diff($requested, $free)) {
		$flash->set("This feature is only available on paid accounts.");
		$response->reload();
		return;
	}

	$repository->updateEnabledForwarders($endpoint, $requested);
	$response->reload();
}

function do_add_email_forwarder(
	EndpointRepository $endpointRepository,
	EmailForwarderRepository $emailForwarderRepository,
	Emailer $emailer,
	DynamicPath $dynamicPath,
	User $user,
	Response $response,
	Input $input,
	AuditLog $auditLog,
):void {
	$endpoint = $endpointRepository->getByIdForUser($dynamicPath->get("id"), $user);
	$email = mb_strtolower(trim($input->getString("email")));

	if(!$endpoint || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$auditLog->record(
			$user->id,
			$endpoint?->id,
			"email-forwarder",
			null,
			"add",
			"rejected",
			["email" => $email, "reason" => $endpoint ? "invalid-email" : "endpoint-not-found"],
		);
		$response->reload();
	}

	foreach($emailForwarderRepository->getForEndpointByUser($endpoint, $user) as $forwarder) {
		if($forwarder->email === $email) {
			$auditLog->record(
				$user->id,
				$endpoint->id,
				"email-forwarder",
				$forwarder->id,
				"add",
				"rejected",
				["email" => $email, "reason" => "duplicate"],
			);

			$response->reload();
		}
	}

	$code = new ConfirmationCode();
	$forwarderId = new Ulid("FORWARDER");
	$now = new DateTimeImmutable();

	$isAccountEmail = $email === mb_strtolower(trim($user->email));
	$emailForwarderRepository->create(
		$forwarderId,
		$endpoint,
		$email,
		$code,
		$now,
		$isAccountEmail ? $now : null,
	);

	$auditLog->record(
		$user->id,
		$endpoint->id,
		"email-forwarder",
		$forwarderId,
		"add",
		"succeeded",
		["email" => $email],
	);

	if($isAccountEmail) {
		$auditLog->record(
			$user->id,
			$endpoint->id,
			"email-forwarder",
			$forwarderId,
			"confirm",
			"succeeded",
			["email" => $email, "reason" => "account-email"],
		);
		$response->reload();
	}

	$emailSent = $emailer->sendConfirmation($email, $code);
	$auditLog->record(
		$user->id,
		$endpoint->id,
		"email-forwarder",
		$forwarderId,
		"send-confirmation",
		$emailSent ? "succeeded" : "failed",
		["email" => $email],
	);
	$response->reload();
}

function do_confirm_email_forwarder(
	EmailForwarderRepository $emailForwarderRepository,
	User $user,
	Response $response,
	Input $input,
	Flash $flash,
	AuditLog $auditLog,
):void {
	$forwarder = $emailForwarderRepository->getByIdForUser($input->getString("forwarderId"), $user);
	$confirmed = $forwarder
		&& $emailForwarderRepository->confirm($forwarder, trim($input->getString("confirmationCode")));

	$auditLog->record(
		$user->id,
		$forwarder?->endpointId,
		"email-forwarder",
		$forwarder?->id ?? $input->getString("forwarderId"),
		"confirm",
		$confirmed ? "succeeded" : "failed",
		[
			"email" => $forwarder?->email,
			"reason" => $confirmed
				? "confirmed"
				: ($forwarder ? "code-mismatch" : "forwarder-not-found"),
		],
	);
	if(!$confirmed) {
		$flash->set("The confirmation code is incorrect. Please try again.");
	}
	$response->reload();
}

function do_resend_email_forwarder(
	EmailForwarderRepository $emailForwarderRepository,
	Emailer $emailer,
	User $user,
	Response $response,
	Input $input,
	AuditLog $auditLog,
):void {
	$forwarder = $emailForwarderRepository->getByIdForUser($input->getString("forwarderId"), $user);
	$outcome = "rejected";
	$reason = "forwarder-not-found";
	$emailSent = null;

	if($forwarder) {
		$code = new ConfirmationCode();
		if($emailForwarderRepository->resend($forwarder, $code, new DateTimeImmutable())) {
			$emailSent = $emailer->sendConfirmation($forwarder->email, $code);
			$outcome = "succeeded";
			$reason = "code-regenerated";
		}
		else {
			$reason = $forwarder->isConfirmed() ? "already-confirmed" : "cooldown";
		}
	}

	$auditLog->record(
		$user->id,
		$forwarder?->endpointId,
		"email-forwarder",
		$forwarder?->id ?? $input->getString("forwarderId"),
		"resend-confirmation",
		$outcome,
		["email" => $forwarder?->email, "reason" => $reason],
	);

	if($emailSent !== null && $forwarder) {
		$auditLog->record(
			$user->id,
			$forwarder->endpointId,
			"email-forwarder",
			$forwarder->id,
			"send-confirmation",
			$emailSent ? "succeeded" : "failed",
			["email" => $forwarder->email, "reason" => "resend"],
		);
	}

	$response->reload();
}

function do_delete_email_forwarder(
	EmailForwarderRepository $emailForwarderRepository,
	User $user,
	Response $response,
	Input $input,
	AuditLog $auditLog,
):void {
	$requestedId = $input->getString("forwarderId");
	$forwarder = $emailForwarderRepository->getByIdForUser($requestedId, $user);

	if($forwarder) {
		$emailForwarderRepository->delete($forwarder);
	}

	$auditLog->record(
		$user->id,
		$forwarder?->endpointId,
		"email-forwarder",
		$forwarder?->id ?? $requestedId,
		"delete",
		$forwarder ? "succeeded" : "rejected",
		$forwarder ? ["email" => $forwarder->email] : ["reason" => "forwarder-not-found"],
	);
	$response->reload();
}

function do_save_general(
	EndpointRepository $repository,
	DynamicPath $path,
	User $user,
	Response $response,
	Input $input,
): void {
	$endpoint = $repository->getByIdForUser($path->get("id"), $user);
	if(!$endpoint) {
		$response->redirect("/app/endpoints/");
		return;
	}

	$retention = $input->getString("retentionMonths");

	$repository->updateGeneral(
		new Endpoint(
			$endpoint->id,
			$endpoint->userId,
			$endpoint->code,
			$input->getString("title"),
			$input->getString("clientHost"),
			$input->getString("confirmationUrl"),
			$input->contains("junkDetection"),
			$input->getString("junkFieldName"),
			$input->getString("mainField"),
			$input->getString("submitterIdentityField"),
			$retention === "forever" ? null : (int)$retention,
			$input->getInt("maximumSubmissionsPerMonth") ?? 50,
			$endpoint->forwarderUrl,
			$input->getString("ignoredKeys"),
		),
	);
	$response->reload();
}

function do_save_webhook(
	EndpointRepository $repository,
	DynamicPath $path,
	User $user,
	Response $response,
	Input $input,
):void {
	$endpoint = $repository->getByIdForUser($path->get("id"), $user);
	if(!$endpoint) {
		$response->redirect("/app/endpoints/");
		return;
	}

	$forwarderUrl = trim($input->getString("forwarderUrl"));
	$repository->updateForwarderUrl(
		$endpoint,
		$forwarderUrl === "" ? null : $forwarderUrl,
	);
	$response->reload();
}

function do_delete(
	EndpointRepository $repository,
	DynamicPath $path,
	User $user,
	Response $response,
): void {
	$repository->deleteByIdForUser($path->get("id"), $user);
	$response->redirect("/app/endpoints/");
}
