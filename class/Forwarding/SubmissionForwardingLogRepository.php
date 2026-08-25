<?php
namespace HexForm\Forwarding;

use GT\Database\Query\QueryCollection;
use GT\Database\Result\Row;
use HexForm\Submission\Submission;
use HexForm\User\User;

readonly class SubmissionForwardingLogRepository {
	public function __construct(private QueryCollection $db) {}

	public function record(
		string $submissionId,
		string $forwarderType,
		string $destination,
		ForwardingResult $result,
	):void {
		$this->db->insert("create", [
			"submissionId" => $submissionId,
			"forwarderType" => $forwarderType,
			"destination" => $destination,
			"successful" => $result->successful,
			"status" => $result->status,
			"statusCode" => $result->statusCode,
		]);
	}

	/** @return array<SubmissionForwardingLog> */
	public function getForSubmissionByUser(Submission $submission, User $user):array {
		$list = [];
		foreach($this->db->fetchAll("getForSubmissionByUser", [
			"submissionId" => $submission->id,
			"userId" => $user->id,
		]) as $row) {
			array_push($list, $this->rowToLog($row));
		}
		return $list;
	}

	private function rowToLog(Row $row):SubmissionForwardingLog {
		return new SubmissionForwardingLog(
			$row->getInt("id"),
			$row->getString("submissionId"),
			$row->getString("forwarderType"),
			$row->getString("destination"),
			$row->getBool("successful"),
			$row->getString("status"),
			$row->getInt("statusCode"),
			$row->getDateTime("createdAt"),
		);
	}
}
