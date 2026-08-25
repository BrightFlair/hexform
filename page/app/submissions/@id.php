<?php

use Gt\DomTemplate\Binder;
use Gt\Dom\HTMLDocument;
use Gt\Http\Response;
use Gt\Routing\Path\DynamicPath;
use HexForm\Submission\SubmissionRepository;
use HexForm\User\User;
use HexForm\Forwarding\SubmissionForwardingLogRepository;

function go(
	User $user,
	SubmissionRepository $submissionRepository,
	DynamicPath $path,
	Response $response,
	Binder $binder,
	SubmissionForwardingLogRepository $forwardingLog,
	HTMLDocument $document,
): void {
	$submission = $submissionRepository->getByIdForUser($path->get("id"), $user);
	if(!$submission) {
		$response->redirect("/app/submissions/");
	}

	$binder->bindData($submission);
	$binder->bindList($submission->getDataRows(), ".submission-detail dl");
	$logList = $forwardingLog->getForSubmissionByUser($submission, $user);
	$binder->bindList($logList, "[data-forwarding-log]");
	if($logList) {
		$document->querySelector("[data-no-forwarding-log]")?->remove();
	}
}
