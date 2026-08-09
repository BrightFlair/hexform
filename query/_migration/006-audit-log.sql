create table AuditLog (
	id bigint unsigned not null auto_increment primary key,
	actorUserId varchar(128) null,
	endpointId char(32) null,
	subjectType varchar(64) not null,
	subjectId varchar(128) null,
	action varchar(64) not null,
	outcome varchar(32) not null,
	context json not null,
	createdAt datetime not null default current_timestamp,
	index AuditLog_actorUserId_createdAt (actorUserId, createdAt),
	index AuditLog_endpointId_createdAt (endpointId, createdAt),
	index AuditLog_subject_createdAt (subjectType, subjectId, createdAt)
);
