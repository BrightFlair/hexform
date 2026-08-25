<?php
namespace HexForm\Test\Email;

use GT\Database\Query\QueryCollection;
use GT\Database\Result\Row;
use HexForm\Email\SmtpConfiguration;
use HexForm\Email\SmtpConfigurationRepository;
use HexForm\Endpoint\Endpoint;
use HexForm\User\User;
use PHPUnit\Framework\TestCase;

class SmtpConfigurationRepositoryTest extends TestCase {
	public function testSave():void {
		$configuration = $this->configuration();
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())->method("insert")
			->with("save", get_object_vars($configuration));

		(new SmtpConfigurationRepository($db))->save($configuration);
	}

	public function testGetForEndpointByUser_isScopedToOwner():void {
		$configuration = $this->configuration();
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())->method("fetch")->with("getForEndpointByUser", [
			"endpointId" => "endpoint-1",
			"userId" => "user-1",
		])->willReturn($this->row($configuration));

		$actual = (new SmtpConfigurationRepository($db))->getForEndpointByUser(
			$this->endpoint(),
			new User("user-1", "owner@example.com"),
		);

		self::assertEquals($configuration, $actual);
	}

	public function testDeleteForEndpointByUser_isScopedToOwner():void {
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())->method("delete")->with("deleteForEndpointByUser", [
			"endpointId" => "endpoint-1",
			"userId" => "user-1",
		]);

		(new SmtpConfigurationRepository($db))->deleteForEndpointByUser(
			$this->endpoint(),
			new User("user-1", "owner@example.com"),
		);
	}

	private function configuration():SmtpConfiguration {
		return new SmtpConfiguration(
			"endpoint-1", "smtp.example.com", 587, "user", "secret",
			"starttls", "forms@example.com", "Website forms",
		);
	}

	private function row(SmtpConfiguration $configuration):Row {
		return new Row(array_map(
			strval(...),
			array_filter(get_object_vars($configuration), fn($value) => $value !== null),
		));
	}

	private function endpoint():Endpoint {
		return new Endpoint(
			"endpoint-1", "user-1", "code", "Contact", "https://example.com",
			null, true, "company", "message", "email", 1, 50, null,
		);
	}
}
