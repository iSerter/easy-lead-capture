<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\App as SlimApp;

class App
{
    private array $config;
    private SlimApp $slimApp;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->slimApp = AppFactory::create();

        $basePath = $config['base_path'] ?? '';
        if ($basePath !== '') {
            $this->slimApp->setBasePath($basePath);
        }

        $this->registerRoutes();
    }

    public function run(): void
    {
        $this->slimApp->run();
    }

    private function registerRoutes(): void
    {
        $config = $this->config;

        // Form page
        $this->slimApp->get('/form', function (Request $request, Response $response) use ($config) {
            $response->getBody()->write('Lead capture form');
            return $response;
        });

        // Form submission
        $this->slimApp->post('/submit', function (Request $request, Response $response) use ($config) {
            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Embed JS loader
        $this->slimApp->get('/embed.js', function (Request $request, Response $response) use ($config) {
            $response->getBody()->write('// embed.js placeholder');
            return $response->withHeader('Content-Type', 'application/javascript');
        });

        // CSS asset
        $this->slimApp->get('/assets/styles.css', function (Request $request, Response $response) use ($config) {
            $response->getBody()->write('/* styles placeholder */');
            return $response->withHeader('Content-Type', 'text/css');
        });

        // Admin routes
        $this->slimApp->get('/admin', function (Request $request, Response $response) use ($config) {
            $response->getBody()->write('Admin dashboard');
            return $response;
        });

        $this->slimApp->get('/admin/login', function (Request $request, Response $response) use ($config) {
            $response->getBody()->write('Admin login');
            return $response;
        });

        $this->slimApp->post('/admin/login', function (Request $request, Response $response) use ($config) {
            return $response;
        });

        $this->slimApp->post('/admin/logout', function (Request $request, Response $response) use ($config) {
            return $response;
        });

        $this->slimApp->get('/admin/export', function (Request $request, Response $response) use ($config) {
            $response->getBody()->write('CSV export');
            return $response;
        });
    }
}
