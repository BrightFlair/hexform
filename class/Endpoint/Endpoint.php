<?php
namespace HexForm\Endpoint;

use DateTimeInterface;
use GT\DomTemplate\BindGetter;

readonly class Endpoint {
	public const string DEFAULT_IGNORED_KEYS = "do,csrf-token,__component";
	public const string DEFAULT_ENABLED_FORWARDERS = "email,webhook";

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
		public string $ignoredKeys = self::DEFAULT_IGNORED_KEYS,
		public int $submissionCount = 0,
		public ?DateTimeInterface $lastSubmitted = null,
		public string $enabledForwarders = self::DEFAULT_ENABLED_FORWARDERS,
	) {}

	#[BindGetter]
	public function getActionUrl():string {
		return "https://hexform.io/f/$this->code";
	}

	#[BindGetter]
	public function getLastSubmittedDisplay():string {
		return $this->lastSubmitted?->format("j M Y, H:i") ?? "Never";
	}

	#[BindGetter]
	public function getRetentionValue():string {
		return $this->retentionMonths === null
			? "forever"
			: (string)$this->retentionMonths;
	}

	/** @return list<string> */
	public function getIgnoredKeyList():array {
		$keys = str_getcsv($this->ignoredKeys, ",", '"', "");
		return array_values(array_filter(
			array_map(trim(...), $keys),
			fn(string $key):bool => $key !== "",
		));
	}

	/** @return list<string> */
	public function getEnabledForwarderList():array {
		return array_values(array_filter(
			array_map(trim(...), explode(",", $this->enabledForwarders)),
			fn(string $forwarder):bool => $forwarder !== "",
		));
	}

	public function hasForwarder(string $forwarder):bool {
		return in_array($forwarder, $this->getEnabledForwarderList(), true);
	}
}
