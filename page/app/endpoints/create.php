<?php
use Gt\Http\Response;
use Gt\Input\Input;
use Gt\Ulid\Ulid;
use HexForm\Endpoint\Endpoint;
use HexForm\Endpoint\EndpointRepository;
use HexForm\User\User;

function do_create(
	User $user,
	EndpointRepository $repository,
	Input $input,
	Response $response,
): void {
	$id = (string) new Ulid("ENDPOINT");
	$code = strtolower(substr(str_replace("_", "", (string) new Ulid()), -16));
	$repository->create(
		new Endpoint(
			$id,
			$user->id,
			$code,
			$input->getString("title"),
			$input->getString("clientHost"),
			null,
			true,
			"company",
			null,
			null,
			1,
			50,
			null,
		),
	);
	$response->redirect("/app/endpoints/$id/");
}
