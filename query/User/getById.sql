select
	id,
	email
from
	User
where
	id = ?
limit 1
