alter table BillingSubscription
	add column cancelAtPeriodEnd boolean not null default false after checkedAt;
