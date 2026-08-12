<?php
namespace HexForm\Test\Billing;

use HexForm\Billing\LatestPaidInvoiceFinder;
use PHPUnit\Framework\TestCase;
use Stripe\Invoice;

class LatestPaidInvoiceFinderTest extends TestCase {
	public function testItIgnoresZeroValuePlanChangeInvoices():void {
		$planChange = Invoice::constructFrom(["id" => "in_plan_change", "amount_paid" => 0]);
		$payment = Invoice::constructFrom(["id" => "in_payment", "amount_paid" => 2000]);

		$result = (new LatestPaidInvoiceFinder())->find([$planChange, $payment]);

		self::assertSame($payment, $result);
	}

	public function testItReturnsNullWhenNoMoneyWasCollected():void {
		$invoice = Invoice::constructFrom(["id" => "in_free", "amount_paid" => 0]);

		$result = (new LatestPaidInvoiceFinder())->find([$invoice]);

		self::assertNull($result);
	}
}
