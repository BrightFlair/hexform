<?php
use Authwave\Authenticator;
use Gt\Http\ServerInfo;
use Gt\Input\Input;

function go(
	Authenticator $authenticator,
	Input $input,
	ServerInfo $serverInfo,
):void {
	if($authenticator->isLoggedIn()) {
		return;
	}

	$serverHost = $serverInfo->getServerHost();
	$isLocal = $serverHost === "localhost"
		|| $serverHost === "127.0.0.1"
		|| $serverHost === "::1"
		|| str_starts_with($serverHost ?? "", "192.168.");

	if($isLocal && $input->contains("debug-auth")) {
		$authenticator->fakeLogin(
			$input->getString("debug-auth"),
			$input->getString("email"),
			"/app/",
		);
	}
}
