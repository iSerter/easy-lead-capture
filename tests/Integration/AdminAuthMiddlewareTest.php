<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Iserter\EasyLeadCapture\Database\Database;
use Iserter\EasyLeadCapture\Middleware\AdminAuthMiddleware;
use Slim\Psr7\Factory\RequestFactory;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AdminAuthMiddlewareTest extends TestCase
{
    private string $tempDb;
    private Database $db;

    protected function setUp(): void
    {
        $this->tempDb = __DIR__ . '/test_middleware.db';
        $this->db = new Database($this->tempDb);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDb)) {
            @unlink($this->tempDb);
            @unlink($this->tempDb . '-shm');
            @unlink($this->tempDb . '-wal');
        }
    }

    public function test_it_redirects_if_no_session(): void
    {
        $middleware = new AdminAuthMiddleware($this->db, '/lead-capture');
        $request = (new RequestFactory())->createRequest('GET', '/admin');
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/lead-capture/admin/login', $response->getHeaderLine('Location'));
    }

    public function test_it_redirects_if_session_invalid(): void
    {
        $middleware = new AdminAuthMiddleware($this->db, '/lead-capture');
        $request = (new RequestFactory())->createRequest('GET', '/admin');
        $request = $request->withCookieParams(['elc_session' => 'invalid']);
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function test_it_redirects_if_session_expired(): void
    {
        $pdo = $this->db->getConnection();
        $pdo->exec("INSERT INTO admin_sessions (token, expires_at) VALUES ('expired', datetime('now', '-1 hour'))");

        $middleware = new AdminAuthMiddleware($this->db, '/lead-capture');
        $request = (new RequestFactory())->createRequest('GET', '/admin');
        $request = $request->withCookieParams(['elc_session' => 'expired']);
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function test_it_passes_if_session_valid(): void
    {
        $pdo = $this->db->getConnection();
        $pdo->exec("INSERT INTO admin_sessions (token, expires_at) VALUES ('valid', datetime('now', '+1 hour'))");

        $middleware = new AdminAuthMiddleware($this->db, '/lead-capture');
        $request = (new RequestFactory())->createRequest('GET', '/admin');
        $request = $request->withCookieParams(['elc_session' => 'valid']);
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
                ->method('handle')
                ->willReturn(new Response());

        $response = $middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
