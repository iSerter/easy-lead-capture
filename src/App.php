<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture;

use Iserter\EasyLeadCapture\Config\ConfigValidator;
use Iserter\EasyLeadCapture\Database\Database;
use Iserter\EasyLeadCapture\Support\DeferredTaskRunner;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\App as SlimApp;

class App
{
    private array $config;
    private SlimApp $slimApp;
    private Database $db;
    private DeferredTaskRunner $deferredRunner;

    public function __construct(array $config)
    {
        $this->config = ConfigValidator::validate($config);
        $this->db = new Database($this->config['database']['path']);
        $this->deferredRunner = new DeferredTaskRunner();
        $this->slimApp = AppFactory::create();

        $basePath = $this->config['base_path'] ?? '';
        if ($basePath !== '') {
            $this->slimApp->setBasePath($basePath);
        }

        $this->registerRoutes();
        $this->slimApp->addBodyParsingMiddleware();
        $this->slimApp->add(new Middleware\SecurityHeadersMiddleware($this->config['base_path']));

        // Ensure deferred tasks run after the response is sent
        register_shutdown_function([$this->deferredRunner, 'run']);
    }

    public function run(): void
    {
        $this->slimApp->run();
    }

    private function registerRoutes(): void
    {
        $config = $this->config;
        $db = $this->db;
        $deferred = $this->deferredRunner;

        // Form page
        $this->slimApp->get('/form', [new Controllers\FormController($config), 'show']);

        // Form submission
        $submitRoute = $this->slimApp->post('/submit', [new Controllers\SubmitController($config, $db, $deferred), 'handle']);

        if (($config['captcha']['enabled'] ?? false)) {
            $verifier = new Captcha\RecaptchaVerifier($config['captcha']['recaptcha_secret_key']);
            $submitRoute->add(new Middleware\CaptchaMiddleware($verifier, (float)$config['captcha']['threshold']));
        }

        // Embed JS loader
        $this->slimApp->get('/embed.js', [new Controllers\EmbedController(), 'embedJs']);

        // CSS asset
        $this->slimApp->get('/assets/styles.css', [new Controllers\EmbedController(), 'styles']);

        // Admin routes
        $adminController = new Controllers\AdminController($config, $db);
        $adminAuthMiddleware = new Middleware\AdminAuthMiddleware($db, $config['base_path']);

        $this->slimApp->get('/admin/login', [$adminController, 'loginForm']);
        $this->slimApp->post('/admin/login', [$adminController, 'login']);
        $this->slimApp->post('/admin/logout', [$adminController, 'logout']);

        $this->slimApp->group('/admin', function ($group) use ($adminController) {
            $group->get('', [$adminController, 'index']);
            $group->get('/export', [$adminController, 'export']);
        })->add($adminAuthMiddleware);
    }
}
