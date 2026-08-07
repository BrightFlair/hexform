<?php
namespace HexForm\Submission;

use Gt\Database\Query\QueryCollection;
use Gt\Database\Result\Row;
use HexForm\Endpoint\Endpoint;
use HexForm\User\User;

class SubmissionRepository {
	public function __construct(private QueryCollection $db) {}

	public function create(string $id, Endpoint $endpoint, array $data, bool $isJunk):void {
		$this->db->insert("create", [
			"id" => $id,
			"endpointId" => $endpoint->id,
			"data" => json_encode($data),
			"isJunk" => $isJunk,
		]);
	}

	/** @return array<Submission> */
	public function getForUser(User $user, bool $junk = false, ?string $endpointId = null):array {
		$list = [];
		foreach(
			$this->db->fetchAll("getForUser", [
				"userId" => $user->id,
				"isJunk" => $junk,
				"endpointId" => $endpointId,
			])
			as $row
		) {
			$list[] = $this->rowToSubmission($row);
		}
		return $list;
	}

	public function getByIdForUser(string $id, User $user):?Submission {
		return $this->rowToSubmission(
			$this->db->fetch("getByIdForUser", ["id" => $id, "userId" => $user->id]),
		);
	}

	public function delete(Submission $s):void {
		$this->db->delete("delete", $s->id);
	}

	public function markNotJunk(Submission $s):void {
		$this->db->update("markNotJunk", $s->id);
	}

	public function getDashboard(User $user, ?string $endpointId = null):array {
		$s = $this->db->fetch("getDashboardSummary", [
			"userId" => $user->id,
			"endpointId" => $endpointId,
		]);
		$d = [];
		foreach(
			$this->db->fetchAll("getDailyCounts", [
				"userId" => $user->id,
				"endpointId" => $endpointId,
			])
			as $row
		) {
			$d[] = [$row->getString("day"), $row->getInt("submissionCount")];
		}
		return [
			"total" => $s?->getInt("total") ?? 0,
			"junk" => $s?->getInt("junk") ?? 0,
			"thisMonth" => $s?->getInt("thisMonth") ?? 0,
			"daily" => $d,
		];
	}

	private function rowToSubmission(?Row $r):?Submission {
		if(!$r) {
			return null;
		}
		return new Submission(
			$r->getString("id"),
			$r->getString("endpointId"),
			$r->getString("endpointTitle"),
			$r->getString("endpointCode"),
			json_decode($r->getString("data"), true) ?? [],
			$r->getBool("isJunk"),
			$r->getDateTime("createdAt"),
			$r->getString("mainField"),
			$r->getString("submitterIdentityField"),
		);
	}
}
