# Database updater
Simple way to keep your database fresh.

## Installation
	composer require stanislav-janu/database-updater

### bootstrap.php
	...
	$container = $configurator->createContainer();

	if(!Tracy\Debugger::$productionMode)
	{
		$databaseUpdater = new JCode\DatabaseUpdater($container->getParameters()['wwwDir'], new Nette\Database\Context(
			$container->getService('database.default.connection'),
			$container->getService('database.default.structure')
		));
		$databaseUpdater->run();
	}

	return $container;

### config.neon
	tracy:
		bar:
			- JCode\DatabaseUpdaterPanel