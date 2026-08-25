<?php
namespace HexForm\Forwarding;

use DateTimeInterface;
use GT\DomTemplate\BindGetter;

readonly class SubmissionForwardingLog {
	public function __construct(
		public int $id,
		public string $submissionId,
		public string $forwarderType,
		public string $destination,
		public bool $successful,
		public string $status,
		public ?int $statusCode,
		public DateTimeInterface $createdAt,
	) {}

	#[BindGetter]
	public function getTypeDisplay():string {
		return match($this->forwarderType) {
			"email" => "Email",
			"webhook" => "Webhook",
			default => ucfirst($this->forwarderType),
		};
	}

	#[BindGetter]
	public function getOutcomeDisplay():string {
		return $this->successful ? "Succeeded" : "Failed";
	}

	#[BindGetter]
	public function getDateDisplay():string {
		return $this->createdAt->format("j M Y, H:i:s");
	}
}
