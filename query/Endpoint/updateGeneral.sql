update Endpoint
set title = :title,
	clientHost = :clientHost,
	confirmationUrl = :confirmationUrl,
	junkDetection = :junkDetection,
	junkFieldName = :junkFieldName,
	mainField = :mainField,
	submitterIdentityField = :submitterIdentityField,
	ignoredKeys = :ignoredKeys,
	retentionMonths = :retentionMonths,
	maximumSubmissionsPerMonth = :maximumSubmissionsPerMonth
where id = :id
	and userId = :userId
