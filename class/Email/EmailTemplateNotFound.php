<?php
namespace HexForm\Email;

use RuntimeException;

class EmailTemplateNotFound extends RuntimeException {
	public function __construct(string $templateName) {
		parent::__construct("Email template not found: $templateName");
	}
}
