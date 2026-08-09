select f.*
from EmailForwarder f
join Endpoint e on e.id=f.endpointId
where f.id=:id and e.userId=:userId
limit 1
