<?php
namespace HexForm\Billing;

readonly class PlanSelection {
	public function __construct(
		public string $redirectUrl,
		public string $message,
	) {}
}
