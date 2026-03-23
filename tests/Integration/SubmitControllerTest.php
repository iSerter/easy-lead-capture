<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Iserter\EasyLeadCapture\Config\ConfigValidator;
use Iserter\EasyLeadCapture\Database\Database;
use Iserter\EasyLeadCapture\Controllers\SubmitController;
use Iserter\EasyLeadCapture\Support\DeferredTaskRunner;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

class SubmitControllerTest extends TestCase
{
    private string $tempDb;
    private array $config;
    private Database $db;

    protected function setUp(): void
    {
        $this->tempDb = __DIR__ . '/test_submit.db';
        $this->config = ConfigValidator::validate(['admin' => ['password' => 'secret']]);
        $this->db = new Database($this->tempDb);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDb)) {
            @unlink($this->tempDb);
        }
    }

    public function test_it_stores_valid_lead(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'test_token';

        $deferred = new DeferredTaskRunner();
        $controller = new SubmitController($this->config, $this->db, $deferred);

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            '_csrf_token' => 'test_token'
        ];

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode($data)));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $controller->handle($request, (new ResponseFactory())->createResponse());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('"success":true', (string)$response->getBody());

        // Verify database
        $pdo = $this->db->getConnection();
        $lead = $pdo->query("SELECT * FROM leads LIMIT 1")->fetch();
        $this->assertNotEmpty($lead);
        
        $storedData = json_decode($lead['data'], true);
        $this->assertEquals('John Doe', $storedData['name']);
        $this->assertEquals('john@example.com', $storedData['email']);
    }

    public function test_it_returns_validation_errors(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'test_token';

        $deferred = new DeferredTaskRunner();
        $controller = new SubmitController($this->config, $this->db, $deferred);

        $data = [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            '_csrf_token' => 'test_token'
        ];

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode($data)));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $controller->handle($request, (new ResponseFactory())->createResponse());

        $this->assertEquals(422, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('"success":false', $body);
        $this->assertStringContainsString('valid email', $body);
    }

    public function test_it_rejects_missing_csrf_token(): void
    {
        $deferred = new DeferredTaskRunner();
        $controller = new SubmitController($this->config, $this->db, $deferred);

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode($data)));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $controller->handle($request, (new ResponseFactory())->createResponse());

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('CSRF token mismatch', (string)$response->getBody());
    }

    public function test_it_stores_lead_with_optional_fields(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'test_token';

        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'form' => [
                'fields' => [
                    'name'    => ['label' => 'Full Name', 'required' => true],
                    'email'   => ['label' => 'Email', 'required' => true, 'field_type' => 'email'],
                    'phone'   => ['label' => 'Phone', 'required' => false],
                    'website' => ['label' => 'Website', 'required' => false],
                    'message' => ['label' => 'Message', 'required' => false, 'field_type' => 'textarea'],
                ],
            ],
        ]);

        $deferred = new DeferredTaskRunner();
        $controller = new SubmitController($config, $this->db, $deferred);

        $data = [
            'name'    => 'Jane Doe',
            'email'   => 'jane@example.com',
            'phone'   => '+1234567890',
            'message' => "Hello,\nI'm interested in your product.",
            '_csrf_token' => 'test_token',
        ];

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode($data)));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $controller->handle($request, (new ResponseFactory())->createResponse());

        $this->assertEquals(200, $response->getStatusCode());

        $pdo = $this->db->getConnection();
        $lead = $pdo->query("SELECT * FROM leads LIMIT 1")->fetch();
        $storedData = json_decode($lead['data'], true);

        $this->assertEquals('Jane Doe', $storedData['name']);
        $this->assertEquals('jane@example.com', $storedData['email']);
        $this->assertEquals('+1234567890', $storedData['phone']);
        $this->assertStringContainsString('interested', $storedData['message']);
        $this->assertArrayNotHasKey('website', $storedData); // empty optional field omitted
    }

    public function test_it_skips_empty_optional_fields(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'test_token';

        $deferred = new DeferredTaskRunner();
        $controller = new SubmitController($this->config, $this->db, $deferred);

        $data = [
            'name'  => 'John Doe',
            'email' => 'john@example.com',
            '_csrf_token' => 'test_token',
        ];

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode($data)));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $controller->handle($request, (new ResponseFactory())->createResponse());

        $this->assertEquals(200, $response->getStatusCode());

        $pdo = $this->db->getConnection();
        $lead = $pdo->query("SELECT * FROM leads LIMIT 1")->fetch();
        $storedData = json_decode($lead['data'], true);

        $this->assertCount(2, $storedData);
        $this->assertEquals('John Doe', $storedData['name']);
    }

    public function test_it_rejects_missing_required_fields(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'test_token';

        $deferred = new DeferredTaskRunner();
        $controller = new SubmitController($this->config, $this->db, $deferred);

        $data = [
            'email' => 'john@example.com',
            '_csrf_token' => 'test_token',
        ];

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode($data)));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $controller->handle($request, (new ResponseFactory())->createResponse());

        $this->assertEquals(422, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('name', $body['errors']);
    }

    public function test_it_sanitizes_html_in_input(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'test_token';

        $deferred = new DeferredTaskRunner();
        $controller = new SubmitController($this->config, $this->db, $deferred);

        $data = [
            'name'  => '<script>alert("xss")</script>',
            'email' => 'john@example.com',
            '_csrf_token' => 'test_token',
        ];

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode($data)));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $controller->handle($request, (new ResponseFactory())->createResponse());

        $this->assertEquals(200, $response->getStatusCode());

        $pdo = $this->db->getConnection();
        $lead = $pdo->query("SELECT * FROM leads LIMIT 1")->fetch();
        $storedData = json_decode($lead['data'], true);

        $this->assertStringNotContainsString('<script>', $storedData['name']);
        $this->assertStringContainsString('&lt;script&gt;', $storedData['name']);
    }

    public function test_it_regenerates_csrf_token_after_success(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'original_token';

        $deferred = new DeferredTaskRunner();
        $controller = new SubmitController($this->config, $this->db, $deferred);

        $data = [
            'name'  => 'John Doe',
            'email' => 'john@example.com',
            '_csrf_token' => 'original_token',
        ];

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode($data)));
        $request = $request->withHeader('Content-Type', 'application/json');

        $controller->handle($request, (new ResponseFactory())->createResponse());

        $this->assertNotEquals('original_token', $_SESSION['csrf_token']);
    }

    public function test_it_stores_ip_address_and_user_agent(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'test_token';

        $deferred = new DeferredTaskRunner();
        $controller = new SubmitController($this->config, $this->db, $deferred);

        $data = [
            'name'  => 'John Doe',
            'email' => 'john@example.com',
            '_csrf_token' => 'test_token',
        ];

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode($data)));
        $request = $request->withHeader('Content-Type', 'application/json');
        $request = $request->withHeader('User-Agent', 'TestBrowser/1.0');
        $request = $request->withHeader('X-Forwarded-For', '203.0.113.50');

        $response = $controller->handle($request, (new ResponseFactory())->createResponse());

        $this->assertEquals(200, $response->getStatusCode());

        $pdo = $this->db->getConnection();
        $lead = $pdo->query("SELECT * FROM leads LIMIT 1")->fetch();

        $this->assertEquals('203.0.113.50', $lead['ip_address']);
        $this->assertEquals('TestBrowser/1.0', $lead['user_agent']);
    }
}
