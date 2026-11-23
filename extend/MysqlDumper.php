<?php declare(strict_types=1);

/**
 * MySQL database dump.
 *
 * @author     David Grudl (http://davidgrudl.com)
 * @copyright  Copyright (c) 2008 David Grudl
 * @license    New BSD License
 */
class MysqlDumper
{
	public const NONE = 0;
	public const DROP = 1;
	public const CREATE = 2;
	public const DATA = 4;
	public const TRIGGERS = 8;
	public const ROUTINES = 16;
	public const ALL = 31; // DROP | CREATE | DATA | TRIGGERS | ROUTINES

	private const MAX_SQL_SIZE = 1e6;

	public array $tables = [
		'*' => self::ALL,
	];


	/** @var mysqli 数据库连接 */
	private $connection;

	/**
	 * Connects to database.
	 * @param mysqli $connection 数据库连接对象
	 * @param string $charset   字符集
	 */
	public function __construct(mysqli $connection, string $charset = 'utf8mb4')
	{
		$this->connection = $connection;
		if ($connection->connect_errno) {
			throw new Exception($connection->connect_error);

		} elseif (!$connection->set_charset($charset)) {
			throw new Exception($connection->error);
		}
	}


	/**
	 * Saves dump to the file.
	 */
	public function save(string $file): void
	{
		$handle = substr(strtolower($file), -3) === '.gz' ? gzopen($file, 'wb') : fopen($file, 'wb');
		if (!$handle) {
			throw new Exception("ERROR: Cannot write file '$file'.");
		}
		$this->write($handle);
	}


	/**
	 * Writes dump to logical file.
	 * @param  resource
	 */
	public function write($handle = null): void
	{
		if ($handle === null) {
			$handle = fopen('php://output', 'wb');
		} elseif (!is_resource($handle) || get_resource_type($handle) !== 'stream') {
			throw new Exception('Argument must be stream resource.');
		}

		$tables = $views = [];

		$res = $this->connection->query('SHOW FULL TABLES');
		while ($row = $res->fetch_row()) {
			if ($row[1] === 'VIEW') {
				$views[] = $row[0];
			} else {
				$tables[] = $row[0];
			}
		}
		$res->close();

		$tables = array_merge($tables, $views); // views must be last

		$this->connection->query('LOCK TABLES `' . implode('` READ, `', $tables) . '` READ');

		$db = $this->connection->query('SELECT DATABASE()')->fetch_row();
		fwrite(
			$handle,
			'-- Created at ' . date('j.n.Y G:i') . " using David Grudl MySQL Dump Utility\n"
			. (isset($_SERVER['HTTP_HOST']) ? "-- Host: $_SERVER[HTTP_HOST]\n" : '')
			. '-- MySQL Server: ' . $this->connection->server_info . "\n"
			. '-- Database: ' . $db[0] . "\n"
			. "\n"
			. "SET NAMES utf8mb4;\n"
			. "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n"
			. "SET FOREIGN_KEY_CHECKS=0;\n"
			. "SET UNIQUE_CHECKS=0;\n"
			. "SET AUTOCOMMIT=0;\n",
		);

		foreach ($tables as $table) {
			$this->dumpTable($handle, $table);
		}

		if (($this->tables['*'] ?? 0) & self::ROUTINES) {
			$routines = [];
			foreach (['FUNCTION', 'PROCEDURE'] as $type) {
				$res = $this->connection->query("SHOW $type STATUS WHERE Db = DATABASE()");
				while ($row = $res->fetch_assoc()) {
					$routines[] = [$type, $row['Name']];
				}
				$res->close();
			}

			$res = $this->connection->query('SHOW EVENTS WHERE Db = DATABASE()');
			while ($row = $res->fetch_assoc()) {
				$routines[] = ['EVENT', $row['Name']];
			}
			$res->close();

			if ($routines) {
				fwrite($handle, "-- --------------------------------------------------------\n\n");
				fwrite($handle, "DELIMITER ;;\n\n");
				foreach ($routines as [$type, $name]) {
					$this->writeRoutine($handle, $type, $name);
				}
				fwrite($handle, "DELIMITER ;\n\n");
			}
		}

		fwrite($handle, "COMMIT;\n");
		fwrite($handle, "-- THE END\n");

		$this->connection->query('UNLOCK TABLES');
	}


	/**
	 * Dumps table to logical file.
	 * @param  resource
	 */
	public function dumpTable($handle, string $table): void
	{
		$mode = $this->tables[$table] ?? $this->tables['*'];
		if ($mode === self::NONE) {
			return;
		}

		$delTable = $this->delimite($table);
		$res = $this->connection->query("SHOW CREATE TABLE $delTable");
		$row = $res->fetch_assoc();
		$res->close();

		fwrite($handle, "-- --------------------------------------------------------\n\n");

		$view = isset($row['Create View']);

		if ($mode & self::DROP) {
			fwrite($handle, 'DROP ' . ($view ? 'VIEW' : 'TABLE') . " IF EXISTS $delTable;\n\n");
		}

		if ($mode & self::CREATE) {
			fwrite($handle, $row[$view ? 'Create View' : 'Create Table'] . ";\n\n");
		}

		if (!$view && ($mode & self::DATA)) {
			fwrite($handle, 'ALTER ' . ($view ? 'VIEW' : 'TABLE') . ' ' . $delTable . " DISABLE KEYS;\n\n");
			$numeric = [];
			$res = $this->connection->query("SHOW COLUMNS FROM $delTable");
			$cols = [];
			while ($row = $res->fetch_assoc()) {
				$col = $row['Field'];
				$cols[] = $this->delimite($col);
				$numeric[$col] = (bool) preg_match('#^[^(]*(BYTE|COUNTER|SERIAL|INT|LONG$|CURRENCY|REAL|MONEY|FLOAT|DOUBLE|DECIMAL|NUMERIC|NUMBER)#i', $row['Type']);
			}
			$cols = '(' . implode(', ', $cols) . ')';
			$res->close();


			$size = 0;
			$res = $this->connection->query("SELECT * FROM $delTable", MYSQLI_USE_RESULT);
			while ($row = $res->fetch_assoc()) {
				$s = '(';
				foreach ($row as $key => $value) {
					if ($value === null) {
						$s .= "NULL,\t";
					} elseif ($numeric[$key]) {
						$s .= $value . ",\t";
					} else {
						$s .= "'" . $this->connection->real_escape_string($value) . "',\t";
					}
				}

				if ($size == 0) {
					$s = "INSERT INTO $delTable $cols VALUES\n$s";
				} else {
					$s = ",\n$s";
				}

				$len = strlen($s) - 1;
				$s[$len - 1] = ')';
				fwrite($handle, $s, $len);

				$size += $len;
				if ($size > self::MAX_SQL_SIZE) {
					fwrite($handle, ";\n");
					$size = 0;
				}
			}

			$res->close();
			if ($size) {
				fwrite($handle, ";\n");
			}
			fwrite($handle, 'ALTER ' . ($view ? 'VIEW' : 'TABLE') . ' ' . $delTable . " ENABLE KEYS;\n\n");
			fwrite($handle, "\n");
		}

		if ($mode & self::TRIGGERS) {
			$res = $this->connection->query("SHOW TRIGGERS WHERE `Table` = '" . $this->connection->real_escape_string($table) . "'");
			$triggers = [];
			while ($row = $res->fetch_assoc()) {
				$triggers[] = $row['Trigger'];
			}
			$res->close();

			if ($triggers) {
				fwrite($handle, "DELIMITER ;;\n\n");
				foreach ($triggers as $trigger) {
					$delTrigger = $this->delimite($trigger);
					if ($mode & self::DROP) {
						fwrite($handle, "DROP TRIGGER IF EXISTS $delTrigger;;\n\n");
					}

					// SHOW TRIGGERS returns a normalized body with broken escaping, only SHOW CREATE gives the original
					$res = $this->connection->query("SHOW CREATE TRIGGER $delTrigger");
					$row = $res->fetch_assoc();
					$res->close();
					fwrite($handle, $this->stripDefiner($row['SQL Original Statement']) . ";;\n\n");
				}
				fwrite($handle, "DELIMITER ;\n\n");
			}
		}

		fwrite($handle, "\n");
	}


	/**
	 * Dumps stored function to logical file.
	 * @param  resource
	 */
	public function dumpFunction($handle, string $name): void
	{
		fwrite($handle, "DELIMITER ;;\n\n");
		$this->writeRoutine($handle, 'FUNCTION', $name);
		fwrite($handle, "DELIMITER ;\n\n");
	}


	/**
	 * Dumps stored procedure to logical file.
	 * @param  resource
	 */
	public function dumpProcedure($handle, string $name): void
	{
		fwrite($handle, "DELIMITER ;;\n\n");
		$this->writeRoutine($handle, 'PROCEDURE', $name);
		fwrite($handle, "DELIMITER ;\n\n");
	}


	/**
	 * Dumps scheduled event to logical file.
	 * @param  resource
	 */
	public function dumpEvent($handle, string $name): void
	{
		fwrite($handle, "DELIMITER ;;\n\n");
		$this->writeRoutine($handle, 'EVENT', $name);
		fwrite($handle, "DELIMITER ;\n\n");
	}


	private function writeRoutine($handle, string $type, string $name): void
	{
		$mode = $this->tables['*'] ?? 0;
		$delName = $this->delimite($name);

		if ($mode & self::DROP) {
			fwrite($handle, "DROP $type IF EXISTS $delName;;\n\n");
		}

		if ($mode & self::CREATE) {
			$res = $this->connection->query("SHOW CREATE $type $delName");
			$row = $res->fetch_assoc();
			$res->close();
			fwrite($handle, $this->stripDefiner($row['Create ' . ucfirst(strtolower($type))]) . ";;\n\n");
		}
	}


	/**
	 * Removes the DEFINER clause so the dump imports under any user.
	 */
	private function stripDefiner(string $sql): string
	{
		$identifier = '(?:`(?:[^`]|``)*`|\S+?)';
		return preg_replace('/^CREATE\s+DEFINER\s*=\s*' . $identifier . '@' . $identifier . '\s+/', 'CREATE ', $sql, 1);
	}


	private function delimite(string $s): string
	{
		return '`' . str_replace('`', '``', $s) . '`';
	}
}
