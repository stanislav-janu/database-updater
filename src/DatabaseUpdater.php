<?php declare(strict_types=1);

namespace JCode;

use Nette\Database\Context;
use Nette\Database\DriverException;
use Nette\Utils\Finder;
use Nette\Utils\Strings;
use Nette\Utils\DateTime;
use Tracy\Debugger;

// TODO: Ajaxify

/**
 * Class DatabaseUpdater
 * @package JCode
 */
class DatabaseUpdater
{
	/** @var Context */
	public $database;

	/** @var string */
	public $wwwDir;

	/** @var array */
	public $index;

	const DIRECTORY = '/../SQL';

	/**
	 * DatabaseUpdater constructor.
	 *
	 * @param string                  $wwwDir
	 * @param \Nette\Database\Context $database
	 */
	public function __construct(string $wwwDir, Context $database)
	{
		$this->wwwDir = $wwwDir;
		$this->database = $database;
	}

	final private function loadIndex()
	{
		$data_file = $this->getDirectory().'/index.dat';
		$f = fopen($data_file, 'r');
		$serialized = fread($f, filesize($data_file));
		fclose($f);
		$this->index = unserialize($serialized);
	}

	final private function saveIndex()
	{
		$data_file = $this->getDirectory().'/index.dat';
		$serialized = serialize($this->index);
		$f = fopen($data_file, 'w+');
		fwrite($f, $serialized);
		fclose($f);
	}

	final public function run()
	{
		if(!Debugger::$productionMode)
		{
			$files = [];
			foreach(Finder::findFiles('*.sql')->from($this->getDirectory()) as $item)
			{
				$files[(int) pathinfo((string) $item, PATHINFO_FILENAME)] = [
					'file' => pathinfo((string) $item, PATHINFO_BASENAME),
					'time' => DateTime::from((int) pathinfo((string) $item, PATHINFO_FILENAME)),
					'completed' => null,
				];
			}

			ksort($files);

			$data_file = $this->getDirectory().'/index.dat';
			if(file_exists($data_file))
			{
				$this->loadIndex();
				$n = 0;
				foreach($files as $loaded_file_id => $loaded_file)
				{
					if(!isset($this->index[$loaded_file_id]))
					{
						$this->index[$loaded_file_id] = $loaded_file;
						$n++;
					}
				}
				if($n > 0)
					$this->saveIndex();
			}
			else
			{
				$this->index = $files;
				$this->saveIndex();
			}

			$this->doForm();
			$this->doIt();
			$this->markAsUpdated();

			$completed_files = [];
			$new_files = [];
			foreach($files as $loaded_file_id => $loaded_file)
			{
				if(isset($this->index[$loaded_file_id]))
				{
					if($this->index[$loaded_file_id]['completed'])
						$completed_files[] = $this->index[$loaded_file_id];
					else
						$new_files[] = $loaded_file;
				}
				else
				{
					$new_files[] = $loaded_file;
				}
			}

			if(count($new_files) > 0)
			{
				$f = [];
				foreach($new_files as $file)
					$f[] = $file['file'];
				$all_files = implode(',', $f);

				echo '<h1>New database updates:</h1>';
				echo '<a href="?database_updater_run='.$all_files.'" style="font-size: 18px">UPDATE ALL</a>';
				echo '<ul>';
				$f = [];
				foreach($new_files as $file)
				{
					$fc = nl2br(Strings::truncate(Strings::normalizeNewLines(file_get_contents($this->getDirectory().'/'.$file['file'])), 512));
					$f[] = $file['file'];
					echo
<<< HTML
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

	final private function doIt()
	{
		if(!isset($_GET['database_updater_run']))
			return null;

		$hasError = false;

		$files = explode(',', $_GET['database_updater_run']);
		foreach($files as $file)
		{
			$file_id = (int) $file;
			if(isset($this->index[$file_id]) && $this->index[$file_id]['completed'] == null)
			{
				try {
					$sql_file = $this->getDirectory().'/'.$file;
					$sql = file_get_contents($sql_file);
					$this->database->query($sql);
					$this->index[$file_id]['completed'] = DateTime::from('now');
					$this->saveIndex();
				}
				catch (DriverException $exception)
				{
					$hasError = true;
					echo '<p><strong>'.$file.'</strong> – '.$exception->getMessage().'</p>';
				}
			}
			else
			{
				$hasError = true;
				echo '<p><strong>File '.$file.' has been not found in index file or has been marked as completed.</strong></p>';
			}
		}

		if($hasError)
			exit;

		// TODO: redirect to URL without our parameter.
	}

	final private function markAsUpdated()
	{
		if(!isset($_GET['database_updater_mark_as_updated']))
			return null;

		$files = explode(',', $_GET['database_updater_mark_as_updated']);
		foreach($files as $file)
		{
			$file_id = (int) $file;
			if(isset($this->index[$file_id]) && $this->index[$file_id]['completed'] == null)
			{
				$this->index[$file_id]['completed'] = DateTime::from('now');
				$this->saveIndex();
			}
		}
	}

	final private function doForm()
	{
		if(!isset($_POST['database_updater_form_new']))
			return null;

		if(!empty($_POST['database_updater_form_new_sql']))
		{
			$sql = $_POST['database_updater_form_new_sql'];

			$now = DateTime::from('now');
			$now_timestamp = $now->getTimestamp();
			$file_name = $now_timestamp.'.sql';
			$this->index[$now_timestamp] = [
				'file' => $file_name,
				'time' => $now,
				'completed' => $now,
			];

			$file = $this->getDirectory().'/'.$file_name;
			$f = fopen($file, 'w+');
			fwrite($f, $sql);
			fclose($f);

			if(!empty($_POST['database_updater_form_new_run']))
				$this->database->query($sql);

			$this->saveIndex();

			if(!empty($_POST['database_updater_form_back_link']))
			{
				header('Location: '.$_POST['database_updater_form_back_link']);
				exit;
			}
		}
	}

	final private function getDirectory() : string
	{
		return $this->wwwDir.self::DIRECTORY;
	}

}
