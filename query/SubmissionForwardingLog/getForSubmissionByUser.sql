select l.*
from SubmissionForwardingLog l
join Submission s on s.id=l.submissionId
join Endpoint e on e.id=s.endpointId
where l.submissionId=:submissionId and e.userId=:userId
order by l.createdAt,l.id
