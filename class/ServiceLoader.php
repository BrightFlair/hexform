<?php
namespace HexForm;

use Authwave\Authenticator;
use HexForm\User\User;
use HexForm\Audit\AuditLog;
use HexForm\User\UserRepository;
use HexForm\Endpoint\EndpointRepository;
use HexForm\Submission\SubmissionRepository;
use HexForm\Email\Emailer;
use HexForm\Forwarding\EmailForwarderRepository;
use GT\Database\Database;
use GT\Http\Uri;
use GT\Session\Session;
use HexForm\UI\Flash;
use GT\WebEngine\Service\DefaultServiceLoader;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

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
		return new EndpointRepository(
			$this->container->get(Database::class)
				->queryCollection("Endpoint")
		);
	}

	public function loadSubmissionRepository():SubmissionRepository {
		return new SubmissionRepository(
			$this->container->get(Database::class)
				->queryCollection("Submission")
		);
	}

	public function loadEmailForwarderRepository():EmailForwarderRepository {
		return new EmailForwarderRepository(
			$this->container->get(Database::class)->queryCollection("EmailForwarder"),
		);
	}

	public function loadAuditLog():AuditLog {
		return new AuditLog(
			$this->container->get(Database::class)->queryCollection("AuditLog"),
		);
	}

	public function loadFlash():Flash {
		return new Flash(
			$this->container->get(Session::class)->getStore("flash", true),
		);
	}

	public function loadEmailer():Emailer {
		return new Emailer($this->container->get(Mailer::class));
	}

	/** @SuppressWarnings("PHPMD.StaticAccess") */
	public function loadSymfonyMailer():Mailer {
		$host = $this->config->getString("email.host");
		$port = $this->config->getString("email.port");
		$username = rawurlencode($this->config->getString("email.username"));
		$password = rawurlencode($this->config->getString("email.password"));
		$credentials = $username === "" ? "" : "$username:$password@";
		return new Mailer(Transport::fromDsn("smtp://$credentials$host:$port"));
	}
}
