<?php
namespace HexForm\Test\Helper;

use GT\Database\Result\ResultSet;
use GT\Database\Result\Row;

class DatabaseTestHelper {
	public static function resultSet(Row ...$rows):ResultSet {
		return new ResultSet(new DatabaseTestStatement(
			array_map(fn(Row $row) => $row->asArray(), $rows),
		));
	}
}
