update Endpoint set title=:title,clientHost=:clientHost,confirmationUrl=:confirmationUrl,junkDetection=:junkDetection,junkFieldName=:junkFieldName,mainField=:mainField,submitterIdentityField=:submitterIdentityField,retentionMonths=:retentionMonths,maximumSubmissionsPerMonth=:maximumSubmissionsPerMonth,forwarderUrl=:forwarderUrl
where id=:id and userId=:userId limit 1
