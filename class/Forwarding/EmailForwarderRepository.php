<?php
namespace HexForm\Forwarding;

use DateTimeInterface;
use GT\Database\Query\QueryCollection;
use GT\Database\Result\Row;
use HexForm\Endpoint\Endpoint;
use HexForm\User\User;

class EmailForwarderRepository {
	public function __construct(private QueryCollection $db) {}

	public function create(
		string $id,
		Endpoint $endpoint,
		string $email,
		string $confirmationCode,
		DateTimeInterface $confirmationCreatedAt,
		?DateTimeInterface $confirmedAt = null,
	):void {
		$this->db->insert("create", [
			"id" => $id,
			"endpointId" => $endpoint->id,
			"email" => $email,
			"confirmationCode" => $confirmationCode,
			"confirmationCreatedAt" => $confirmationCreatedAt->format("Y-m-d H:i:s"),
			"confirmedAt" => $confirmedAt?->format("Y-m-d H:i:s"),
		]);
	}

	/** @return array<EmailForwarder> */
	public function getForEndpointByUser(Endpoint $endpoint, User $user):array {
		return $this->mapRows($this->db->fetchAll("getForEndpointByUser", [
			"endpointId" => $endpoint->id,
			"userId" => $user->id,
		]));
	}

	/** @return array<EmailForwarder> */
	public function getConfirmedForEndpoint(Endpoint $endpoint):array {
		return $this->mapRows($this->db->fetchAll("getConfirmedForEndpoint", $endpoint->id));
	}

	public function getByIdForUser(string $id, User $user):?EmailForwarder {
		return $this->rowToForwarder($this->db->fetch("getByIdForUser", [
			"id" => $id,
			"userId" => $user->id,
		]));
	}

	public function confirm(EmailForwarder $forwarder, string $confirmationCode):bool {
		if($forwarder->isConfirmed() || !hash_equals($forwarder->confirmationCode, $confirmationCode)) {
			return false;
		}
		$this->db->update("confirm", [
			"id" => $forwarder->id,
			"confirmationCode" => $confirmationCode,
		]);
		return true;
	}

	public function delete(EmailForwarder $forwarder):void {
		$this->db->delete("delete", $forwarder->id);
	}

	public function resend(
		EmailForwarder $forwarder,
		string $confirmationCode,
		DateTimeInterface $confirmationCreatedAt,
	):bool {
		if(!$forwarder->canResend($confirmationCreatedAt)) {
			return false;
		}
		$this->db->update("resend", [
			"id" => $forwarder->id,
			"confirmationCode" => $confirmationCode,
			"confirmationCreatedAt" => $confirmationCreatedAt->format("Y-m-d H:i:s"),
		]);
		return true;
	}

	/**
	 * @param iterable<Row> $rows
	 * @return array<EmailForwarder>
	 */
	private function mapRows(iterable $rows):array {
		$list = [];
		foreach($rows as $row) {
			$list[] = $this->rowToForwarder($row);
		}
		return $list;
	}

	private function rowToForwarder(?Row $row):?EmailForwarder {
		if(!$row) {
			return null;
		}
		return new EmailForwarder(
			$row->getString("id"),
			$row->getString("endpointId"),
			$row->getString("email"),
			$row->getDateTime("confirmedAt"),
			$row->getString("confirmationCode"),
			$row->getDateTime("confirmationCreatedAt"),
		);
	}
}
