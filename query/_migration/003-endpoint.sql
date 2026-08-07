create table Endpoint (
	id char(32) not null primary key,
	userId char(128) not null,
	code char(32) not null unique,
	title varchar(120) not null,
	clientHost varchar(2048) not null,
	confirmationUrl varchar(2048) null,
	junkDetection boolean not null default true,
	junkFieldName varchar(120) null,
	mainField varchar(120) null,
	submitterIdentityField varchar(120) null,
	retentionMonths smallint unsigned null default 1,
	maximumSubmissionsPerMonth int unsigned not null default 50,
	forwarderUrl varchar(2048) null,
	createdAt datetime not null default current_timestamp,
	constraint Endpoint_user foreign key (userId) references User(id) on delete cascade,
	index Endpoint_userId (userId)
);
