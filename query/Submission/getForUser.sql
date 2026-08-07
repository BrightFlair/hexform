select s.*,e.title endpointTitle,e.code endpointCode,e.mainField,e.submitterIdentityField
from Submission s join Endpoint e on e.id=s.endpointId
where e.userId=:userId and s.isJunk=:isJunk and (:endpointId is null or s.endpointId=:endpointId)
order by s.createdAt desc
