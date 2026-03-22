<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $basePath) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $uri = $request->getUri()->getPath();

        // Common headers
        $response = $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-XSS-Protection', '1; mode=block')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Admin headers
        if (str_starts_with($uri, $this->basePath . '/admin')) {
            $response = $response->withHeader('X-Frame-Options', 'SAMEORIGIN');
        } else {
            // Form/embed headers
            $csp = "default-src 'self'; " .
                   "script-src 'self' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/ https://www.google.com/; " .
                   "style-src 'self' 'unsafe-inline'; " .
                   "frame-src https://www.google.com/recaptcha/ https://recaptcha.google.com/ https://www.google.com/; " .
                   "img-src 'self' data: *; " .
                   "connect-src 'self' https://www.google.com/recaptcha/ https://www.google.com/";
            
            $response = $response->withHeader('Content-Security-Policy', $csp);
        }

        return $response;
    }
}
