<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Iserter\EasyLeadCapture\Config\ConfigValidator;
use Iserter\EasyLeadCapture\Database\Database;
use Iserter\EasyLeadCapture\Controllers\AdminController;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

class AdminControllerTest extends TestCase
{
    private string $tempDb;
    private array $config;
    private Database $db;

    protected function setUp(): void
    {
        $this->tempDb = __DIR__ . '/test_admin.db';
        $this->config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'base_path' => '/lead-capture'
        ]);
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

    public function test_login_form_renders(): void
    {
        $controller = new AdminController($this->config, $this->db);
        $request = (new RequestFactory())->createRequest('GET', '/admin/login');
        $response = (new ResponseFactory())->createResponse();

        $response = $controller->loginForm($request, $response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Admin Login', (string)$response->getBody());
    }

    public function test_successful_login(): void
    {
        $controller = new AdminController($this->config, $this->db);
        $request = (new RequestFactory())->createRequest('POST', '/admin/login');
        $request = $request->withParsedBody(['password' => 'secret']);
        $response = (new ResponseFactory())->createResponse();

        $response = $controller->login($request, $response);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/admin', $response->getHeaderLine('Location'));
        $this->assertStringContainsString('elc_session=', $response->getHeaderLine('Set-Cookie'));

        // Verify session in DB
        $pdo = $this->db->getConnection();
        $session = $pdo->query("SELECT * FROM admin_sessions LIMIT 1")->fetch();
        $this->assertNotEmpty($session);
    }

    public function test_failed_login(): void
    {
        $controller = new AdminController($this->config, $this->db);
        $request = (new RequestFactory())->createRequest('POST', '/admin/login');
        $request = $request->withParsedBody(['password' => 'wrong']);
        $response = (new ResponseFactory())->createResponse();

        $response = $controller->login($request, $response);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/admin/login?error=1', $response->getHeaderLine('Location'));

        // Verify attempt in DB
        $pdo = $this->db->getConnection();
        $attempt = $pdo->query("SELECT * FROM login_attempts LIMIT 1")->fetch();
        $this->assertNotEmpty($attempt);
    }

    public function test_login_rate_limit(): void
    {
        $controller = new AdminController($this->config, $this->db);
        $pdo = $this->db->getConnection();
        
        // Add 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $pdo->exec("INSERT INTO login_attempts (ip_address) VALUES ('127.0.0.1')");
        }

        $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('POST', '/admin/login', ['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withParsedBody(['password' => 'secret']); // Even correct password
        $response = (new ResponseFactory())->createResponse();

        $response = $controller->login($request, $response);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('locked=1', $response->getHeaderLine('Location'));
    }

    public function test_logout(): void
    {
        $controller = new AdminController($this->config, $this->db);
        
        // Create session
        $token = 'test_token';
        $pdo = $this->db->getConnection();
        $pdo->prepare("INSERT INTO admin_sessions (token, expires_at) VALUES (?, datetime('now', '+1 hour'))")->execute([$token]);

        $request = (new RequestFactory())->createRequest('POST', '/admin/logout');
        $request = $request->withCookieParams(['elc_session' => $token]);
        $response = (new ResponseFactory())->createResponse();

        $response = $controller->logout($request, $response);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('elc_session=;', $response->getHeaderLine('Set-Cookie'));

        // Verify session deleted
        $session = $pdo->query("SELECT * FROM admin_sessions WHERE token = 'test_token'")->fetch();
        $this->assertFalse($session);
    }

    public function test_dashboard_index(): void
    {
        $controller = new AdminController($this->config, $this->db);
        $pdo = $this->db->getConnection();
        
        // Add a lead
        $data = json_encode(['name' => 'John Doe', 'email' => 'john@example.com']);
        $pdo->prepare("INSERT INTO leads (data, created_at) VALUES (?, '2023-01-01 12:00:00')")->execute([$data]);

        $request = (new RequestFactory())->createRequest('GET', '/admin');
        $response = (new ResponseFactory())->createResponse();

        $response = $controller->index($request, $response);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Admin Dashboard', $body);
        $this->assertStringContainsString('John Doe', $body);
        $this->assertStringContainsString('john@example.com', $body);
    }

    public function test_export_csv(): void
    {
        $controller = new AdminController($this->config, $this->db);
        $pdo = $this->db->getConnection();
        
        // Add a lead
        $data = json_encode(['name' => 'John Doe', 'email' => 'john@example.com']);
        $pdo->prepare("INSERT INTO leads (data, created_at) VALUES (?, '2023-01-01 12:00:00')")->execute([$data]);

        $request = (new RequestFactory())->createRequest('GET', '/admin/export');
        $response = (new ResponseFactory())->createResponse();

        $response = $controller->export($request, $response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/csv; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment; filename="leads-', $response->getHeaderLine('Content-Disposition'));
        
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Name,E-mail,Date', $body);
        $this->assertStringContainsString('John Doe', $body);
        $this->assertStringContainsString('john@example.com', $body);
        $this->assertStringContainsString('2023-01-01 12:00:00', $body);
    }
}
