<?php
namespace HexForm\UI;

use GT\Session\SessionStoreInterface;

class Flash {
	private const KEY = "message";

	public function __construct(private SessionStoreInterface $session) {}

	public function set(string $message):void {
		$this->session->set(self::KEY, $message);
	}

	public function consume():?string {
		$message = $this->session->getString(self::KEY);
		$this->session->remove(self::KEY);
		return $message;
	}
}
