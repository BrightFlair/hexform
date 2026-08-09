select * from EmailForwarder
where endpointId=? and confirmedAt is not null
order by createdAt
