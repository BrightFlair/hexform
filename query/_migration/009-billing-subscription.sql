create table BillingSubscription (
	userId char(128) not null primary key,
	stripeCustomerId varchar(255) not null unique,
	stripeSubscriptionId varchar(255) not null unique,
	plan varchar(32) not null,
	status varchar(32) not null,
	latestPaymentAmount int unsigned null,
	latestPaymentAt datetime null,
	nextPaymentAmount int unsigned null,
	nextPaymentAt datetime null,
	currency char(3) not null,
	checkedAt datetime not null,
	createdAt datetime not null default current_timestamp,
	updatedAt datetime not null default current_timestamp on update current_timestamp,
	constraint BillingSubscription_user foreign key (userId) references User(id) on delete cascade
);
