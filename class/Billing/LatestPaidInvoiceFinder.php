<?php
namespace HexForm\Billing;

use Stripe\Invoice;

class LatestPaidInvoiceFinder {
	/** @param iterable<Invoice> $invoices */
	public function find(iterable $invoices):?Invoice {
		return $this->findMany($invoices, 1)[0] ?? null;
	}

	/** @param iterable<Invoice> $invoices
	 * @return list<Invoice>
	 */
	public function findMany(iterable $invoices, int $limit):array {
		$found = [];
		foreach($invoices as $invoice) {
			if($invoice->amount_paid > 0) {
				$found[] = $invoice;
				if(count($found) === $limit) {
					break;
				}
			}
		}
		return $found;
	}
}
