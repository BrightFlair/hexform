create table EmailForwarder (
	id char(32) not null primary key,
	endpointId char(32) not null,
	email varchar(254) not null,
	confirmedAt datetime null,
	confirmationCode char(5) not null,
	confirmationCreatedAt datetime not null,
	createdAt datetime not null default current_timestamp,
	constraint EmailForwarder_endpoint foreign key (endpointId) references Endpoint(id) on delete cascade,
	unique EmailForwarder_endpointId_email (endpointId, email),
	index EmailForwarder_endpointId_confirmedAt (endpointId, confirmedAt)
);
