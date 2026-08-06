<?php
use Authwave\Authenticator;
use HexForm\User\User;
use Gt\Http\Response;
use Gt\Http\ServerInfo;
use Gt\Input\Input;

function go(
	?User $user,
	Response $response,
):void {
	if(!$user) {
		$response->redirect("/");
	}
}
