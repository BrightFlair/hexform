select s.*,e.title endpointTitle,e.code endpointCode,e.mainField,e.submitterIdentityField
from Submission s join Endpoint e on e.id=s.endpointId
where s.id=:id and e.userId=:userId limit 1
