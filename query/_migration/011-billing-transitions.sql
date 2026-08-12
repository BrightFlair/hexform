alter table BillingSubscription
	add column pendingPlan varchar(32) null after cancelAtPeriodEnd,
	add column previousPaymentAmount int unsigned null after pendingPlan,
	add column previousPaymentAt datetime null after previousPaymentAmount;
