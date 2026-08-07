<?php
namespace HexForm;

use Authwave\Authenticator;
use HexForm\User\User;
use HexForm\User\UserRepository;
use HexForm\Endpoint\EndpointRepository;
use HexForm\Submission\SubmissionRepository;
use Gt\Database\Database;
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

	public function loadUser():?User {
		$authenticator = $this->container->get(Authenticator::class);
		if(!$authenticator->isLoggedIn()) {
			return null;
		}

		$userRepository = $this->container->get(UserRepository::class);
		return $userRepository->fromAuthwaveUser($authenticator->getUser());
	}

	public function loadUserRepository():UserRepository {
		$database = $this->container->get(Database::class);
		return new UserRepository($database->queryCollection("User"));
	}

	public function loadEndpointRepository():EndpointRepository {
		return new EndpointRepository($this->container->get(Database::class)->queryCollection("Endpoint"));
	}

	public function loadSubmissionRepository():SubmissionRepository {
		return new SubmissionRepository($this->container->get(Database::class)->queryCollection("Submission"));
	}
}
