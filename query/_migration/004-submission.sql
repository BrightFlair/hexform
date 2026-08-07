create table Submission (
	id char(32) not null primary key,
	endpointId char(32) not null,
	data json not null,
	isJunk boolean not null default false,
	createdAt datetime not null default current_timestamp,
	constraint Submission_endpoint foreign key (endpointId) references Endpoint(id) on delete cascade,
	index Submission_endpointId_createdAt (endpointId, createdAt),
	index Submission_isJunk_createdAt (isJunk, createdAt)
);
