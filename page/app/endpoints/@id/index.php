<?php
use Gt\Dom\HTMLDocument;
use Gt\DomTemplate\Binder;
use Gt\Http\Response;
use Gt\Input\Input;
use Gt\Routing\Path\DynamicPath;
use HexForm\Endpoint\Endpoint;
use HexForm\Endpoint\EndpointRepository;
use HexForm\User\User;

function go(
	EndpointRepository $repository,
	DynamicPath $path,
	User $user,
	Response $response,
	Binder $binder,
	HTMLDocument $document,
): void {
	$endpoint = $repository->getByIdForUser($path->get("id"), $user);
	if(!$endpoint) {
		$response->redirect("/app/endpoints/");
		return;
	}
	$binder->bindData($endpoint);
	$document
		->querySelector("[data-inbox]")
		?->setAttribute("href", "/app/submissions/?endpoint=" . urlencode($endpoint->id));
	if ($endpoint->junkDetection) {
		$document->querySelector("[data-junk-detection]")?->setAttribute("checked", "checked");
	}
	foreach ($document->querySelectorAll("[data-retention] option") as $option) {
		if ($option->getAttribute("value") === $endpoint->getRetentionValue()) {
			$option->setAttribute("selected", "selected");
		}
	}
}

function do_save(
	EndpointRepository $repository,
	DynamicPath $path,
	User $user,
	Response $response,
	Input $input,
): void {
	$e = $repository->getByIdForUser($path->get("id"), $user);
	if(!$e) {
		$response->redirect("/app/endpoints/");
		return;
	}
	$retention = $input->getString("retentionMonths");
	$repository->update(
		new Endpoint(
			$e->id,
			$e->userId,
			$e->code,
			$input->getString("title"),
			$input->getString("clientHost"),
			$input->getString("confirmationUrl"),
			$input->contains("junkDetection"),
			$input->getString("junkFieldName"),
			$input->getString("mainField"),
			$input->getString("submitterIdentityField"),
			$retention === "forever" ? null : (int)$retention,
			$input->getInt("maximumSubmissionsPerMonth") ?? 50,
			$input->getString("forwarderUrl"),
		),
	);
	$response->reload();
}

function do_delete(
	EndpointRepository $repository,
	DynamicPath $path,
	User $user,
	Response $response,
): void {
	$repository->deleteByIdForUser($path->get("id"), $user);
	$response->redirect("/app/endpoints/");
}
