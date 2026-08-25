<?php
use Gt\Http\Response;
use GT\Http\ResponseStatusException\ClientError\HttpNotFound;
use Gt\Http\ServerInfo;
use Gt\Input\Input;
use Gt\Routing\Path\DynamicPath;
use Gt\Ulid\Ulid;
use HexForm\Endpoint\EndpointRepository;
use HexForm\Submission\SubmissionRepository;
use HexForm\Email\Emailer;
use HexForm\Email\SmtpConfigurationRepository;
use HexForm\Forwarding\EmailForwarderRepository;
use HexForm\Audit\AuditLog;
use HexForm\Forwarding\SubmissionForwardingLogRepository;
use HexForm\Forwarding\WebhookForwarder;

function go(
	DynamicPath $path,
	EndpointRepository $endpoints,
	SubmissionRepository $submissions,
	Input $input,
	Response $response,
	ServerInfo $serverInfo,
	EmailForwarderRepository $forwarders,
	Emailer $emailer,
	SmtpConfigurationRepository $smtpConfigurations,
	AuditLog $audit,
	SubmissionForwardingLogRepository $forwardingLog,
	WebhookForwarder $webhookForwarder,
): void {
	if($serverInfo->getRequestMethod() !== "POST") {
		$response->redirect("/");
	}

	$endpoint = $endpoints->getByCode($path->get("code"));
	if(!$endpoint) {
		throw new HttpNotFound();
	}

	$data = [];
	$ignoredKeys = $endpoint->getIgnoredKeyList();

	foreach($input->getAll(Input::DATA_BODY)->asArray() as $key => $value) {
		if(in_array($key, $ignoredKeys, true)) {
			continue;
		}

		$data[$key] = $value;
	}

	$isJunk	=
		$endpoint->junkDetection &&
		$endpoint->junkFieldName &&
		!empty($data[$endpoint->junkFieldName]);

	$submissionId = new Ulid("SUBMISSION");
	$submissions->create(
		$submissionId,
		$endpoint,
		$data,
		$isJunk,
	);

	if(!$isJunk && $endpoint->hasForwarder("email")) {
		$smtp = $smtpConfigurations->getForEndpoint($endpoint);
		foreach($forwarders->getConfirmedForEndpoint($endpoint) as $forwarder) {
			$result = $emailer->sendSubmissionWithStatus(
				$forwarder->email,
				$endpoint->title,
				$data,
				$smtp,
			);
			$forwardingLog->record($submissionId, "email", $forwarder->email, $result);

			$audit->record(
				null,
				$endpoint->id,
				"email-forwarder",
				$forwarder->id,
				"forward-submission",
				$result->successful ? "succeeded" : "failed",
				["email" => $forwarder->email, "smtp" => $smtp ? "custom" : "hexform"],
			);
		}
	}

	if(!$isJunk && $endpoint->hasForwarder("webhook") && $endpoint->forwarderUrl) {
		$result = $webhookForwarder->send($endpoint->forwarderUrl, $data);
		$forwardingLog->record(
			$submissionId,
			"webhook",
			$endpoint->forwarderUrl,
			$result,
		);
		$audit->record(
			null,
			$endpoint->id,
			"webhook-forwarder",
			null,
			"forward-submission",
			$result->successful ? "succeeded" : "failed",
			["url" => $endpoint->forwarderUrl, "statusCode" => $result->statusCode],
		);
	}

	if($endpoint->confirmationUrl) {
		$response->redirect($endpoint->confirmationUrl);
	}
	$response->redirect($endpoint->clientHost);
}
