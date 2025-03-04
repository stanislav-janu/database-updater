<?php declare(strict_types=1);

namespace JCode;

use Latte\Engine;
use Tracy\IBarPanel;


class DatabaseUpdaterPanel implements IBarPanel
{
	public function getPanel(): string
	{
		return (new Engine())->renderToString(__DIR__ . '/panel.latte', [
			'back_link' => $_SERVER['REQUEST_URI'],
		]);
	}


	public function getTab(): string
	{
		return (new Engine())->renderToString(__DIR__ . '/tab.latte');
	}

}
