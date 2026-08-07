<?php
use Gt\Dom\HTMLDocument;
use Gt\DomTemplate\Binder;
use Gt\Input\Input;
use HexForm\Endpoint\EndpointRepository;
use HexForm\Submission\SubmissionRepository;
use HexForm\User\User;

function go(
	User $user,
	EndpointRepository $endpoints,
	SubmissionRepository $submissions,
	Input $input,
	Binder $binder,
	HTMLDocument $document,
): void {
	$list = $endpoints->getForUser($user);
	$endpointId = $input->getString("endpoint");
	if($endpointId && !$endpoints->getByIdForUser($endpointId, $user)) {
		$endpointId = null;
	}
	$stats = $submissions->getDashboard($user, $endpointId);
	$binder->bindList($list, "[data-endpoint-select]");
	if($endpointId) {
		foreach($document->querySelectorAll("[data-endpoint-select] option") as $option) {
			if($option->getAttribute("value") === $endpointId) {
				$option->setAttribute("selected", "selected");
			}
		};
	}
	$hasSubmission = $stats["total"] > 0;
	$steps = [
		"endpoint" => !empty($list),
		"submission" => $hasSubmission,
		"junk" => false,
		"forwarder" => false,
	];
	foreach($list as $endpoint) {
		$steps["junk"]
			= $steps["junk"] || ($endpoint->junkDetection && (bool)$endpoint->junkFieldName);
		$steps["forwarder"] = $steps["forwarder"] || (bool)$endpoint->forwarderUrl;
	}
	foreach($steps as $name => $done) {
		if($done) {
			$document->querySelector("[data-step='$name']")?->classList->add("complete");
		}
	}
	$binder->bindKeyValue("completedSteps", count(array_filter($steps)));
	$binder->bindKeyValue("endpointCount", count($list));
	foreach(["total", "junk", "thisMonth"] as $key) {
		$binder->bindKeyValue($key, $stats[$key]);
	}
	$binder->bindKeyValue(
		"chartJson",
		json_encode(["data" => $stats["daily"]], JSON_UNESCAPED_SLASHES),
	);
}
