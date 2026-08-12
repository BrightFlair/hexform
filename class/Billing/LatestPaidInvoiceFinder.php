<?php
namespace HexForm\Billing;

use Stripe\Invoice;

class LatestPaidInvoiceFinder {
	/** @param iterable<Invoice> $invoices */
	public function find(iterable $invoices):?Invoice {
		foreach($invoices as $invoice) {
			if($invoice->amount_paid > 0) {
				return $invoice;
			}
		}

		return null;
	}
}
