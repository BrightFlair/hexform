<?php
namespace HexForm\Email;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

class SmtpMailerFactory {
	/** @SuppressWarnings("PHPMD.StaticAccess") */
	public function create(SmtpConfiguration $configuration):MailerInterface {
		return new Mailer(Transport::fromDsn($configuration->getDsn()));
	}
}
