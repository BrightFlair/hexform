create table EndpointSmtp (
	endpointId char(32) not null primary key,
	host varchar(253) not null,
	port smallint unsigned not null,
	username varchar(255) null,
	password varchar(2048) null,
	security varchar(16) not null default 'starttls',
	fromAddress varchar(254) not null,
	fromName varchar(255) null,
	createdAt datetime not null default current_timestamp,
	updatedAt datetime not null default current_timestamp on update current_timestamp,
	constraint EndpointSmtp_endpoint foreign key (endpointId) references Endpoint(id) on delete cascade
);
