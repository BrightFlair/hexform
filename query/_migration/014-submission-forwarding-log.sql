create table SubmissionForwardingLog (
	id bigint unsigned not null auto_increment primary key,
	submissionId char(32) not null,
	forwarderType varchar(32) not null,
	destination varchar(2048) not null,
	successful boolean not null,
	status varchar(512) not null,
	statusCode smallint unsigned null,
	createdAt datetime not null default current_timestamp,
	constraint SubmissionForwardingLog_submission foreign key (submissionId)
		references Submission(id) on delete cascade,
	index SubmissionForwardingLog_submissionId_createdAt (submissionId, createdAt)
)
