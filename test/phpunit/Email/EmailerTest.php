<?php
namespace HexForm\Test\Email;

use HexForm\Email\Emailer;
use HexForm\Email\SmtpConfiguration;
use HexForm\Email\SmtpMailerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mailer\Exception\TransportException;

class EmailerTest extends TestCase {
	private Emailer $sut;

	protected function setUp():void {
		$this->sut = new Emailer(new Mailer(new NullTransport()));
	}

	public function testSendConfirmation_rendersMarkdownAsTextAndHtml():void {
		$email = $this->sut->createEmail(
			"team@example.com",
			"confirm-forwarder",
			["confirmationCode" => "12345"],
		);

		self::assertSame("Confirm your HexForm forwarding address", $email->getSubject());
		self::assertStringContainsString("12345", $email->getTextBody());
		self::assertStringContainsString("<h2>12345</h2>", $email->getHtmlBody());
	}

	public function testSendSubmission_containsAllKeyValuePairs():void {
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

	public function testSendSubmission_usesEndpointSmtpAndSender():void {
		$customMailer = new class implements MailerInterface {
			public ?RawMessage $message = null;

			public function send(RawMessage $message, ?Envelope $envelope = null):void {
				$this->message = $message;
			}
		};
		$smtp = new SmtpConfiguration(
			"endpoint-1", "smtp.example.com", 587, "user", "secret",
			"starttls", "forms@example.com", "Website forms",
		);
		$factory = self::createMock(SmtpMailerFactory::class);
		$factory->expects(self::once())->method("create")->with($smtp)->willReturn($customMailer);
		$sut = new Emailer(new Mailer(new NullTransport()), "data/email", $factory);

		self::assertTrue($sut->sendSubmission(
			"team@example.com",
			"Contact form",
			["message" => "Hello"],
			$smtp,
		));
		self::assertSame("forms@example.com", $customMailer->message->getFrom()[0]->getAddress());
		self::assertSame("Website forms", $customMailer->message->getFrom()[0]->getName());
	}

	public function testSendSubmissionWithStatus_reportsSmtpAcceptance():void {
		$result = $this->sut->sendSubmissionWithStatus(
			"team@example.com",
			"Contact form",
			["message" => "Hello"],
		);

		self::assertTrue($result->successful);
		self::assertSame("Accepted by HexForm SMTP server", $result->status);
	}

	public function testSendSubmissionWithStatus_reportsSmtpFailure():void {
		$mailer = self::createMock(MailerInterface::class);
		$mailer->method("send")
			->willThrowException(new TransportException("Mailbox unavailable"));
		$sut = new Emailer($mailer);

		$result = $sut->sendSubmissionWithStatus(
			"team@example.com",
			"Contact form",
			["message" => "Hello"],
		);

		self::assertFalse($result->successful);
		self::assertSame("SMTP submission failed: Mailbox unavailable", $result->status);
	}
}
