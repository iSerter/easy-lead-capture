<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Iserter\EasyLeadCapture\Config\ConfigValidator;
use Iserter\EasyLeadCapture\Database\Database;
use Iserter\EasyLeadCapture\Controllers\AdminController;
use Iserter\EasyLeadCapture\IpGeo\IpGeoProvider;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

class AdminControllerTest extends TestCase
{
    private string $tempDb;
    private array $config;
    private Database $db;
    private IpGeoProvider $mockGeoProvider;

    protected function setUp(): void
    {
        $this->tempDb = __DIR__ . '/test_admin.db';
        $this->config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'base_path' => '/lead-capture'
        ]);
        $this->db = new Database($this->tempDb);
        $this->mockGeoProvider = new class implements IpGeoProvider {
            public function lookup(string $ip): ?array { return null; }
        };
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
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
        $request = (new RequestFactory())->createRequest('GET', '/admin/login');
        $response = (new ResponseFactory())->createResponse();

        $response = $controller->loginForm($request, $response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Admin Login', (string)$response->getBody());
    }

    public function test_successful_login(): void
    {
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
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
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
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
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
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
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
        
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
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
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
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
        $pdo = $this->db->getConnection();
        
        // Add a lead
        $data = json_encode(['name' => 'John Doe', 'email' => 'john@example.com']);
        $pdo->prepare("INSERT INTO leads (data, status, notes, created_at) VALUES (?, 'new', 'Some note', '2023-01-01 12:00:00')")->execute([$data]);

        $request = (new RequestFactory())->createRequest('GET', '/admin/export');
        $response = (new ResponseFactory())->createResponse();

        $response = $controller->export($request, $response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/csv; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment; filename="leads-', $response->getHeaderLine('Content-Disposition'));
        
        $body = (string)$response->getBody();
        // Updated for Status, Notes, and Geo columns
        $this->assertStringContainsString('"Country Code"', $body);
        $this->assertStringContainsString('Region', $body);
        $this->assertStringContainsString('City', $body);
        $this->assertStringContainsString('John Doe', $body);
        $this->assertStringContainsString('john@example.com', $body);
        $this->assertStringContainsString('new', $body);
        $this->assertStringContainsString('Some note', $body);
        $this->assertStringContainsString('2023-01-01 12:00:00', $body);
    }

    public function test_update_status(): void
    {
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
        $pdo = $this->db->getConnection();
        
        // Add a lead
        $data = json_encode(['name' => 'John Doe']);
        $pdo->prepare("INSERT INTO leads (data) VALUES (?)")->execute([$data]);
        $id = $pdo->lastInsertId();

        $request = (new RequestFactory())->createRequest('POST', "/admin/leads/$id/status");
        $request = $request->withParsedBody(['status' => 'qualified']);
        $response = (new ResponseFactory())->createResponse();

        $response = $controller->updateStatus($request, $response, ['id' => (string)$id]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('"success":true', (string)$response->getBody());

        // Verify in DB
        $status = $pdo->query("SELECT status FROM leads WHERE id = $id")->fetchColumn();
        $this->assertEquals('qualified', $status);
    }

    public function test_update_notes(): void
    {
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
        $pdo = $this->db->getConnection();
        
        // Add a lead
        $data = json_encode(['name' => 'John Doe']);
        $pdo->prepare("INSERT INTO leads (data) VALUES (?)")->execute([$data]);
        $id = $pdo->lastInsertId();

        $request = (new RequestFactory())->createRequest('POST', "/admin/leads/$id/notes");
        $request = $request->withParsedBody(['notes' => 'Handled via phone']);
        $response = (new ResponseFactory())->createResponse();

        $response = $controller->updateNotes($request, $response, ['id' => (string)$id]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('"success":true', (string)$response->getBody());

        // Verify in DB
        $notes = $pdo->query("SELECT notes FROM leads WHERE id = $id")->fetchColumn();
        $this->assertEquals('Handled via phone', $notes);
    }

    public function test_dashboard_shows_consent_badges(): void
    {
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
        $this->seedConsentLeads();

        $response = $controller->index((new RequestFactory())->createRequest('GET', '/admin'), (new ResponseFactory())->createResponse());

        $body = (string)$response->getBody();
        $this->assertStringContainsString('Opted in', $body);
        $this->assertStringContainsString('Withdrawn', $body);
        $this->assertStringContainsString('Captured before consent tracking', $body);
    }

    public function test_consent_filters(): void
    {
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
        $this->seedConsentLeads();

        $expectations = [
            'yes' => ['Opted In Olivia'],
            'no' => ['Declined Dana', 'Withdrawn Will'],
            'unknown' => ['Legacy Larry'],
        ];

        foreach ($expectations as $filter => $expectedNames) {
            $request = (new RequestFactory())->createRequest('GET', '/admin/export')->withQueryParams(['consent' => $filter]);
            $body = (string)$controller->export($request, (new ResponseFactory())->createResponse())->getBody();

            foreach (['Opted In Olivia', 'Declined Dana', 'Withdrawn Will', 'Legacy Larry'] as $name) {
                if (in_array($name, $expectedNames, true)) {
                    $this->assertStringContainsString($name, $body, "Filter '{$filter}' should include {$name}");
                } else {
                    $this->assertStringNotContainsString($name, $body, "Filter '{$filter}' should exclude {$name}");
                }
            }
        }
    }

    public function test_export_includes_consent_columns(): void
    {
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
        $this->seedConsentLeads();

        $body = (string)$controller->export((new RequestFactory())->createRequest('GET', '/admin/export'), (new ResponseFactory())->createResponse())->getBody();

        $this->assertStringContainsString('"Marketing Consent","Consent Text","Consent Date","Consent Withdrawn At"', $body);
        $this->assertStringContainsString(',Yes,"Send me news",2026-01-01T00:00:00+00:00,,new,', $body);
        $this->assertStringContainsString(',Unknown,,,,new,', $body);
        $this->assertStringContainsString(',Withdrawn,', $body);
    }

    public function test_withdraw_consent(): void
    {
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
        $ids = $this->seedConsentLeads();

        $response = $controller->withdrawConsent(
            (new RequestFactory())->createRequest('POST', '/admin/leads/x/consent/withdraw'),
            (new ResponseFactory())->createResponse(),
            ['id' => $ids['opted_in']]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($this->db->getConnection()->query("SELECT data FROM leads WHERE id = {$ids['opted_in']}")->fetchColumn(), true);
        $this->assertTrue($data['_consent']['marketing']);
        $this->assertNotEmpty($data['_consent']['marketing_withdrawn_at']);
        $this->assertEquals('Opted In Olivia', $data['name']);
    }

    public function test_withdraw_consent_rejects_leads_without_active_consent(): void
    {
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
        $ids = $this->seedConsentLeads();

        foreach (['declined', 'withdrawn', 'legacy'] as $key) {
            $response = $controller->withdrawConsent(
                (new RequestFactory())->createRequest('POST', '/admin/leads/x/consent/withdraw'),
                (new ResponseFactory())->createResponse(),
                ['id' => $ids[$key]]
            );
            $this->assertEquals(400, $response->getStatusCode(), "Lead '{$key}' should be rejected");
        }

        $response = $controller->withdrawConsent(
            (new RequestFactory())->createRequest('POST', '/admin/leads/999/consent/withdraw'),
            (new ResponseFactory())->createResponse(),
            ['id' => '999']
        );
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @return array<string, string>
     */
    private function seedConsentLeads(): array
    {
        $pdo = $this->db->getConnection();
        $consent = fn(bool $marketing, ?string $withdrawnAt = null) => [
            'marketing' => $marketing,
            'marketing_text' => 'Send me news',
            'marketing_withdrawn_at' => $withdrawnAt,
            'captured_at' => '2026-01-01T00:00:00+00:00',
        ];

        $leads = [
            'opted_in' => ['name' => 'Opted In Olivia', '_consent' => $consent(true)],
            'declined' => ['name' => 'Declined Dana', '_consent' => $consent(false)],
            'withdrawn' => ['name' => 'Withdrawn Will', '_consent' => $consent(true, '2026-02-01T00:00:00+00:00')],
            'legacy' => ['name' => 'Legacy Larry'],
        ];

        $ids = [];
        foreach ($leads as $key => $data) {
            $pdo->prepare("INSERT INTO leads (data) VALUES (?)")->execute([json_encode($data)]);
            $ids[$key] = $pdo->lastInsertId();
        }

        return $ids;
    }

    public function test_dashboard_ignores_array_consent_param(): void
    {
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
        $this->seedConsentLeads();

        $request = (new RequestFactory())->createRequest('GET', '/admin')->withQueryParams(['consent' => ['yes']]);
        $response = $controller->index($request, (new ResponseFactory())->createResponse());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Legacy Larry', (string)$response->getBody());
    }

    public function test_consent_filter_stays_visible_when_feature_disabled_and_page_empty(): void
    {
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'consent' => ['marketing' => ['enabled' => false]],
        ]);
        $controller = new AdminController($config, $this->db, $this->mockGeoProvider);
        $this->seedConsentLeads();

        // Filter matches nothing on an otherwise-populated table
        $request = (new RequestFactory())->createRequest('GET', '/admin')->withQueryParams(['consent' => 'yes', 'from' => '2099-01-01']);
        $body = (string)$controller->index($request, (new ResponseFactory())->createResponse())->getBody();

        $this->assertStringContainsString('No leads found.', $body);
        $this->assertStringContainsString('Marketing Consent</label>', $body);
    }

    public function test_consent_column_hidden_when_feature_disabled_and_no_records(): void
    {
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'consent' => ['marketing' => ['enabled' => false]],
        ]);
        $controller = new AdminController($config, $this->db, $this->mockGeoProvider);
        $this->db->getConnection()->prepare("INSERT INTO leads (data) VALUES (?)")->execute([json_encode(['name' => 'Legacy'])]);

        $body = (string)$controller->index((new RequestFactory())->createRequest('GET', '/admin'), (new ResponseFactory())->createResponse())->getBody();

        $this->assertStringNotContainsString('Marketing Consent</label>', $body);
    }

    public function test_terms_only_lead_is_not_labelled_as_legacy(): void
    {
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
        $this->db->getConnection()->prepare("INSERT INTO leads (data) VALUES (?)")->execute([
            json_encode(['name' => 'Terms Tina', '_consent' => ['terms' => true, 'captured_at' => '2026-01-01T00:00:00+00:00']]),
        ]);

        $body = (string)$controller->index((new RequestFactory())->createRequest('GET', '/admin'), (new ResponseFactory())->createResponse())->getBody();

        $this->assertStringContainsString('Marketing consent was not requested', $body);
        $this->assertStringNotContainsString('Captured before consent tracking', $body);
    }

    public function test_withdraw_consent_preserves_other_stored_json(): void
    {
        $controller = new AdminController($this->config, $this->db, $this->mockGeoProvider);
        $pdo = $this->db->getConnection();
        $pdo->prepare("INSERT INTO leads (data) VALUES (?)")->execute([
            '{"name":"Ola","meta":{},"_consent":{"marketing":true,"marketing_withdrawn_at":null,"captured_at":"2026-01-01T00:00:00+00:00"}}',
        ]);
        $id = $pdo->lastInsertId();

        $response = $controller->withdrawConsent(
            (new RequestFactory())->createRequest('POST', "/admin/leads/$id/consent/withdraw"),
            (new ResponseFactory())->createResponse(),
            ['id' => $id]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $stored = $pdo->query("SELECT data FROM leads WHERE id = $id")->fetchColumn();
        $this->assertStringContainsString('"meta":{}', $stored);
        $this->assertNotNull(json_decode($stored, true)['_consent']['marketing_withdrawn_at']);
    }
}
