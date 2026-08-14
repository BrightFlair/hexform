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
use HexForm\Forwarding\EmailForwarderRepository;
use HexForm\Audit\AuditLog;
function go(
	DynamicPath $path,
	EndpointRepository $endpoints,
	SubmissionRepository $submissions,
	Input $input,
	Response $response,
	ServerInfo $serverInfo,
	EmailForwarderRepository $forwarders,
	Emailer $emailer,
	AuditLog $audit,
): void {
	if($serverInfo->getRequestMethod() !== "POST") {
		return;
	}
	$endpoint = $endpoints->getByCode($path->get("code"));
	if(!$endpoint) {
		throw new HttpNotFound();
	}
	$data = [];
	$ignoredKeys = $endpoint->getIgnoredKeyList();
	foreach($input->getAll(Input::DATA_BODY) as $key => $value) {
		if(in_array($key, $ignoredKeys, true)) {
			continue;
		}
		$data[$key] = $input->get($key, Input::DATA_BODY);
	}
	$isJunk
		= $endpoint->junkDetection &&
		$endpoint->junkFieldName &&
		!empty($data[$endpoint->junkFieldName]);
	$submissions->create((string)new Ulid("SUBMISSION"), $endpoint, $data, $isJunk);
	if(!$isJunk) {
		foreach($forwarders->getConfirmedForEndpoint($endpoint) as $forwarder) {
			$emailSent = $emailer->sendSubmission($forwarder->email, $endpoint->title, $data);
			$audit->record(
				null,
				$endpoint->id,
				"email-forwarder",
				$forwarder->id,
				"forward-submission",
				$emailSent ? "succeeded" : "failed",
				["email" => $forwarder->email],
			);
		}
	}
	if($endpoint->confirmationUrl) {
		$response->redirect($endpoint->confirmationUrl);
	}
	$response->redirect($endpoint->clientHost);
}
