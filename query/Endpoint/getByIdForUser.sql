select e.*, count(s.id) submissionCount, max(s.createdAt) lastSubmitted
from Endpoint e left join Submission s on s.endpointId=e.id
where e.id=:id and e.userId=:userId group by e.id limit 1
