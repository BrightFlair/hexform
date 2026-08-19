<?php

use Gt\Dom\Element;
use Gt\Dom\HTMLDocument;
use Gt\DomTemplate\Binder;
use Gt\Http\Response;
use Gt\Input\Input;
use HexForm\Submission\SubmissionRepository;
use HexForm\User\User;

function go(
	User $user,
	SubmissionRepository $repository,
	Binder $binder,
	HTMLDocument $document,
): void {
	$list = $repository->getForUser($user, true);
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

function do_not_junk(
	User $user,
	SubmissionRepository $repository,
	Input $input,
	Response $response,
): void {
	$repository->markNotJunkByIdForUser($input->getString("id"), $user);
	$response->reload();
}

function do_delete(
	User $user,
	SubmissionRepository $repository,
	Input $input,
	Response $response,
): void {
	$repository->deleteByIdForUser($input->getString("id"), $user);
	$response->reload();
}
