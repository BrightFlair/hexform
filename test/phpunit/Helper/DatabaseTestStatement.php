<?php
namespace HexForm\Test\Helper;

use PDO;
use PDOStatement;

class DatabaseTestStatement extends PDOStatement {
	private int $index = 0;

	/** @param array<int, array<string, string>> $rows */
	public function __construct(private array $rows) {}

	public function execute(?array $params = null):bool {
		$this->index = 0;
		return true;
	}

	public function fetch(
		int $mode = PDO::FETCH_DEFAULT,
		int $cursorOrientation = PDO::FETCH_ORI_NEXT,
		int $cursorOffset = 0,
	):mixed {
		return $this->rows[$this->index++] ?? false;
	}
}
