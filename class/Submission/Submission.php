<?php
namespace HexForm\Submission;

use DateTimeInterface;
use GT\DomTemplate\BindGetter;

readonly class Submission {
	/** @param array<string, mixed> $data */
	public function __construct(
		public string $id,
		public string $endpointId,
		public string $endpointTitle,
		public string $endpointCode,
		public array $data,
		public bool $isJunk,
		public DateTimeInterface $createdAt,
		public ?string $mainField = null,
		public ?string $submitterIdentityField = null,
	) {}

	#[BindGetter]
	public function getDateDisplay():string {
		return $this->createdAt->format("j M Y, H:i");
	}

	#[BindGetter]
	public function getSubmitter():string {
		return $this->fieldPreview($this->submitterIdentityField, "Unknown");
	}

	#[BindGetter]
	public function getContents():string {
		return $this->fieldPreview($this->mainField, "No preview configured");
	}

	/** @return array<int, array{field: string, value: string}> */
	public function getDataRows():array {
		return array_map(
			fn($key, $value) => [
				"field" => $key,
				"value" => $this->stringify($value),
			],
			array_keys($this->data),
			$this->data,
		);
	}

	private function fieldPreview(?string $field, string $fallback):string {
		if(!$field || !isset($this->data[$field])) {
			return $fallback;
		}
		$value = $this->stringify($this->data[$field]);
		return mb_strimwidth($value, 0, 100, "…");
	}

	private function stringify(mixed $value):string {
		if(is_scalar($value)) {
			return (string)$value;
		}

		return json_encode($value) ?: "";
	}
}
