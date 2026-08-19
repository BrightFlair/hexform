<?php
namespace HexForm\Endpoint;

use GT\Database\Query\QueryCollection;
use GT\Database\Result\Row;
use HexForm\User\User;

readonly class EndpointRepository {
	public function __construct(private QueryCollection $db) {}

	public function create(Endpoint $endpoint):void {
		$this->db->insert("create", $this->toParams($endpoint));
	}

	public function updateGeneral(Endpoint $endpoint):void {
		$params = $this->toParams($endpoint);
		unset($params["forwarderUrl"]);
		$this->db->update("updateGeneral", $params);
	}

	public function updateForwarderUrl(Endpoint $endpoint, ?string $forwarderUrl):void {
		$this->db->update("updateForwarderUrl", [
			"id" => $endpoint->id,
			"userId" => $endpoint->userId,
			"forwarderUrl" => $forwarderUrl,
		]);
	}

	public function delete(Endpoint $endpoint):void {
		$this->db->delete("delete", $endpoint->id);
	}

	public function deleteByIdForUser(string $id, User $user):bool {
		$endpoint = $this->getByIdForUser($id, $user);
		if(!$endpoint) {
			return false;
		}

		$this->delete($endpoint);
		return true;
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

	/** @return array<string, bool|int|string|null> */
	private function toParams(Endpoint $endpoint):array {
		return [
			"id" => $endpoint->id,
			"userId" => $endpoint->userId,
			"code" => $endpoint->code,
			"title" => $endpoint->title,
			"clientHost" => $endpoint->clientHost,
			"confirmationUrl" => $endpoint->confirmationUrl,
			"junkDetection" => $endpoint->junkDetection,
			"junkFieldName" => $endpoint->junkFieldName,
			"mainField" => $endpoint->mainField,
			"submitterIdentityField" => $endpoint->submitterIdentityField,
			"retentionMonths" => $endpoint->retentionMonths,
			"maximumSubmissionsPerMonth" => $endpoint->maximumSubmissionsPerMonth,
			"forwarderUrl" => $endpoint->forwarderUrl,
			"ignoredKeys" => $endpoint->ignoredKeys,
		];
	}

	private function rowToEndpoint(?Row $row):?Endpoint {
		if(!$row) {
			return null;
		}
		return new Endpoint(
			$row->getString("id"),
			$row->getString("userId"),
			$row->getString("code"),
			$row->getString("title"),
			$row->getString("clientHost"),
			$row->getString("confirmationUrl"),
			$row->getBool("junkDetection"),
			$row->getString("junkFieldName"),
			$row->getString("mainField"),
			$row->getString("submitterIdentityField"),
			$row->getInt("retentionMonths"),
			$row->getInt("maximumSubmissionsPerMonth"),
			$row->getString("forwarderUrl"),
			$row->getString("ignoredKeys") ?? Endpoint::DEFAULT_IGNORED_KEYS,
			$row->getInt("submissionCount") ?? 0,
			$row->getDateTime("lastSubmitted"),
		);
	}
}
