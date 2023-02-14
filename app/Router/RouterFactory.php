<?php

declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\RouteList;


final class RouterFactory
{
	use Nette\StaticClass;

	public static function createRouter(): RouteList
	{
		$router = new RouteList;
				//$router[] = new Nette\Application\Routers\CliRouter(array('action' => 'Cli:spravaceniku'));
				//$router[] = new Nette\Application\Routers\CliRouter(array('action' => 'Status:default'));
		$router->addRoute('<presenter>/<action>[/<id>]', 'Homepage:default');
		return $router;
	}
}
