<?php
namespace HexForm\Test\Email;

use HexForm\Email\SmtpConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SmtpConfigurationTest extends TestCase {
	#[DataProvider("dsnProvider")]
	public function testGetDsn(string $security, string $expected):void {
		$sut = new SmtpConfiguration(
			"endpoint-1",
			"smtp.example.com",
			587,
			"person@example.com",
			"p@ss word",
			$security,
			"forms@example.com",
			"Website forms",
		);

		self::assertSame($expected, $sut->getDsn());
	}

	/** @return array<string, array{string, string}> */
	public static function dsnProvider():array {
		$credentials = "person%40example.com:p%40ss%20word";
		return [
			"STARTTLS" => ["starttls", "smtp://$credentials@smtp.example.com:587?require_tls=true"],
			"implicit TLS" => ["tls", "smtps://$credentials@smtp.example.com:587"],
			"unencrypted" => ["none", "smtp://$credentials@smtp.example.com:587?auto_tls=false"],
		];
	}

	public function testGetDsn_withoutAuthentication():void {
		$sut = new SmtpConfiguration(
			"endpoint-1", "smtp.example.com", 25, null, null, "none",
			"forms@example.com", null,
		);

		self::assertSame("smtp://smtp.example.com:25?auto_tls=false", $sut->getDsn());
	}

	public function testGetDsn_withIpv6Host():void {
		$sut = new SmtpConfiguration(
			"endpoint-1", "::1", 25, null, null, "none", "forms@example.com", null,
		);

		self::assertSame("smtp://[::1]:25?auto_tls=false", $sut->getDsn());
	}
}
