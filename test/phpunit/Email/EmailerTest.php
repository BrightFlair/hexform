<?php
namespace HexForm\Test\Email;

use HexForm\Email\Emailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\NullTransport;

class EmailerTest extends TestCase {
	private Emailer $sut;

	protected function setUp():void {
		$this->sut = new Emailer(new Mailer(new NullTransport()));
	}

	public function testConfirmationEmailRendersMarkdownAsTextAndHtml():void {
		$email = $this->sut->createEmail(
			"team@example.com",
			"confirm-forwarder",
			["confirmationCode" => "12345"],
		);

		self::assertSame("Confirm your HexForm forwarding address", $email->getSubject());
		self::assertStringContainsString("12345", $email->getTextBody());
		self::assertStringContainsString("<h2>12345</h2>", $email->getHtmlBody());
	}

	public function testSubmissionTemplateContainsAllKeyValuePairs():void {
		self::assertTrue($this->sut->sendSubmission(
			"team@example.com",
			"Contact form",
			["email" => "sender@example.com", "message" => "Hello"],
		));
		$email = $this->sut->createEmail("team@example.com", "submission", [
			"endpointTitle" => "Contact form",
			"submissionRows" => "| email | sender@example.com |\n| message | Hello |",
		]);

		self::assertStringContainsString("sender@example.com", $email->getHtmlBody());
		self::assertStringContainsString("Hello", $email->getHtmlBody());
	}
}
