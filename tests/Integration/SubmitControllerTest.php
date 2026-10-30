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
            @unlink($this->tempDb . '-shm');
            @unlink($this->tempDb . '-wal');
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

        // Only submitted form fields are stored (plus the _consent metadata record)
        $this->assertCount(2, array_diff_key($storedData, ['_consent' => true]));
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

    public function test_it_defers_confirmation_email_when_enabled_and_email_is_present(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'test_token';

        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'confirmation_email' => [
                'enabled' => true,
                'subject' => 'Thanks',
            ],
        ]);

        $deferred = new DeferredTaskRunner();
        $controller = new SubmitController($config, $this->db, $deferred);

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            '_csrf_token' => 'test_token',
        ])));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $controller->handle($request, (new ResponseFactory())->createResponse());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $this->getDeferredTasks($deferred));
    }

    public function test_it_does_not_defer_confirmation_email_without_email_field(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'test_token';

        $previousErrorLog = ini_get('error_log');
        $tempErrorLog = tempnam(sys_get_temp_dir(), 'elc-log-') ?: sys_get_temp_dir() . '/elc-log-test.log';
        ini_set('error_log', $tempErrorLog);

        try {
            $config = ConfigValidator::validate([
                'admin' => ['password' => 'secret'],
                'form' => [
                    'fields' => [
                        'email' => ['label' => 'Email', 'required' => false, 'field_type' => 'text'],
                    ],
                ],
                'confirmation_email' => [
                    'enabled' => true,
                    'subject' => 'Thanks',
                ],
            ]);
        } finally {
            ini_set('error_log', $previousErrorLog === false ? '' : $previousErrorLog);
            if (file_exists($tempErrorLog)) {
                unlink($tempErrorLog);
            }
        }

        $deferred = new DeferredTaskRunner();
        $controller = new SubmitController($config, $this->db, $deferred);

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            '_csrf_token' => 'test_token',
        ])));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $controller->handle($request, (new ResponseFactory())->createResponse());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $this->getDeferredTasks($deferred));
    }

    private function getDeferredTasks(DeferredTaskRunner $deferred): array
    {
        $reflection = new \ReflectionClass($deferred);
        $property = $reflection->getProperty('tasks');
        $property->setAccessible(true);

        return $property->getValue($deferred);
    }

    public function test_it_records_marketing_consent_when_checked(): void
    {
        $lead = $this->submitAndFetch($this->config, ['_consent_marketing' => '1']);

        $consent = $lead['_consent'];
        $this->assertTrue($consent['marketing']);
        $this->assertEquals($this->config['consent']['marketing']['label'], $consent['marketing_text']);
        $this->assertNull($consent['marketing_withdrawn_at']);
        $this->assertNotEmpty($consent['captured_at']);
        $this->assertArrayNotHasKey('terms', $consent);
    }

    public function test_it_records_declined_marketing_consent_when_unchecked(): void
    {
        $lead = $this->submitAndFetch($this->config, []);

        $this->assertFalse($lead['_consent']['marketing']);
    }

    public function test_it_accepts_standard_checked_values(): void
    {
        foreach (['1', 'on', 'true', true, 1] as $value) {
            $lead = $this->submitAndFetch($this->config, ['_consent_marketing' => $value]);
            $this->assertTrue($lead['_consent']['marketing'], 'Value ' . var_export($value, true) . ' should count as consent');
        }
    }

    public function test_it_treats_unexpected_consent_values_as_declined(): void
    {
        foreach (['0', 'false', 'yes please', ''] as $value) {
            $lead = $this->submitAndFetch($this->config, ['_consent_marketing' => $value]);
            $this->assertFalse($lead['_consent']['marketing'], "Value '{$value}' must not count as consent");
        }
    }

    public function test_it_ignores_client_supplied_consent_text(): void
    {
        $lead = $this->submitAndFetch($this->config, [
            '_consent_marketing' => '1',
            '_consent' => ['marketing_text' => 'forged'],
        ]);

        $this->assertEquals($this->config['consent']['marketing']['label'], $lead['_consent']['marketing_text']);
    }

    public function test_it_rejects_submission_without_required_terms(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'test_token';

        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'consent' => ['terms' => ['enabled' => true]],
        ]);
        $controller = new SubmitController($config, $this->db, new DeferredTaskRunner());

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            '_csrf_token' => 'test_token',
        ])));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $controller->handle($request, (new ResponseFactory())->createResponse());

        $this->assertEquals(422, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('You must accept the terms to continue.', $body['errors']['_consent_terms']);
        $this->assertEquals(0, (int)$this->db->getConnection()->query("SELECT COUNT(*) FROM leads")->fetchColumn());
    }

    public function test_it_records_terms_acceptance_and_privacy_url(): void
    {
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'consent' => [
                'terms' => ['enabled' => true],
                'privacy_policy_url' => 'https://example.com/privacy',
            ],
        ]);

        $lead = $this->submitAndFetch($config, ['_consent_terms' => '1']);

        $this->assertTrue($lead['_consent']['terms']);
        $this->assertFalse($lead['_consent']['marketing']);
        $this->assertEquals('https://example.com/privacy', $lead['_consent']['privacy_policy_url']);
    }

    public function test_it_omits_consent_record_when_all_consent_disabled(): void
    {
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'consent' => ['marketing' => ['enabled' => false]],
        ]);

        $lead = $this->submitAndFetch($config, ['_consent_marketing' => '1']);

        $this->assertArrayNotHasKey('_consent', $lead);
    }

    private function submitAndFetch(array $config, array $extraInput): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'test_token';

        $controller = new SubmitController($config, $this->db, new DeferredTaskRunner());

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode(array_merge([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            '_csrf_token' => 'test_token',
        ], $extraInput))));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $controller->handle($request, (new ResponseFactory())->createResponse());
        $this->assertEquals(200, $response->getStatusCode());

        $row = $this->db->getConnection()->query("SELECT data FROM leads ORDER BY id DESC LIMIT 1")->fetch();
        return json_decode($row['data'], true);
    }
}
