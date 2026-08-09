select f.*
from EmailForwarder f
join Endpoint e on e.id=f.endpointId
where f.endpointId=:endpointId and e.userId=:userId
order by f.createdAt
