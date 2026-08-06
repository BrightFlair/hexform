<?php
namespace HexForm;

use Authwave\Authenticator;
use Gt\Http\Uri;
use Gt\Session\Session;
use GT\WebEngine\Service\DefaultServiceLoader;

class ServiceLoader extends DefaultServiceLoader {
	public function loadAuthenticator():Authenticator {
		$authwaveConfig = $this->config->getSection("authwave");
		$session = $this->container->get(Session::class);
		$uri = $this->container->get(Uri::class);

		return new Authenticator(
			$authwaveConfig->getString("key"),
			$uri,
			$authwaveConfig->getString("host"),
			$session->getStore("authwave", true),
		);
	}
}
