alter table User
	add column subscriptionPlan varchar(32) not null default 'free';
