<?php

use Gt\Dom\Element;
use Gt\Dom\HTMLDocument;
use Gt\DomTemplate\Binder;
use HexForm\Endpoint\EndpointRepository;
use HexForm\User\User;

function go(
	User $user,
	EndpointRepository $repository,
	Binder $binder,
	HTMLDocument $document,
):void {
	$list = $repository->getForUser($user);
	if($list) {
		$document->querySelector(".empty-state")?->remove();
	}

	$binder->bindListCallback(
		$list,
		function(Element $row, array $data):array {
			$row->querySelector("[data-inbox]")?->setAttribute(
				"href",
				"/app/submissions/?endpoint=" . urlencode($data["id"]),
			);
			$row->querySelector("[data-configure]")?->setAttribute(
				"href",
				"/app/endpoints/" . urlencode($data["id"]) . "/",
			);
			return $data;
		},
		$document->querySelector("tbody"),
	);
}
