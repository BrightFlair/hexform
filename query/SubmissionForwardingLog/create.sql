insert into SubmissionForwardingLog (
	submissionId,
	forwarderType,
	destination,
	successful,
	status,
	statusCode
) values (
	:submissionId,
	:forwarderType,
	:destination,
	:successful,
	:status,
	:statusCode
)
