insert into EndpointSmtp (
	endpointId, host, port, username, password, security, fromAddress, fromName
) values (
	:endpointId, :host, :port, :username, :password, :security, :fromAddress, :fromName
)
on duplicate key update
	host = values(host),
	port = values(port),
	username = values(username),
	password = values(password),
	security = values(security),
	fromAddress = values(fromAddress),
	fromName = values(fromName)
