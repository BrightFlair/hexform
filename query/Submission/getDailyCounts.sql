select date(s.createdAt) day,count(*) submissionCount
from Submission s join Endpoint e on e.id=s.endpointId
where e.userId=:userId and (:endpointId is null or e.id=:endpointId) and s.createdAt >= current_date - interval 30 day
group by date(s.createdAt) order by day
