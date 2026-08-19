<?php
namespace HexForm\Test\Email;

use HexForm\Email\ConfirmationCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfirmationCodeTest extends TestCase {
	public function testDefaultCodeHasFiveDigits():void {
		self::assertMatchesRegularExpression('/^[1-9][0-9]{4}$/', (string)new ConfirmationCode());
	}

	public function testConfiguredCodeHasRequestedLength():void {
		self::assertMatchesRegularExpression('/^[1-9][0-9]{7}$/', (string)new ConfirmationCode(8));
	}

	#[DataProvider("invalidLengthProvider")]
	public function testRejectsLengthOutsideIntegerRange(int $length):void {
		$this->expectException(InvalidArgumentException::class);

		new ConfirmationCode($length);
	}

	/** @return array<string, array{int}> */
	public static function invalidLengthProvider():array {
		return [
			"zero" => [0],
			"negative" => [-1],
			"too large" => [19],
		];
	}
}
