<?php declare(strict_types=1);

namespace JCode;

use Latte\Engine;
use Tracy\IBarPanel;

/**
 * Class DatabaseUpdaterPanel
 * @package JCode
 */
class DatabaseUpdaterPanel implements IBarPanel
{

	/**
	 * @return string
	 */
	function getPanel() : string
	{
		return (new Engine())->renderToString(__DIR__.'/panel.latte', [
			'back_link' => $_SERVER['REQUEST_URI']
		]);
	}

	/**
	 * @return string
	 */
	function getTab() : string
	{
		return (new Engine())->renderToString(__DIR__.'/tab.latte');
	}

}
