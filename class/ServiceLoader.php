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
use HexForm\Billing\BillingGateway;
use HexForm\Billing\BillingService;
use HexForm\Billing\PlanSelector;
use HexForm\Billing\BillingSubscriptionRepository;
use HexForm\Billing\StripeBillingGateway;
use Stripe\StripeClient;
use HexForm\Billing\StripeWebhookService;

/** @SuppressWarnings("PHPMD.TooManyPublicMethods") */
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

	public function loadBillingSubscriptionRepository():BillingSubscriptionRepository {
		return new BillingSubscriptionRepository(
			$this->container->get(Database::class)->queryCollection("BillingSubscription"),
		);
	}

	public function loadBillingGateway():BillingGateway {
		$stripeConfig = [
			"api_key" => $this->config->getString("stripe.secret_key"),
		];
		$testApiBase = getenv("HEXFORM_STRIPE_API_BASE");
		if($testApiBase) {
			$stripeConfig["api_base"] = $testApiBase;
		}
		return new StripeBillingGateway(
			new StripeClient($stripeConfig),
			$this->config->getString("stripe.product"),
			[
				"developer" => $this->config->getString("stripe.developer_price_lookup_key"),
				"enterprise" => $this->config->getString("stripe.enterprise_price_lookup_key"),
			],
		);
	}

	public function loadBillingService():BillingService {
		return new BillingService(
			$this->container->get(BillingGateway::class),
			$this->container->get(BillingSubscriptionRepository::class),
			$this->container->get(UserRepository::class),
		);
	}

	public function loadPlanSelector():PlanSelector {
		return new PlanSelector(
			$this->container->get(BillingService::class),
			$this->container->get(AuditLog::class),
		);
	}

	public function loadStripeWebhookService():StripeWebhookService {
		return new StripeWebhookService(
			$this->container->get(BillingService::class),
			$this->config->getString("stripe.webhook_secret"),
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
