select
	id,
	email,
	subscriptionPlan
from
	User
where
	id = ?
limit 1
