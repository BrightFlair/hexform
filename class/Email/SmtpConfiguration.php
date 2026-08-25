<?php
namespace HexForm\Email;

use Symfony\Component\Mime\Address;

readonly class SmtpConfiguration {
	public const string SECURITY_STARTTLS = "starttls";
	public const string SECURITY_TLS = "tls";
	public const string SECURITY_NONE = "none";

	public function __construct(
		public string $endpointId,
		public string $host,
		public int $port,
		public ?string $username,
		public ?string $password,
		public string $security,
		public string $fromAddress,
		public ?string $fromName,
	) {}

	/** @return list<string> */
	public static function securityList():array {
		return [self::SECURITY_STARTTLS, self::SECURITY_TLS, self::SECURITY_NONE];
	}

	public function getDsn():string {
		$scheme = $this->security === self::SECURITY_TLS ? "smtps" : "smtp";
		$host = str_contains($this->host, ":") ? "[$this->host]" : $this->host;
		$credentials = $this->username === null
			? ""
			: rawurlencode($this->username) . ":" . rawurlencode($this->password ?? "") . "@";
		$options = match($this->security) {
			self::SECURITY_STARTTLS => "?require_tls=true",
			self::SECURITY_NONE => "?auto_tls=false",
			default => "",
		};

		return "$scheme://$credentials$host:$this->port$options";
	}

	public function getFrom():Address {
		return new Address($this->fromAddress, $this->fromName ?? "");
	}
}
