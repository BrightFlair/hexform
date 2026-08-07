<?php
namespace HexForm\Endpoint;

use Gt\Database\Query\QueryCollection;
use Gt\Database\Result\Row;
use HexForm\User\User;

class EndpointRepository {
	public function __construct(private QueryCollection $db) {}

	public function create(Endpoint $endpoint):void {
		$this->db->insert("create", $this->toParams($endpoint));
	}

	public function update(Endpoint $endpoint):void {
		$this->db->update("update", $this->toParams($endpoint));
	}

	public function delete(Endpoint $endpoint):void {
		$this->db->delete("delete", $endpoint->id);
	}

	/** @return array<Endpoint> */
	public function getForUser(User $user):array {
		$list = [];
		foreach($this->db->fetchAll("getForUser", $user->id) as $row) {
			$list[] = $this->rowToEndpoint($row);
		}
		return $list;
	}

	public function getByIdForUser(string $id, User $user):?Endpoint {
		return $this->rowToEndpoint(
			$this->db->fetch("getByIdForUser", ["id" => $id, "userId" => $user->id]),
		);
	}

	public function getByCode(string $code):?Endpoint {
		return $this->rowToEndpoint($this->db->fetch("getByCode", $code));
	}

	private function toParams(Endpoint $e):array {
		return [
			"id" => $e->id,
			"userId" => $e->userId,
			"code" => $e->code,
			"title" => $e->title,
			"clientHost" => $e->clientHost,
			"confirmationUrl" => $e->confirmationUrl,
			"junkDetection" => $e->junkDetection,
			"junkFieldName" => $e->junkFieldName,
			"mainField" => $e->mainField,
			"submitterIdentityField" => $e->submitterIdentityField,
			"retentionMonths" => $e->retentionMonths,
			"maximumSubmissionsPerMonth" => $e->maximumSubmissionsPerMonth,
			"forwarderUrl" => $e->forwarderUrl,
		];
	}

	private function rowToEndpoint(?Row $r):?Endpoint {
		if(!$r) {
			return null;
		}
		return new Endpoint(
			$r->getString("id"),
			$r->getString("userId"),
			$r->getString("code"),
			$r->getString("title"),
			$r->getString("clientHost"),
			$r->getString("confirmationUrl"),
			$r->getBool("junkDetection"),
			$r->getString("junkFieldName"),
			$r->getString("mainField"),
			$r->getString("submitterIdentityField"),
			$r->getInt("retentionMonths"),
			$r->getInt("maximumSubmissionsPerMonth"),
			$r->getString("forwarderUrl"),
			$r->getInt("submissionCount") ?? 0,
			$r->getDateTime("lastSubmitted"),
		);
	}
}
