select s.*
from EndpointSmtp s
join Endpoint e on e.id = s.endpointId
where s.endpointId = :endpointId
	and e.userId = :userId
limit 1
