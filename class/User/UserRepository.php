<?php
namespace HexForm\User;

use Authwave\User as AuthUser;
use Gt\Database\Query\QueryCollection;
use Gt\Database\Result\Row;

class UserRepository {
	public function __construct(
		private QueryCollection $db,
	) {}

	public function fromAuthwaveUser(AuthUser $authUser):User {
		if($user = $this->getById($authUser->id)) {
			return $user;
		}

		$this->db->insert("create", [
			"id" => $authUser->id,
			"email" => $authUser->email,
		]);

		return $this->getById($authUser->id);
	}

	public function getById(string $id):?User {
		return $this->rowToUser($this->db->fetch("getById", $id));
	}

	private function rowToUser(?Row $row):?User {
		if(!$row) {
			return null;
		}

		return new User(
			$row->getString("id"),
			$row->getString("email"),
			$row->getString("subscriptionPlan"),
		);
	}
}
