<?php
namespace HexForm\Test;

use GT\Http\Response;
use GT\Http\Uri;
use Gt\Input\Input;
use HexForm\Audit\AuditLog;
use HexForm\Billing\BillingService;
use HexForm\UI\Flash;
use HexForm\User\User;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . "/../../page/app/account.php";

class AccountPageTest extends TestCase {
	public function testSuccessfulCancellationRedirectIsNotReportedAsFailure():void {
		$user = new User("user-1", "person@example.com", "developer");
		$billing = self::createMock(BillingService::class);
		$billing->expects(self::once())->method("selectFreePlan")
			->with($user)->willReturn(true);
		$flash = self::createMock(Flash::class);
		$flash->expects(self::once())->method("set")->with(
			"Your subscription will change to Free after your current paid period ends.",
		);
		$audit = $this->successfulAudit($user, "free");

		$response = $this->redirectingResponse();
		$this->expectException(RedirectSignal::class);

		\do_select_plan(
			$user,
			$billing,
			$this->input("free"),
			$response,
			$flash,
			new Uri("https://hexform.test/app/account/"),
			$audit,
		);
	}

	public function testSuccessfulUpgradeRedirectIsNotReportedAsFailure():void {
		$user = new User("user-1", "person@example.com", "developer");
		$billing = self::createMock(BillingService::class);
		$billing->expects(self::once())->method("selectPaidPlan")
			->with(
				$user,
				"enterprise",
				"https://hexform.test/app/account/?checkout=success&session_id={CHECKOUT_SESSION_ID}",
				"https://hexform.test/app/account/?checkout=cancelled&signup=enterprise",
			)->willReturn(null);
		$flash = self::createMock(Flash::class);
		$flash->expects(self::once())->method("set")
			->with("Your subscription plan is now Enterprise.");
		$audit = $this->successfulAudit($user, "enterprise");

		$response = $this->redirectingResponse();
		$this->expectException(RedirectSignal::class);

		\do_select_plan(
			$user,
			$billing,
			$this->input("enterprise"),
			$response,
			$flash,
			new Uri("https://hexform.test/app/account/"),
			$audit,
		);
	}

	private function input(string $plan):Input {
		$input = self::createStub(Input::class);
		$input->method("getString")->with("subscriptionPlan")->willReturn($plan);
		return $input;
	}

	private function redirectingResponse():Response {
		$response = new Response();
		$response->setExitCallback(static function():never {
			throw new RedirectSignal();
		});
		return $response;
	}

	private function successfulAudit(User $user, string $plan):AuditLog {
		$audit = self::createMock(AuditLog::class);
		$audit->expects(self::once())->method("record")->with(
			$user->id,
			null,
			"subscription",
			$user->id,
			"change-plan",
			"succeeded",
			["plan" => $plan],
		);
		return $audit;
	}
}

class RedirectSignal extends RuntimeException {}
