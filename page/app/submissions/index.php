<?php
use Gt\Dom\Element;
use Gt\Dom\HTMLDocument;
use Gt\DomTemplate\Binder;
use Gt\Http\Response;
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
	$endpointId = $input->getString("endpoint");
	if($endpointId && !$endpoints->getByIdForUser($endpointId, $user)) {
		$endpointId = null;
	}
	$binder->bindList($endpoints->getForUser($user), "[data-endpoint-select]");
	if($endpointId) {
		foreach($document->querySelectorAll("[data-endpoint-select] option") as $option) {
			if($option->getAttribute("value") === $endpointId) {
				$option->setAttribute("selected", "selected");
			}
		};
	}
	$list = $submissions->getForUser($user, false, $endpointId);
	if($list) {
		$document->querySelector(".empty-state")?->remove();
	}
	$binder->bindListCallback(
		$list,
		function(Element $row, array $data):array {
			$row->querySelector("[data-read]")?->setAttribute(
				"href",
				"/app/submissions/" . urlencode($data["id"]) . "/",
			);
			return $data;
		},
		$document->querySelector("tbody"),
	);
}

function do_delete(
	User $user,
	SubmissionRepository $repository,
	Input $input,
	Response $response,
): void {
	if($s = $repository->getByIdForUser($input->getString("id"), $user)) {
		$repository->delete($s);
	}
	$response->reload();
}
