<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FormController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function show(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $data = [
            'base_path' => $this->config['base_path'],
            'colors' => $this->config['form']['colors'],
            'form' => $this->config['form'],
            'fields' => $this->config['form']['fields'],
            'submit_url' => $this->config['base_path'] . '/submit',
            'captcha' => $this->config['captcha'],
            'csrf_token' => $_SESSION['csrf_token'],
        ];

        $content = $this->render('form', $data);
        $html = $this->render('layouts/base', array_merge($data, ['content' => $content]));

        $response->getBody()->write($html);
        return $response;
    }

    private function render(string $template, array $data): string
    {
        extract($data);
        ob_start();
        include dirname(__DIR__) . "/Views/{$template}.php";
        return ob_get_clean();
    }
}
