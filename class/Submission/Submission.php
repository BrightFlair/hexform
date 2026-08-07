<?php
namespace HexForm\Submission;

use DateTimeInterface;
use Gt\DomTemplate\BindGetter;

readonly class Submission {
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

	public function getDataRows():array {
		return array_map(
			fn($k, $v) => ["field" => $k, "value" => is_scalar($v) ? (string)$v : json_encode($v)],
			array_keys($this->data),
			$this->data,
		);
	}

	private function fieldPreview(?string $field, string $fallback):string {
		if(!$field || !isset($this->data[$field])) {
			return $fallback;
		}
		$value = is_scalar($this->data[$field])
			? (string)$this->data[$field]
			: json_encode($this->data[$field]);
		return mb_strimwidth($value, 0, 100, "…");
	}
}
