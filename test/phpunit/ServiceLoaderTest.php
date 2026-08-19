<?php
namespace HexForm\Test;

use Authwave\Authenticator;
use Authwave\User as AuthUser;
use GT\Config\Config;
use GT\Config\ConfigSection;
use GT\Database\Database;
use GT\Database\Query\QueryCollection;
use GT\Http\Uri;
use GT\ServiceContainer\Container;
use GT\Session\Session;
use GT\Session\SessionStore;
use HexForm\Endpoint\EndpointRepository;
use HexForm\Audit\AuditLog;
use HexForm\Billing\BillingService;
use HexForm\Billing\PlanSelector;
use HexForm\Forwarding\EmailForwarderRepository;
use HexForm\ServiceLoader;
use HexForm\Submission\SubmissionRepository;
use HexForm\User\User;
use HexForm\User\UserRepository;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;

class ServiceLoaderTest extends TestCase {
	private const string TEST_USER_ID = "user-1";
	private const string TEST_USER_EMAIL = "person@example.com";

	#[IgnoreDeprecations]
	public function testLoadAuthenticator():void {
		$config = new Config(new ConfigSection("authwave", [
			"key" => "deployment_secret",
			"host" => "login.example.com",
		]));
		$sessionStore = self::createStub(SessionStore::class);
		$session = self::createMock(Session::class);
		$session->expects(self::once())
			->method("getStore")
			->with("authwave", true)
			->willReturn($sessionStore);
		$container = new Container();
		$container->set($session, new Uri("https://hexform.test/app/"));
		$sut = new ServiceLoader($config, $container);

		$authenticator = $sut->loadAuthenticator();

		self::assertInstanceOf(Authenticator::class, $authenticator);
		self::assertFalse($authenticator->isLoggedIn());
	}

	public function testLoadUser_whenLoggedOut():void {
		$authenticator = self::createMock(Authenticator::class);
		$authenticator->expects(self::once())->method("isLoggedIn")->willReturn(false);
		$container = new Container();
		$container->set($authenticator);
		$sut = new ServiceLoader(new Config(), $container);

		self::assertNull($sut->loadUser());
	}

	public function testLoadUser_whenLoggedIn():void {
		$authUser = new AuthUser(self::TEST_USER_ID, self::TEST_USER_EMAIL);
		$expected = new User(self::TEST_USER_ID, self::TEST_USER_EMAIL);
		$authenticator = self::createMock(Authenticator::class);
		$authenticator->method("isLoggedIn")->willReturn(true);
		$authenticator->expects(self::once())->method("getUser")->willReturn($authUser);
		$repository = self::createMock(UserRepository::class);
		$repository->expects(self::once())
			->method("fromAuthwaveUser")
			->with($authUser)
			->willReturn($expected);
		$container = new Container();
		$container->set($authenticator, $repository);
		$sut = new ServiceLoader(new Config(), $container);

		self::assertSame($expected, $sut->loadUser());
	}

	public function testLoadUserRepository():void {
		$queryCollection = self::createStub(QueryCollection::class);
		$database = self::createMock(Database::class);
		$database->expects(self::once())
			->method("queryCollection")
			->with("User")
			->willReturn($queryCollection);
		$sut = new ServiceLoader(new Config(), $this->createContainer($database));

		self::assertInstanceOf(UserRepository::class, $sut->loadUserRepository());
	}

	public function testLoadEndpointRepository():void {
		$queryCollection = self::createStub(QueryCollection::class);
		$database = self::createMock(Database::class);
		$database->expects(self::once())
			->method("queryCollection")
			->with("Endpoint")
			->willReturn($queryCollection);
		$sut = new ServiceLoader(new Config(), $this->createContainer($database));

		self::assertInstanceOf(EndpointRepository::class, $sut->loadEndpointRepository());
	}

	public function testLoadSubmissionRepository():void {
		$queryCollection = self::createStub(QueryCollection::class);
		$database = self::createMock(Database::class);
		$database->expects(self::once())
			->method("queryCollection")
			->with("Submission")
			->willReturn($queryCollection);
		$sut = new ServiceLoader(new Config(), $this->createContainer($database));

		self::assertInstanceOf(SubmissionRepository::class, $sut->loadSubmissionRepository());
	}

	public function testLoadEmailForwarderRepository():void {
		$queryCollection = self::createStub(QueryCollection::class);
		$database = self::createMock(Database::class);
		$database->expects(self::once())
			->method("queryCollection")
			->with("EmailForwarder")
			->willReturn($queryCollection);
		$sut = new ServiceLoader(new Config(), $this->createContainer($database));

		self::assertInstanceOf(
			EmailForwarderRepository::class,
			$sut->loadEmailForwarderRepository(),
		);
	}

	public function testLoadAuditLog():void {
		$queryCollection = self::createStub(QueryCollection::class);
		$database = self::createMock(Database::class);
		$database->expects(self::once())
			->method("queryCollection")
			->with("AuditLog")
			->willReturn($queryCollection);
		$sut = new ServiceLoader(new Config(), $this->createContainer($database));

		self::assertInstanceOf(AuditLog::class, $sut->loadAuditLog());
	}

	public function testLoadPlanSelector():void {
		$container = new Container();
		$container->set(
			self::createStub(BillingService::class),
			self::createStub(AuditLog::class),
		);
		$sut = new ServiceLoader(new Config(), $container);

		self::assertInstanceOf(PlanSelector::class, $sut->loadPlanSelector());
	}

	private function createContainer(Database $database):Container {
		$container = new Container();
		$container->set($database);
		return $container;
	}
}
