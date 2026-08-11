<?php
namespace HexForm\User;

readonly class User {
	public function __construct(
		public string $id,
		public string $email,
		public ?string $subscriptionPlan = null,
	) {}
}
