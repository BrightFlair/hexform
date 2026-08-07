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

function find_junk(User $user, SubmissionRepository $repository, Input $input) {
	return $repository->getByIdForUser($input->getString("id"), $user);
}

function do_not_junk(
	User $user,
	SubmissionRepository $repository,
	Input $input,
	Response $response,
): void {
	if($s = find_junk($user, $repository, $input)) {
		$repository->markNotJunk($s);
	}
	$response->reload();
}

function do_delete(
	User $user,
	SubmissionRepository $repository,
	Input $input,
	Response $response,
): void {
	if($s = find_junk($user, $repository, $input)) {
		$repository->delete($s);
	}
	$response->reload();
}
