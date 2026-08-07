<?php
use Gt\DomTemplate\Binder;
use Gt\Http\Response;
use Gt\Routing\Path\DynamicPath;
use HexForm\Submission\SubmissionRepository;
use HexForm\User\User;

function go(
	User $user,
	SubmissionRepository $repository,
	DynamicPath $path,
	Response $response,
	Binder $binder,
): void {
	$s = $repository->getByIdForUser($path->get("id"), $user);
	if(!$s) {
		$response->redirect("/app/submissions/");
		return;
	}
	$binder->bindData($s);
	$binder->bindList($s->getDataRows(), ".submission-detail dl");
}
