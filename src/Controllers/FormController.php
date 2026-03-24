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

        $cspNonce = base64_encode(random_bytes(16));

        $data = [
            'base_path' => $this->config['base_path'],
            'colors' => $this->config['form']['colors'],
            'form' => $this->config['form'],
            'fields' => $this->config['form']['fields'],
            'submit_url' => $this->config['base_path'] . '/submit',
            'captcha' => $this->config['captcha'],
            'csrf_token' => $_SESSION['csrf_token'],
            'on_submit' => $this->config['on_submit'],
            'csp_nonce' => $cspNonce,
        ];

        $content = $this->render('form', $data);
        $html = $this->render('layouts/base', array_merge($data, ['content' => $content]));

        $response->getBody()->write($html);

        // Set CSP with nonce to allow inline scripts/styles
        $csp = "default-src 'self'; " .
               "script-src 'self' 'nonce-{$cspNonce}' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/ https://www.google.com/; " .
               "style-src 'self' 'unsafe-inline'; " .
               "frame-src https://www.google.com/recaptcha/ https://recaptcha.google.com/ https://www.google.com/; " .
               "img-src 'self' data: *; " .
               "connect-src 'self' https://www.google.com/recaptcha/ https://www.google.com/";
        $response = $response->withHeader('Content-Security-Policy', $csp);

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
