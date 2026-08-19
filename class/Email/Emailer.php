<?php
namespace HexForm\Email;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

readonly class Emailer {
	private const string FROM_ADDRESS = "forms@hexform.io";
	private const string FROM_NAME = "HexForm";

	public function __construct(
		private Mailer $mailer,
		private string $templateDirectory = "data/email",
	) {}

	public function sendConfirmation(string $email, string $code):bool {
		return $this->send($email, "confirm-forwarder", ["confirmationCode" => $code]);
	}

	/** @param array<string, mixed> $submission */
	public function sendSubmission(string $email, string $endpointTitle, array $submission):bool {
		$rows = [];
		foreach($submission as $key => $value) {
			array_push($rows,
				"| " . $this->escapeTableValue($key)
				. " | " . $this->escapeTableValue($this->stringify($value)) . " |"
			);
		}

		return $this->send($email, "submission", [
			"endpointSubject" => $endpointTitle,
			"endpointTitle" => $this->escapeMarkdown($endpointTitle),
			"submissionRows" => implode("\n", $rows),
		]);
	}

	/** @param array<string, string> $variables */
	public function createEmail(string $emailAddress, string $templateName, array $variables):Email {
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
			->from(new Address(self::FROM_ADDRESS, self::FROM_NAME))
			->to($emailAddress)
			->subject($subject)
			->text($markdown)
			->html($html);
	}

	/** @param array<string, string> $variables */
	private function send(string $emailAddress, string $templateName, array $variables):bool {
		try {
			$this->mailer->send($this->createEmail($emailAddress, $templateName, $variables));
			return true;
		}
		catch(TransportExceptionInterface) {
			return false;
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
