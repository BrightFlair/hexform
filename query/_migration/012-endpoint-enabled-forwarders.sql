alter table Endpoint
add column enabledForwarders varchar(255) not null default 'email,webhook'
after forwarderUrl;
