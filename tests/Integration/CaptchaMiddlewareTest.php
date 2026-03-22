<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Iserter\EasyLeadCapture\Captcha\RecaptchaVerifier;
use Iserter\EasyLeadCapture\Middleware\CaptchaMiddleware;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CaptchaMiddlewareTest extends TestCase
{
    public function test_it_rejects_missing_token(): void
    {
        $verifier = $this->createMock(RecaptchaVerifier::class);
        $middleware = new CaptchaMiddleware($verifier);

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $handler = $this->createMock(RequestHandlerInterface::class);

        $response = $middleware->process($request, $handler);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('missing token', (string)$response->getBody());
    }

    public function test_it_rejects_low_score(): void
    {
        $verifier = $this->createMock(RecaptchaVerifier::class);
        $verifier->method('verify')->willReturn([
            'success' => true,
            'score' => 0.3,
            'action' => 'submit'
        ]);

        $middleware = new CaptchaMiddleware($verifier, 0.5);

        $request = (new RequestFactory())->createRequest('POST', '/submit')
            ->withParsedBody(['captcha_token' => 'test_token']);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $response = $middleware->process($request, $handler);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Captcha verification failed', (string)$response->getBody());
    }

    public function test_it_passes_valid_token(): void
    {
        $verifier = $this->createMock(RecaptchaVerifier::class);
        $verifier->method('verify')->willReturn([
            'success' => true,
            'score' => 0.9,
            'action' => 'submit'
        ]);

        $middleware = new CaptchaMiddleware($verifier, 0.5);

        $request = (new RequestFactory())->createRequest('POST', '/submit')
            ->withParsedBody(['captcha_token' => 'test_token']);
        
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface {
                $response = (new ResponseFactory())->createResponse();
                $response->getBody()->write('score:' . $request->getAttribute('captcha_score'));
                return $response;
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('score:0.9', (string)$response->getBody());
    }
}
