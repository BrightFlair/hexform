insert into EmailForwarder (
	id, endpointId, email, confirmationCode, confirmationCreatedAt, confirmedAt
) values (
	:id, :endpointId, :email, :confirmationCode, :confirmationCreatedAt, :confirmedAt
)
