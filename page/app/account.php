<?php
use Gt\DomTemplate\Binder;
use HexForm\User\User;

function go(User $user, Binder $binder): void {
	$binder->bindData($user);
}
