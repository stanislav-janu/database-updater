<?php declare(strict_types=1);

namespace JCode;

use Nette\Utils\FileSystem;
use Nette\Database\DriverException;
use Nette\Database\Explorer;
use Nette\Utils\Finder;
use Nette\Utils\Strings;
use Nette\Utils\DateTime;
use Tracy\Debugger;


class DatabaseUpdater
{
	/** @var array<int|string, array<string, mixed>> */
	public array $index = [];

	const string DIRECTORY = '../SQL';

	const string FILE = 'index.dat';


	public function __construct(
		public string $wwwDir,
		public Explorer $database
	) {
	}


	private function loadIndex(): void
	{
		/** @var array<int|string, array<string, mixed>> $index */
		$index = unserialize(FileSystem::read($this->getFilePath()));
		$this->index = $index;
	}


	private function saveIndex(): void
	{
		FileSystem::write($this->getFilePath(), serialize($this->index));
	}


	final public function run(): void
	{
		if (Debugger::$productionMode !== true) {
			$files = [];
			foreach (Finder::findFiles('*.sql')
				->from($this->getDirectory()) as $item) {
				$files[(int) pathinfo((string) $item, PATHINFO_FILENAME)] = [
					'file' => pathinfo((string) $item, PATHINFO_BASENAME),
					'time' => DateTime::from((int) pathinfo((string) $item, PATHINFO_FILENAME)),
					'completed' => null,
				];
			}

			ksort($files);

			if (file_exists($this->getFilePath())) {
				$this->loadIndex();
				$n = 0;
				foreach ($files as $loaded_file_id => $loaded_file) {
					if (!isset($this->index[$loaded_file_id])) {
						$this->index[$loaded_file_id] = $loaded_file;
						$n++;
					}
				}

				if ($n > 0) {
					$this->saveIndex();
				}
			} else {
				$this->index = $files;
				$this->saveIndex();
			}

			$this->doForm();
			$this->doIt();
			$this->markAsUpdated();

			$new_files = [];
			foreach ($files as $loaded_file_id => $loaded_file) {
				if (isset($this->index[$loaded_file_id])) {
					if ($this->index[$loaded_file_id]['completed'] === null) {
						$new_files[] = $loaded_file;
					}
				} else {
					$new_files[] = $loaded_file;
				}
			}

			if ($new_files !== []) {
				$f = [];
				foreach ($new_files as $file) {
					$f[] = $file['file'];
				}

				$all_files = implode(',', $f);

				echo '<h1>New database updates:</h1>';
				echo '<a href="?database_updater_run=' . $all_files . '" style="font-size: 18px">UPDATE ALL</a>';
				echo '<ul>';
				foreach ($new_files as $file) {
					$fc = nl2br(Strings::truncate(Strings::unixNewLines(FileSystem::read($this->getDirectory() . '/' . $file['file'])), 512));
					echo <<< HTML
<li>
<h2>{$file['time']->format('j. F Y H:i')}</h2>
<p><a href="?database_updater_run={$file['file']}">UPDATE</a>, <a href="?database_updater_mark_as_updated={$file['file']}">Mark as updated</a></p>
<div style="height: 200px;overflow: auto;border: 1px solid lightcoral"><code>{$fc}</code></div>
</li>
HTML;
				}

				echo '</ul>';
				exit;
			}
		}
	}


	private function doIt(): void
	{
		if (!isset($_GET['database_updater_run']) || !is_string($_GET['database_updater_run'])) {
			return;
		}

		$hasError = false;

		$files = explode(',', $_GET['database_updater_run']);
		foreach ($files as $file) {
			$file_id = (int) $file;
			if (isset($this->index[$file_id]) && $this->index[$file_id]['completed'] === null) {
				try {
					$sql_file = $this->getDirectory() . '/' . $file;
					$sql = FileSystem::read($sql_file);
					$this->database->query($sql);
					$this->index[$file_id]['completed'] = DateTime::from('now');
					$this->saveIndex();
				} catch (DriverException $exception) {
					$hasError = true;
					echo '<p><strong>' . $file . '</strong> – ' . $exception->getMessage() . '</p>';
				}
			} else {
				$hasError = true;
				echo '<p><strong>File ' . $file . ' has been not found in index file or has been marked as completed.</strong></p>';
			}
		}

		if ($hasError) {
			exit;
		}

		// TODO: redirect to URL without our parameter.
	}


	private function markAsUpdated(): void
	{
		if (!isset($_GET['database_updater_mark_as_updated']) || !is_string($_GET['database_updater_mark_as_updated'])) {
			return;
		}

		$files = explode(',', $_GET['database_updater_mark_as_updated']);
		foreach ($files as $file) {
			$file_id = (int) $file;
			if (isset($this->index[$file_id]) && $this->index[$file_id]['completed'] === null) {
				$this->index[$file_id]['completed'] = DateTime::from('now');
				$this->saveIndex();
			}
		}
	}


	private function doForm(): void
	{
		if (!isset($_POST['database_updater_form_new'])) {
			return;
		}

		if (isset($_POST['database_updater_form_new_sql']) && is_string($_POST['database_updater_form_new_sql'])) {
			$sql = $_POST['database_updater_form_new_sql'];

			$now = DateTime::from('now');
			$now_timestamp = $now->getTimestamp();
			$file_name = $now_timestamp . '.sql';
			$this->index[$now_timestamp] = [
				'file' => $file_name,
				'time' => $now,
				'completed' => $now,
			];

			FileSystem::write($this->getDirectory() . '/' . $file_name, $sql);

			if (isset($_POST['database_updater_form_new_run'])) {
				$this->database->query($sql);
			}

			$this->saveIndex();

			if (isset($_POST['database_updater_form_back_link']) && is_string($_POST['database_updater_form_back_link'])) {
				header('Location: ' . $_POST['database_updater_form_back_link']);
				exit;
			}
		}
	}


	private function getDirectory(): string
	{
		return $this->wwwDir . DIRECTORY_SEPARATOR . self::DIRECTORY;
	}


	private function getFilePath(): string
	{
		return $this->wwwDir . DIRECTORY_SEPARATOR . self::DIRECTORY . DIRECTORY_SEPARATOR . self::FILE;
	}

}
