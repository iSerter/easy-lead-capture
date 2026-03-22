<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EmbedController
{
    public function embedJs(Request $request, Response $response): Response
    {
        $path = dirname(__DIR__, 2) . '/assets/embed.js';
        if (!file_exists($path)) {
            return $response->withStatus(404);
        }

        $response->getBody()->write(file_get_contents($path));
        return $response->withHeader('Content-Type', 'application/javascript')
                        ->withHeader('Cache-Control', 'public, max-age=86400');
    }

    public function styles(Request $request, Response $response): Response
    {
        $path = dirname(__DIR__, 2) . '/assets/styles.css';
        if (!file_exists($path)) {
            return $response->withStatus(404);
        }

        $response->getBody()->write(file_get_contents($path));
        return $response->withHeader('Content-Type', 'text/css')
                        ->withHeader('Cache-Control', 'public, max-age=86400');
    }
}
