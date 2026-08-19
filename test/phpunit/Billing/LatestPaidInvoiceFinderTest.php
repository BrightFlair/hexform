<?php
namespace HexForm\Test\Billing;

use HexForm\Billing\LatestPaidInvoiceFinder;
use PHPUnit\Framework\TestCase;
use Stripe\Invoice;

class LatestPaidInvoiceFinderTest extends TestCase {
	public function testFindMany_ignoresZeroValuePlanChangeInvoices():void {
		$planChange = Invoice::constructFrom(["id" => "in_plan_change", "amount_paid" => 0]);
		$payment = Invoice::constructFrom(["id" => "in_payment", "amount_paid" => 2000]);

		$result = (new LatestPaidInvoiceFinder())->find([$planChange, $payment]);

		self::assertSame($payment, $result);
	}

	public function testFind_returnsNullWhenNoMoneyWasCollected():void {
		$invoice = Invoice::constructFrom(["id" => "in_free", "amount_paid" => 0]);

		$result = (new LatestPaidInvoiceFinder())->find([$invoice]);

		self::assertNull($result);
	}

	public function testFindMany_returnsActualPaymentsInInvoiceOrder():void {
		$latest = Invoice::constructFrom(["id" => "in_latest", "amount_paid" => 2000]);
		$bookkeeping = Invoice::constructFrom(["id" => "in_zero", "amount_paid" => 0]);
		$original = Invoice::constructFrom(["id" => "in_original", "amount_paid" => 500]);

		$result = (new LatestPaidInvoiceFinder())->findMany(
			[$latest, $bookkeeping, $original],
			2,
		);

		self::assertSame([$latest, $original], $result);
	}
}
