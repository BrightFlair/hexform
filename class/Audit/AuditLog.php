<?php
namespace HexForm\Audit;

use GT\Database\Query\QueryCollection;

readonly class AuditLog {
	public function __construct(private QueryCollection $db) {}

	/** @param array<string, bool|int|string|null> $context */
	public function record(
		?string $actorUserId,
		?string $endpointId,
		string $subjectType,
		?string $subjectId,
		string $action,
		string $outcome,
		array $context = [],
	):void {
		$this->db->insert("create", [
			"actorUserId" => $actorUserId,
			"endpointId" => $endpointId,
			"subjectType" => $subjectType,
			"subjectId" => $subjectId,
			"action" => $action,
			"outcome" => $outcome,
			"context" => json_encode($context, JSON_THROW_ON_ERROR),
		]);
	}
}
