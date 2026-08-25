update Endpoint
set enabledForwarders = :enabledForwarders
where id = :id
	and userId = :userId
