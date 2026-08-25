<?php
namespace HexForm\Forwarding;

readonly class ForwardingResult {
	public function __construct(
		public bool $successful,
		public string $status,
		public ?int $statusCode = null,
	) {}
}
