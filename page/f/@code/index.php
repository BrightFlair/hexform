<?php
use Gt\Http\Response;
use Gt\Http\ServerInfo;
use Gt\Input\Input;
use Gt\Routing\Path\DynamicPath;
use Gt\Ulid\Ulid;
use HexForm\Endpoint\EndpointRepository;
use HexForm\Submission\SubmissionRepository;
function go(
	DynamicPath $path,
	EndpointRepository $endpoints,
	SubmissionRepository $submissions,
	Input $input,
	Response $response,
	ServerInfo $serverInfo,
): void {
	if($serverInfo->getRequestMethod() !== "POST") {
		return;
	}
	$endpoint = $endpoints->getByCode($path->get("code"));
	if(!$endpoint) {
		$response->setStatus(404);
		return;
	}
	$data = [];
	foreach($input->getAll(Input::DATA_BODY) as $key => $value) {
		$data[$key] = $input->get($key, Input::DATA_BODY);
	}
	$isJunk
		= $endpoint->junkDetection &&
		$endpoint->junkFieldName &&
		!empty($data[$endpoint->junkFieldName]);
	$submissions->create((string)new Ulid("SUBMISSION"), $endpoint, $data, $isJunk);
	if($endpoint->confirmationUrl) {
		$response->redirect($endpoint->confirmationUrl);
	}
}
