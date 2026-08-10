alter table Endpoint
add column ignoredKeys varchar(2048) not null default 'do,csrf-token,__component'
after submitterIdentityField;
