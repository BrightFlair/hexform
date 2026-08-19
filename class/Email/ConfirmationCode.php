<?php
namespace HexForm\Email;

use Stringable;

class ConfirmationCode implements Stringable {
	private int $code;

	public function __construct(int $codeLength = 5) {
		$maximum = str_repeat("9", $codeLength);
		$minimum = "1" . str_repeat("0", $codeLength - 1);
		$this->code = random_int($minimum, $maximum);
	}

	public function __toString():string {
		return $this->code;
	}
}
