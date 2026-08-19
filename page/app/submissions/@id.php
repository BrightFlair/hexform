<?php

use Gt\DomTemplate\Binder;
use Gt\Http\Response;
use Gt\Routing\Path\DynamicPath;
use HexForm\Submission\SubmissionRepository;
use HexForm\User\User;

function go(
	User $user,
	SubmissionRepository $submissionRepository,
	DynamicPath $path,
	Response $response,
	Binder $binder,
): void {
	$submission = $submissionRepository->getByIdForUser($path->get("id"), $user);
	if(!$submission) {
		$response->redirect("/app/submissions/");
	}

	$binder->bindData($submission);
	$binder->bindList($submission->getDataRows(), ".submission-detail dl");
}
