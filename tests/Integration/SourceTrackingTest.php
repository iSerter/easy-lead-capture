<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Iserter\EasyLeadCapture\Config\ConfigValidator;
use Iserter\EasyLeadCapture\Database\Database;
use Iserter\EasyLeadCapture\Controllers\FormController;
use Iserter\EasyLeadCapture\Controllers\SubmitController;
use Iserter\EasyLeadCapture\Support\DeferredTaskRunner;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

class SourceTrackingTest extends TestCase
{
    private string $tempDb;
    private array $config;
    private Database $db;

    protected function setUp(): void
    {
        $this->tempDb = __DIR__ . '/test_source.db';
        $this->config = ConfigValidator::validate(['admin' => ['password' => 'secret']]);
        $this->db = new Database($this->tempDb);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDb)) {
            @unlink($this->tempDb);
            @unlink($this->tempDb . '-shm');
            @unlink($this->tempDb . '-wal');
        }
        $_SESSION = [];
    }

    public function test_it_captures_utm_params_on_form_load_and_persists_on_submit(): void
    {
        $formController = new FormController($this->config);
        
        // 1. GET /form with UTM params
        $request = (new RequestFactory())->createRequest('GET', '/form');
        $request = $request->withQueryParams([
            'utm_source' => 'email',
            'utm_medium' => 'newsletter',
            'utm_campaign' => 'summer_sale',
            'ignored_param' => 'foo'
        ]);
        
        $formController->show($request, (new ResponseFactory())->createResponse());
        
        $this->assertArrayHasKey('elc_source', $_SESSION);
        $this->assertEquals('email', $_SESSION['elc_source']['utm_source']);
        $this->assertEquals('newsletter', $_SESSION['elc_source']['utm_medium']);
        $this->assertArrayNotHasKey('ignored_param', $_SESSION['elc_source']);

        // 2. POST /submit
        $_SESSION['csrf_token'] = 'test_token';
        $deferred = new DeferredTaskRunner();
        $submitController = new SubmitController($this->config, $this->db, $deferred);

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            '_csrf_token' => 'test_token'
        ];

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode($data)));
        $request = $request->withHeader('Content-Type', 'application/json');

        $submitController->handle($request, (new ResponseFactory())->createResponse());

        // Verify session cleared
        $this->assertArrayNotHasKey('elc_source', $_SESSION);

        // Verify database
        $pdo = $this->db->getConnection();
        $lead = $pdo->query("SELECT * FROM leads LIMIT 1")->fetch();
        $storedData = json_decode($lead['data'], true);
        
        $this->assertArrayHasKey('_source', $storedData);
        $this->assertEquals('email', $storedData['_source']['utm_source']);
        $this->assertEquals('newsletter', $storedData['_source']['utm_medium']);
        $this->assertEquals('summer_sale', $storedData['_source']['utm_campaign']);
    }

    public function test_it_ignores_params_when_disabled_in_config(): void
    {
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'source_tracking' => ['enabled' => false]
        ]);
        $formController = new FormController($config);
        
        $request = (new RequestFactory())->createRequest('GET', '/form');
        $request = $request->withQueryParams(['utm_source' => 'email']);
        
        $formController->show($request, (new ResponseFactory())->createResponse());
        
        $this->assertArrayNotHasKey('elc_source', $_SESSION);
    }

    public function test_it_does_not_store_source_if_no_params_present(): void
    {
        $_SESSION['csrf_token'] = 'test_token';
        $deferred = new DeferredTaskRunner();
        $submitController = new SubmitController($this->config, $this->db, $deferred);

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            '_csrf_token' => 'test_token'
        ];

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode($data)));
        $request = $request->withHeader('Content-Type', 'application/json');

        $submitController->handle($request, (new ResponseFactory())->createResponse());

        $pdo = $this->db->getConnection();
        $lead = $pdo->query("SELECT * FROM leads LIMIT 1")->fetch();
        $storedData = json_decode($lead['data'], true);
        
        $this->assertArrayNotHasKey('_source', $storedData);
    }

    public function test_it_does_not_trust_source_in_post_body(): void
    {
        $_SESSION['csrf_token'] = 'test_token';
        // Ensure session is empty
        unset($_SESSION['elc_source']);

        $deferred = new DeferredTaskRunner();
        $submitController = new SubmitController($this->config, $this->db, $deferred);

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            '_csrf_token' => 'test_token',
            '_source' => ['utm_source' => 'malicious']
        ];

        $request = (new RequestFactory())->createRequest('POST', '/submit');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode($data)));
        $request = $request->withHeader('Content-Type', 'application/json');

        $submitController->handle($request, (new ResponseFactory())->createResponse());

        $pdo = $this->db->getConnection();
        $lead = $pdo->query("SELECT * FROM leads LIMIT 1")->fetch();
        $storedData = json_decode($lead['data'], true);
        
        // _source should not be present because it wasn't in the session
        $this->assertArrayNotHasKey('_source', $storedData);
    }
}
