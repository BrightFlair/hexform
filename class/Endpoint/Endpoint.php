<?php
namespace HexForm\Endpoint;

use DateTimeInterface;
use GT\DomTemplate\BindGetter;

readonly class Endpoint {
	/** @SuppressWarnings("PHPMD.ExcessiveParameterList") */
	public function __construct(
		public string $id,
		public string $userId,
		public string $code,
		public string $title,
		public string $clientHost,
		public ?string $confirmationUrl,
		public bool $junkDetection,
		public ?string $junkFieldName,
		public ?string $mainField,
		public ?string $submitterIdentityField,
		public ?int $retentionMonths,
		public int $maximumSubmissionsPerMonth,
		public ?string $forwarderUrl,
		public int $submissionCount = 0,
		public ?DateTimeInterface $lastSubmitted = null,
	) {}

	#[BindGetter]
	public function getActionUrl():string {
		return "/f/$this->code/";
	}

	#[BindGetter]
	public function getLastSubmittedDisplay():string {
		return $this->lastSubmitted?->format("j M Y, H:i") ?? "Never";
	}

	#[BindGetter]
	public function getRetentionValue():string {
		return $this->retentionMonths === null ? "forever" : (string)$this->retentionMonths;
	}
}
