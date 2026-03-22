<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Middleware;

use Iserter\EasyLeadCapture\Captcha\RecaptchaVerifier;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class CaptchaMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RecaptchaVerifier $verifier,
        private readonly float $threshold = 0.5
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = $request->getParsedBody();
        $token = is_array($body) ? ($body['captcha_token'] ?? '') : '';

        if (empty($token)) {
            return $this->errorResponse('Captcha verification failed (missing token).');
        }

        $serverParams = $request->getServerParams();
        $ip = $serverParams['REMOTE_ADDR'] ?? null;

        $result = $this->verifier->verify((string)$token, $ip);

        if (!$result['success']) {
            return $this->errorResponse('Captcha verification failed (Google rejected).');
        }

        $score = $result['score'] ?? 0.0;

        if ($score < $this->threshold) {
            return $this->errorResponse('Captcha verification failed (low score).');
        }

        // Attach score to request attributes
        $request = $request->withAttribute('captcha_score', $score);

        return $handler->handle($request);
    }

    private function errorResponse(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
        ], JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(403);
    }
}
