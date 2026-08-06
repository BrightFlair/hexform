create table User (
	id char(128) not null primary key,
	email varchar(254) not null unique
);
