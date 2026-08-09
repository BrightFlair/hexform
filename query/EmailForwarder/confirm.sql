update EmailForwarder
set confirmedAt=current_timestamp
where id=:id and confirmationCode=:confirmationCode and confirmedAt is null
