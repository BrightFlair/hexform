insert into BillingSubscription (
	userId, stripeCustomerId, stripeSubscriptionId, plan, status,
	latestPaymentAmount, latestPaymentAt, nextPaymentAmount, nextPaymentAt,
	currency, checkedAt, cancelAtPeriodEnd, pendingPlan,
	previousPaymentAmount, previousPaymentAt
) values (
	:userId, :stripeCustomerId, :stripeSubscriptionId, :plan, :status,
	:latestPaymentAmount, :latestPaymentAt, :nextPaymentAmount, :nextPaymentAt,
	:currency, :checkedAt, :cancelAtPeriodEnd, :pendingPlan,
	:previousPaymentAmount, :previousPaymentAt
)
on duplicate key update
	stripeCustomerId = values(stripeCustomerId),
	stripeSubscriptionId = values(stripeSubscriptionId),
	plan = values(plan),
	status = values(status),
	latestPaymentAmount = values(latestPaymentAmount),
	latestPaymentAt = values(latestPaymentAt),
	nextPaymentAmount = values(nextPaymentAmount),
	nextPaymentAt = values(nextPaymentAt),
	currency = values(currency),
	checkedAt = values(checkedAt),
	cancelAtPeriodEnd = values(cancelAtPeriodEnd),
	pendingPlan = values(pendingPlan),
	previousPaymentAmount = values(previousPaymentAmount),
	previousPaymentAt = values(previousPaymentAt)
