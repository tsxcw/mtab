<?php declare(strict_types=1);

/**
 * MySQL database dump loader.
 */
class MysqlImporter
{
	/** @var (\Closure(int $count, ?float $percent): void)|null */
	public ?Closure $onProgress = null;


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
	 * Loads dump from the file.
	 */
	public function load(string $file): int
	{
		$handle = substr(strtolower($file), -3) === '.gz' ? gzopen($file, 'rb') : fopen($file, 'rb');
		if (!$handle) {
			throw new Exception("ERROR: Cannot open file '$file'.");
		}
		return $this->read($handle);
	}


	/**
	 * Reads dump from logical file.
	 * @param  resource
	 */
	public function read($handle): int
	{
		if (!is_resource($handle) || get_resource_type($handle) !== 'stream') {
			throw new Exception('Argument must be stream resource.');
		}

		$stat = fstat($handle);

		$sql = '';
		$delimiter = ';';
		$count = $size = 0;

		while (!feof($handle)) {
			$s = fgets($handle);
			if ($s === false) {
				break;
			}
			$size += strlen($s);
			if (strtoupper(substr($s, 0, 10)) === 'DELIMITER ') {
				$delimiter = trim(substr($s, 10));

			} elseif (substr($ts = rtrim($s), -strlen($delimiter)) === $delimiter) {
				$sql .= substr($ts, 0, -strlen($delimiter));
				if (!$this->connection->query($sql)) {
					throw new Exception($this->connection->error . ': ' . $sql);
				}
				$sql = '';
				$count++;
				if ($this->onProgress) {
					($this->onProgress)($count, isset($stat['size']) ? $size * 100 / $stat['size'] : null);
				}

			} else {
				$sql .= $s;
			}
		}

		if (rtrim($sql) !== '') {
			$count++;
			if (!$this->connection->query($sql)) {
				throw new Exception($this->connection->error . ': ' . $sql);
			}
			if ($this->onProgress) {
				($this->onProgress)($count, isset($stat['size']) ? 100 : null);
			}
		}

		return $count;
	}
}
