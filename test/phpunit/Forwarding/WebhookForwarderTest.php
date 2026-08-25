<?php
namespace HexForm\Test\Forwarding;

use GT\Curl\CurlException;
use GT\Curl\CurlInterface;
use HexForm\Forwarding\WebhookForwarder;
use PHPUnit\Framework\TestCase;

class WebhookForwarderTest extends TestCase {
	public function testSend_postsJsonAndReturnsHttpStatus():void {
		$curl = self::createMock(CurlInterface::class);
		$curl->expects(self::once())->method("setOptArray")->with(self::callback(
			fn(array $options):bool =>
				$options[CURLOPT_POST] === true
				&& $options[CURLOPT_POSTFIELDS] === '{"message":"Hello"}'
				&& in_array("Content-Type: application/json", $options[CURLOPT_HTTPHEADER], true)
		))->willReturn(true);
		$curl->expects(self::once())->method("exec")->willReturn("");
		$curl->expects(self::once())
			->method("getInfo")
			->with(CURLINFO_RESPONSE_CODE)
			->willReturn(202);
		$sut = new WebhookForwarder(fn(string $url):CurlInterface => $curl);

		$result = $sut->send("https://example.com/hook", ["message" => "Hello"]);

		self::assertTrue($result->successful);
		self::assertSame(202, $result->statusCode);
		self::assertSame("HTTP 202", $result->status);
	}

	public function testSend_returnsFailedResultForTransportError():void {
		$curl = self::createMock(CurlInterface::class);
		$curl->method("setOptArray")->willReturn(true);
		$curl->method("exec")->willThrowException(new CurlException("Connection refused"));
		$sut = new WebhookForwarder(fn(string $url):CurlInterface => $curl);

		$result = $sut->send("https://example.com/hook", []);

		self::assertFalse($result->successful);
		self::assertNull($result->statusCode);
		self::assertSame("Request failed: Connection refused", $result->status);
	}

	public function testSend_treatsNonSuccessfulHttpStatusAsFailure():void {
		$curl = self::createMock(CurlInterface::class);
		$curl->method("setOptArray")->willReturn(true);
		$curl->method("exec")->willReturn("");
		$curl->method("getInfo")->willReturn(503);
		$sut = new WebhookForwarder(fn(string $url):CurlInterface => $curl);

		$result = $sut->send("https://example.com/hook", []);

		self::assertFalse($result->successful);
		self::assertSame("HTTP 503", $result->status);
	}
}
