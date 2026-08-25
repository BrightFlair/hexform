<?php
namespace HexForm\Email;

use GT\Database\Query\QueryCollection;
use GT\Database\Result\Row;
use HexForm\Endpoint\Endpoint;
use HexForm\User\User;

readonly class SmtpConfigurationRepository {
	public function __construct(private QueryCollection $db) {}

	public function getForEndpoint(Endpoint $endpoint):?SmtpConfiguration {
		return $this->rowToConfiguration($this->db->fetch("getForEndpoint", $endpoint->id));
	}

	public function getForEndpointByUser(Endpoint $endpoint, User $user):?SmtpConfiguration {
		return $this->rowToConfiguration($this->db->fetch("getForEndpointByUser", [
			"endpointId" => $endpoint->id,
			"userId" => $user->id,
		]));
	}

	public function save(SmtpConfiguration $configuration):void {
		$this->db->insert("save", get_object_vars($configuration));
	}

	public function deleteForEndpointByUser(Endpoint $endpoint, User $user):void {
		$this->db->delete("deleteForEndpointByUser", [
			"endpointId" => $endpoint->id,
			"userId" => $user->id,
		]);
	}

	private function rowToConfiguration(?Row $row):?SmtpConfiguration {
		if(!$row) {
			return null;
		}

		return new SmtpConfiguration(
			$row->getString("endpointId"),
			$row->getString("host"),
			$row->getInt("port"),
			$row->getString("username"),
			$row->getString("password"),
			$row->getString("security"),
			$row->getString("fromAddress"),
			$row->getString("fromName"),
		);
	}
}
