<?php
namespace HexForm\Submission;

use GT\Database\Query\QueryCollection;
use GT\Database\Result\Row;
use HexForm\Endpoint\Endpoint;
use HexForm\User\User;

readonly class SubmissionRepository {
	public function __construct(private QueryCollection $db) {}

	/** @param array<string, mixed> $data */
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
		$resultSet = $this->db->fetchAll("getForUser", [
			"userId" => $user->id,
			"isJunk" => $junk,
			"endpointId" => $endpointId,
		]);

		foreach($resultSet as $row) {
			array_push($list, $this->rowToSubmission($row));
		}
		return $list;
	}

	public function getByIdForUser(string $id, User $user):?Submission {
		return $this->rowToSubmission(
			$this->db->fetch("getByIdForUser", ["id" => $id, "userId" => $user->id]),
		);
	}

	public function delete(Submission $submission):void {
		$this->db->delete("delete", $submission->id);
	}

	public function deleteByIdForUser(string $id, User $user):bool {
		$submission = $this->getByIdForUser($id, $user);
		if(!$submission) {
			return false;
		}

		$this->delete($submission);
		return true;
	}

	public function markNotJunk(Submission $submission):void {
		$this->db->update("markNotJunk", $submission->id);
	}

	public function markNotJunkByIdForUser(string $id, User $user):bool {
		$submission = $this->getByIdForUser($id, $user);
		if(!$submission) {
			return false;
		}

		$this->markNotJunk($submission);
		return true;
	}

// TODO: The return type from this function should be a model class.
	/**
	 * @return array{
	 *     total: int,
	 *     junk: int,
	 *     thisMonth: int,
	 *     daily: array<int, array{string|null, int|null}>
	 * }
	 */
	public function getDashboard(User $user, ?string $endpointId = null):array {
		$rowSummary = $this->db->fetch("getDashboardSummary", [
			"userId" => $user->id,
			"endpointId" => $endpointId,
		]);
		$data = [];
		foreach(
			$this->db->fetchAll("getDailyCounts", [
				"userId" => $user->id,
				"endpointId" => $endpointId,
			])
			as $row
		) {
			array_push($data, [$row->getString("day"), $row->getInt("submissionCount")]);
		}
		return [
			"total" => $rowSummary?->getInt("total") ?? 0,
			"junk" => $rowSummary?->getInt("junk") ?? 0,
			"thisMonth" => $rowSummary?->getInt("thisMonth") ?? 0,
			"daily" => $data,
		];
	}

	private function rowToSubmission(?Row $row):?Submission {
		if(!$row) {
			return null;
		}
		return new Submission(
			$row->getString("id"),
			$row->getString("endpointId"),
			$row->getString("endpointTitle"),
			$row->getString("endpointCode"),
			json_decode($row->getString("data"), true) ?? [],
			$row->getBool("isJunk"),
			$row->getDateTime("createdAt"),
			$row->getString("mainField"),
			$row->getString("submitterIdentityField"),
		);
	}
}
