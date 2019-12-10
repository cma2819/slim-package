<?php
declare(strict_types=1);

// use App\Application\Handlers\HttpErrorHandler;
// use App\Application\Handlers\ShutdownHandler;
// use App\Application\ResponseEmitter\ResponseEmitter;
use DI\ContainerBuilder;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\ResponseEmitter;

require __DIR__ . '/../vendor/autoload.php';

// Get Environment Variables from .env
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../.env');
$environment = getenv('env') ?? 'develop';

// Instantiate PHP-DI ContainerBuilder
$containerBuilder = new ContainerBuilder();

if ($environment === 'production') { // Should be set to true in production
	$containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
}

// Set up settings
switch ($environment) {
	case 'production':
		$settings = require __DIR__ . '/../app/settings.production.php';
		$settings($containerBuilder);
		break;
	case 'testing':
		$settings = require __DIR__ . '/../app/settings.testing.php';
		$settings($containerBuilder);
		break;
	default:
		$settings = require __DIR__ . '/../app/settings.develop.php';
		$settings($containerBuilder);
		break;
}

// Set up dependencies
$dependencies = require __DIR__ . '/../app/dependencies.php';
$dependencies($containerBuilder);

// Set up repositories
$repositories = require __DIR__ . '/../app/repositories.php';
$repositories($containerBuilder);

// Build PHP-DI Container instance
$container = $containerBuilder->build();

// Instantiate the app
$app = \DI\Bridge\Slim\Bridge::create($container);

// Register middleware
$middleware = require __DIR__ . '/../app/middleware.php';
$middleware($app);

// Register routes
$routes = require __DIR__ . '/../app/routes.php';
$routes($app);

// Create Request object from globals
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();

// Add Routing Middleware
$app->addRoutingMiddleware();

/** @var bool $displayErrorDetails */
$displayErrorDetails = $container->get('settings')['displayErrorDetails'];
// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, false, false);

// Run App
$response = $app->handle($request);
$responseEmitter = new ResponseEmitter();
$responseEmitter->emit($response);