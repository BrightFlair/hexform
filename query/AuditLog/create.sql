insert into AuditLog (
	actorUserId, endpointId, subjectType, subjectId, action, outcome, context
) values (
	:actorUserId, :endpointId, :subjectType, :subjectId, :action, :outcome, :context
)
