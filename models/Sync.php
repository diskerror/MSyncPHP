<?php

namespace Model;

use Exception;
use Laminas\Json\Json;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;

/**
 * Msync class.
 */
class Sync
{
	/**
	 * This algorithm seems to be the best trade-off between size (uniqueness),
	 * speed, and cross-platform availability.
	 */
	public const HASH_ALGO = 'tiger192,3';

	/**
	 * Settings from command line and config file.
	 */
	protected Opts $opts;

	/**
	 * Functional classes.
	 */
	protected SFTP $sftp;

	public function __construct(Opts $opts)
	{
		$this->opts = $opts;

		if ($this->opts->localPath !== getcwd()) {
			chdir($this->opts->localPath);
		}

		$key = PublicKeyLoader::load(file_get_contents($this->opts->sshKeyPath));

		$this->sftp = new SFTP($this->opts->host);
		if (!$this->sftp->login($this->opts->user, $key)) {
			throw new Exception('Login failed');
		}

		$this->sftp->enableDatePreservation();
		$this->sftp->chdir($this->opts->remotePath);
	}

	public static function hdToRegex(...$strs): string
	{
		foreach ($strs as &$s) {
			$s = trim($s);
		}

		return '@^(?:' . strtr(implode('|', $strs), "\n", '|') . ')$@';
	}

	public function getRemoteList(): array
	{
		$cmd = php_strip_whitespace(__DIR__ . '/../find.php');
		$cmd = substr($cmd, 6);    //	removes "<?php\n"
		$cmd = str_replace(
			['$path', '$plength', '$regexIgnore', '$regexNoHash', '$hashName'],
			[
				'"' . $this->opts->remotePath . '"',
				strlen($this->opts->remotePath),
				'"' . self::hdToRegex($this->opts->IGNORE_REGEX) . '"',
				'"' . self::hdToRegex($this->opts->NO_PUSH_REGEX) . '"',
				'"' . self::HASH_ALGO . '"',
			],
			$cmd
		);

		$cmd .= ' echo json_encode($rtval), "\\n";';

		$response = $this->sftp->exec("echo '$cmd' | php -a");

		//	Remove text before first '[' or '{'.
		$response= preg_replace('/^[^[{]*(.+)$/sAD', '$1', $response);

		return Json::decode($response, JSON_OBJECT_AS_ARRAY);
	}

	public function getDevList(): array
	{
		$path        = $this->opts->localPath;
		$plength     = strlen($this->opts->localPath);
		$regexIgnore = self::hdToRegex($this->opts->IGNORE_REGEX);
		$regexNoHash = self::hdToRegex($this->opts->NO_PUSH_REGEX);
		$hashName    = self::HASH_ALGO;

		require __DIR__ . '/../find.php';

		return $rtval;
	}

	public function pullFiles(array $files): void
	{
		$report = new Report($this->opts->verbose);
		$i      = 1;
		$of_ct  = ' of ' . count($files);

		foreach ($files as $file) {
			if ($file['ftype'] === 'f') {
				$fname = $file['fname'];
				$dname = dirname($fname);

				if (!file_exists($dname)) {
					mkdir($dname, 0755, true);
				}

				/**
				 * Copy the file to here when:
				 *        it doesn't exist locally
				 *        it was not hashed but sizes differ or mod times differ
				 *        it was hashed and the hashes differ
				 */
				if (!file_exists($fname) ||
					($file['hashval'] === '' ?
						($file['sizeb'] != filesize($fname) || $file['modts'] != filemtime($fname)) :
						$file['hashval'] !== hash_file(self::HASH_ALGO, $fname)
					)
				) {
					$report->status($i . $of_ct);
					$this->sftp->get($fname, $fname);
				}

				++$i;
			}
		}

		foreach ($files as $file) {
			if ($file['ftype'] === 'd') {
				$report->status($i++ . $of_ct);

				$dname = $file['fname'];

				if (!file_exists($dname)) {
					mkdir($dname, 0755, true);
				}

				touch($dname, $file['modts']);
			}
		}
		
		$report->out('');
	}
}