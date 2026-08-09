<?php
use Authwave\Authenticator;
use HexForm\User\User;
use Gt\Http\Response;
use Gt\Input\Input;

function go(?User $user, Response $response, Input $input, Authenticator $authenticator): void
{
	if ($input->contains("logout")) {
		$authenticator->logout();
		$response->redirect("/");
		return;
	}
	if (!$user) {
		$response->redirect("/");
		return;
	}
}
