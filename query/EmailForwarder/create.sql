insert into EmailForwarder (
	id, endpointId, email, confirmationCode, confirmationCreatedAt
) values (
	:id, :endpointId, :email, :confirmationCode, :confirmationCreatedAt
)
