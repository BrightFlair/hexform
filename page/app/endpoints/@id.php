<?php

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
	Flash $flash,
): void {
	$endpoint = $endpointRepository->getByIdForUser($path->get("id"), $user);
	if(!$endpoint) {
		$response->redirect("/app/endpoints/");
		return;
	}

	$binder->bindData($endpoint);
	$confirmationError = $flash->consume();

	if($confirmationError) {
		$binder->bindKeyValue("confirmation-error-message", $confirmationError);
	}
	else {
		$document->querySelector("[data-confirmation-error]")?->remove();
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

function do_save(
	EndpointRepository $repository,
	DynamicPath $path,
	User $user,
	Response $response,
	Input $input,
): void {
	$endpoint = $repository->getByIdForUser($path->get("id"), $user);
	if(!$endpoint) {
		$response->redirect("/app/endpoints/");
	}

	$retention = $input->getString("retentionMonths");

	$repository->update(
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
			$input->getString("forwarderUrl"),
			$input->getString("ignoredKeys"),
		),
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
