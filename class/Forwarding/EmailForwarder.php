<?php
namespace HexForm\Forwarding;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use GT\DomTemplate\BindGetter;

readonly class EmailForwarder {
	private const RESEND_DELAY = "PT2M";

	public function __construct(
		public string $id,
		public string $endpointId,
		public string $email,
		public ?DateTimeInterface $confirmedAt,
		public string $confirmationCode,
		public DateTimeInterface $confirmationCreatedAt,
	) {}

	#[BindGetter]
	public function getStatus():string {
		return $this->isConfirmed() ? "Confirmed" : "Pending";
	}

	public function isConfirmed():bool {
		return $this->confirmedAt !== null;
	}

	public function canResend(?DateTimeInterface $now = null):bool {
		$now ??= new DateTimeImmutable();
		$availableAt = DateTimeImmutable::createFromInterface($this->confirmationCreatedAt)
			->add(new DateInterval(self::RESEND_DELAY));
		return !$this->isConfirmed() && $now >= $availableAt;
	}

	#[BindGetter]
	public function getCanResend():bool {
		return $this->canResend();
	}
}
