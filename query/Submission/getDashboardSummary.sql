select count(s.id) total,
	coalesce(sum(s.isJunk),0) junk,
	coalesce(sum(s.createdAt >= date_format(current_date,'%Y-%m-01')),0) thisMonth
from Endpoint e left join Submission s on s.endpointId=e.id
where e.userId=:userId and (:endpointId is null or e.id=:endpointId)
