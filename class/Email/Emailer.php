<?php
namespace HexForm\Email;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use HexForm\Forwarding\ForwardingResult;

readonly class Emailer {
	private const string FROM_ADDRESS = "forms@hexform.io";
	private const string FROM_NAME = "HexForm";

	public function __construct(
		private MailerInterface $mailer,
		private string $templateDirectory = "data/email",
		private SmtpMailerFactory $smtpMailerFactory = new SmtpMailerFactory(),
	) {}

	public function sendConfirmation(string $email, string $code):bool {
		return $this->send($email, "confirm-forwarder", ["confirmationCode" => $code]);
	}

	/** @param array<string, mixed> $submission */
	public function sendSubmission(
		string $email,
		string $endpointTitle,
		array $submission,
		?SmtpConfiguration $smtp = null,
	):bool {
		return $this->sendSubmissionWithStatus(
			$email,
			$endpointTitle,
			$submission,
			$smtp,
		)->successful;
	}

	/** @param array<string, mixed> $submission */
	public function sendSubmissionWithStatus(
		string $email,
		string $endpointTitle,
		array $submission,
		?SmtpConfiguration $smtp = null,
	):ForwardingResult {
		$rows = [];
		foreach($submission as $key => $value) {
			array_push($rows,
				"| " . $this->escapeTableValue($key)
				. " | " . $this->escapeTableValue($this->stringify($value)) . " |"
			);
		}

		return $this->sendWithStatus($email, "submission", [
			"endpointSubject" => $endpointTitle,
			"endpointTitle" => $this->escapeMarkdown($endpointTitle),
			"submissionRows" => implode("\n", $rows),
		], $smtp);
	}

	/** @param array<string, string> $variables */
	public function createEmail(
		string $emailAddress,
		string $templateName,
		array $variables,
		?Address $from = null,
	):Email {
		$path = "$this->templateDirectory/$templateName.md";
		$template = file_get_contents($path);
		if($template === false) {
			throw new EmailTemplateNotFound($templateName);
		}
		[$subjectLine, $markdown] = array_pad(explode("\n", trim($template), 2), 2, "");
		foreach($variables as $name => $value) {
			$placeholder = "{{" . $name . "}}";
			$subjectLine = str_replace($placeholder, strip_tags($value), $subjectLine);
			$markdown = str_replace($placeholder, $value, $markdown);
		}
		$subjectLine = preg_replace('/{{[^}]+}}/', '', $subjectLine) ?? "";
		$markdown = preg_replace('/{{[^}]+}}/', '', $markdown) ?? "";
		$subject = trim(ltrim($subjectLine, "# "));
		$markdown = ltrim($markdown);
		$html = (string)(new GithubFlavoredMarkdownConverter())->convert($markdown);

		$email = new Email();
		return $email
			->from($from ?? new Address(self::FROM_ADDRESS, self::FROM_NAME))
			->to($emailAddress)
			->subject($subject)
			->text($markdown)
			->html($html);
	}

	/** @param array<string, string> $variables */
	private function send(
		string $emailAddress,
		string $templateName,
		array $variables,
		?SmtpConfiguration $smtp = null,
	):bool {
		return $this->sendWithStatus(
			$emailAddress,
			$templateName,
			$variables,
			$smtp,
		)->successful;
	}

	/** @param array<string, string> $variables */
	private function sendWithStatus(
		string $emailAddress,
		string $templateName,
		array $variables,
		?SmtpConfiguration $smtp = null,
	):ForwardingResult {
		try {
			$mailer = $smtp ? $this->smtpMailerFactory->create($smtp) : $this->mailer;
			$mailer->send($this->createEmail(
				$emailAddress,
				$templateName,
				$variables,
				$smtp?->getFrom(),
			));
			return new ForwardingResult(
				true,
				$smtp ? "Accepted by custom SMTP server" : "Accepted by HexForm SMTP server",
			);
		}
		catch(TransportExceptionInterface $exception) {
			return new ForwardingResult(
				false,
				"SMTP submission failed: "
					. mb_strimwidth($exception->getMessage(), 0, 450, "…"),
			);
		}
	}

	private function stringify(mixed $value):string {
		return is_scalar($value) || $value === null
			? (string)$value
			: (json_encode($value, JSON_UNESCAPED_SLASHES) ?: "");
	}

	private function escapeTableValue(string $value):string {
		$value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
		return str_replace(["|", "\r", "\n"], ["\\|", "", "<br>"], $value);
	}

	private function escapeMarkdown(string $value):string {
		$safeValue = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
		return addcslashes($safeValue, "\\`*_{}[]()#+-.!|");
	}
}
