<?php
namespace HexForm\Forwarding;

use Closure;
use GT\Curl\Curl;
use GT\Curl\CurlException;
use GT\Curl\CurlInterface;

readonly class WebhookForwarder {
	/** @var Closure(string):CurlInterface */
	private Closure $curlFactory;

	/** @param null|Closure(string):CurlInterface $curlFactory */
	public function __construct(?Closure $curlFactory = null) {
		$this->curlFactory = $curlFactory ?? fn(string $url):CurlInterface => new Curl($url);
	}

	/** @param array<string, mixed> $submission */
	public function send(string $url, array $submission):ForwardingResult {
		$curl = ($this->curlFactory)($url);
		$curl->setOptArray([
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode($submission, JSON_THROW_ON_ERROR),
			CURLOPT_HTTPHEADER => [
				"Accept: application/json",
				"Content-Type: application/json",
				"User-Agent: HexForm",
			],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 10,
		]);

		try {
			$curl->exec();
			$statusCode = (int)$curl->getInfo(CURLINFO_RESPONSE_CODE);
			$successful = $statusCode >= 200 && $statusCode < 300;
			return new ForwardingResult(
				$successful,
				$statusCode > 0 ? "HTTP $statusCode" : "No HTTP response",
				$statusCode > 0 ? $statusCode : null,
			);
		}
		catch(CurlException $exception) {
			return new ForwardingResult(
				false,
				"Request failed: " . mb_strimwidth($exception->getMessage(), 0, 450, "…"),
			);
		}
	}
}
