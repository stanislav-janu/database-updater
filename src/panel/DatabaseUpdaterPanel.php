<?php declare(strict_types=1);

namespace JCode;

use Latte\Engine;
use Tracy\IBarPanel;


class DatabaseUpdaterPanel implements IBarPanel
{
	function getPanel(): string
	{
		return (new Engine())->renderToString(__DIR__ . '/panel.latte', [
			'back_link' => $_SERVER['REQUEST_URI'],
		]);
	}


	function getTab(): string
	{
		return (new Engine())->renderToString(__DIR__ . '/tab.latte');
	}

}
