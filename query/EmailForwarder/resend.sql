update EmailForwarder
set confirmationCode=:confirmationCode, confirmationCreatedAt=:confirmationCreatedAt
where id=:id and confirmedAt is null
