<?php
use Authwave\Authenticator;
use GT\Http\Response;
use Gt\Http\ServerInfo;
use Gt\Input\Input;

function go(
	Authenticator $authenticator,
	Input $input,
	ServerInfo $serverInfo,
	Response $response,
):void {
	if($input->contains("logout")) {
		$authenticator->logout("/");
		$response->redirect("/");
		return;
	}

	$serverHost = $serverInfo->getServerHost();
	$debugAuthenticationEnabled = getenv("HEXFORM_BEHAT") === "1"
		|| $serverHost === "localhost"
		|| $serverHost === "127.0.0.1"
		|| $serverHost === "::1"
		|| str_starts_with($serverHost ?? "", "192.168.");

	$debugAuthenticationRequested = $input->contains("debug-auth")
		&& !$input->contains(Authenticator::RESPONSE_QUERY_PARAMETER);
	if($debugAuthenticationEnabled && $debugAuthenticationRequested) {
		$authenticator->fakeLogin(
			$input->getString("debug-auth"),
			$input->getString("email"),
			"/app/",
		);
		return;
	}

	if($authenticator->isLoggedIn()) {
		return;
	}
}
