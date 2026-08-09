<?php
use Gt\Dom\HTMLDocument;
use Gt\Dom\Element;
use Gt\DomTemplate\Binder;
use Gt\Http\Response;
use Gt\Input\Input;
use Gt\Routing\Path\DynamicPath;
use HexForm\Endpoint\Endpoint;
use HexForm\Endpoint\EndpointRepository;
use HexForm\Audit\AuditLog;
use HexForm\Email\Emailer;
use HexForm\Forwarding\EmailForwarderRepository;
use HexForm\User\User;
use Gt\Ulid\Ulid;
use HexForm\UI\Flash;

function go(
	EndpointRepository $repository,
	DynamicPath $path,
	User $user,
	Response $response,
	Binder $binder,
	HTMLDocument $document,
	EmailForwarderRepository $forwarders,
	Flash $flash,
): void {
	$endpoint = $repository->getByIdForUser($path->get("id"), $user);
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
	$document
		->querySelector("[data-inbox]")
		?->setAttribute("href", "/app/submissions/?endpoint=" . urlencode($endpoint->id));
	if ($endpoint->junkDetection) {
		$document->querySelector("[data-junk-detection]")?->setAttribute("checked", "checked");
	}
	foreach ($document->querySelectorAll("[data-retention] option") as $option) {
		if ($option->getAttribute("value") === $endpoint->getRetentionValue()) {
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
	EndpointRepository $endpoints,
	EmailForwarderRepository $forwarders,
	Emailer $emailer,
	DynamicPath $path,
	User $user,
	Response $response,
	Input $input,
	AuditLog $audit,
):void {
	$endpoint = $endpoints->getByIdForUser($path->get("id"), $user);
	$email = mb_strtolower(trim($input->getString("email")));
	if(!$endpoint || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$audit->record(
			$user->id,
			$endpoint?->id,
			"email-forwarder",
			null,
			"add",
			"rejected",
			["email" => $email, "reason" => $endpoint ? "invalid-email" : "endpoint-not-found"],
		);
		$response->reload();
		return;
	}
	foreach($forwarders->getForEndpointByUser($endpoint, $user) as $forwarder) {
		if($forwarder->email === $email) {
			$audit->record(
				$user->id,
				$endpoint->id,
				"email-forwarder",
				$forwarder->id,
				"add",
				"rejected",
				["email" => $email, "reason" => "duplicate"],
			);
			$response->reload();
			return;
		}
	}
	$code = generateConfirmationCode();
	$forwarderId = (string)new Ulid("FORWARDER");
	$forwarders->create($forwarderId, $endpoint, $email, $code, new DateTimeImmutable());
	$audit->record(
		$user->id,
		$endpoint->id,
		"email-forwarder",
		$forwarderId,
		"add",
		"succeeded",
		["email" => $email],
	);
	$emailSent = $emailer->sendConfirmation($email, $code);
	$audit->record(
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
	EmailForwarderRepository $forwarders,
	User $user,
	Response $response,
	Input $input,
	Flash $flash,
	AuditLog $audit,
):void {
	$forwarder = $forwarders->getByIdForUser($input->getString("forwarderId"), $user);
	$confirmed = $forwarder
		&& $forwarders->confirm($forwarder, trim($input->getString("confirmationCode")));
	$audit->record(
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
	EmailForwarderRepository $forwarders,
	Emailer $emailer,
	User $user,
	Response $response,
	Input $input,
	AuditLog $audit,
):void {
	$forwarder = $forwarders->getByIdForUser($input->getString("forwarderId"), $user);
	$outcome = "rejected";
	$reason = "forwarder-not-found";
	$emailSent = null;
	if($forwarder) {
		$code = generateConfirmationCode();
		if($forwarders->resend($forwarder, $code, new DateTimeImmutable())) {
			$emailSent = $emailer->sendConfirmation($forwarder->email, $code);
			$outcome = "succeeded";
			$reason = "code-regenerated";
		}
		else {
			$reason = $forwarder->isConfirmed() ? "already-confirmed" : "cooldown";
		}
	}
	$audit->record(
		$user->id,
		$forwarder?->endpointId,
		"email-forwarder",
		$forwarder?->id ?? $input->getString("forwarderId"),
		"resend-confirmation",
		$outcome,
		["email" => $forwarder?->email, "reason" => $reason],
	);
	if($emailSent !== null && $forwarder) {
		$audit->record(
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
	EmailForwarderRepository $forwarders,
	User $user,
	Response $response,
	Input $input,
	AuditLog $audit,
):void {
	$requestedId = $input->getString("forwarderId");
	$forwarder = $forwarders->getByIdForUser($requestedId, $user);
	if($forwarder) {
		$forwarders->delete($forwarder);
	}
	$audit->record(
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

function generateConfirmationCode():string {
	return (string)random_int(11111, 99999);
}

function do_save(
	EndpointRepository $repository,
	DynamicPath $path,
	User $user,
	Response $response,
	Input $input,
): void {
	$e = $repository->getByIdForUser($path->get("id"), $user);
	if(!$e) {
		$response->redirect("/app/endpoints/");
		return;
	}
	$retention = $input->getString("retentionMonths");
	$repository->update(
		new Endpoint(
			$e->id,
			$e->userId,
			$e->code,
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
