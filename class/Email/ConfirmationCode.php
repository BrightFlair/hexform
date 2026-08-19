<?php
namespace HexForm\Email;

use InvalidArgumentException;
use Stringable;

readonly class ConfirmationCode implements Stringable {
	private int $code;

	public function __construct(int $codeLength = 5) {
		if($codeLength < 1 || $codeLength > 18) {
			throw new InvalidArgumentException("Confirmation code length must be between 1 and 18.");
		}

		$minimum = 10 ** ($codeLength - 1);
		$maximum = (10 ** $codeLength) - 1;
		$this->code = random_int($minimum, $maximum);
	}

	public function __toString():string {
		return (string)$this->code;
	}
}
