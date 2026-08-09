<?php
namespace HexForm\Test\Audit;

use GT\Database\Query\QueryCollection;
use HexForm\Audit\AuditLog;
use PHPUnit\Framework\TestCase;

class AuditLogTest extends TestCase {
	public function testRecordStoresActorSubjectOutcomeAndContext():void {
		$db = self::createMock(QueryCollection::class);
		$db->expects(self::once())->method("insert")->with("create", [
			"actorUserId" => "user-1",
			"endpointId" => "endpoint-1",
			"subjectType" => "email-forwarder",
			"subjectId" => "forwarder-1",
			"action" => "delete",
			"outcome" => "succeeded",
			"context" => '{"email":"team@example.com"}',
		]);
		$sut = new AuditLog($db);

		$sut->record(
			"user-1",
			"endpoint-1",
			"email-forwarder",
			"forwarder-1",
			"delete",
			"succeeded",
			["email" => "team@example.com"],
		);
	}
}
