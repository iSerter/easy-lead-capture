<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Controllers;

use Iserter\EasyLeadCapture\Database\Database;
use Iserter\EasyLeadCapture\Support\DeferredTaskRunner;
use Iserter\EasyLeadCapture\Mail\Mailer;
use Iserter\EasyLeadCapture\Mail\MailerFactory;
use Iserter\EasyLeadCapture\Support\ApiPinger;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SubmitController
{
    private array $config;
    private Database $db;
    private DeferredTaskRunner $deferred;
    private Mailer $mailer;
    private ApiPinger $apiPinger;

    public function __construct(array $config, Database $db, DeferredTaskRunner $deferred)
    {
        $this->config = $config;
        $this->db = $db;
        $this->deferred = $deferred;
        $this->mailer = new Mailer(MailerFactory::create($config));
        $this->apiPinger = new ApiPinger();
    }

    public function handle(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $input = $request->getParsedBody();
        if (empty($input)) {
            $input = json_decode((string)$request->getBody(), true) ?? [];
        }
        
        // CSRF validation
        $submittedToken = $input['_csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($submittedToken) || !hash_equals($sessionToken, $submittedToken)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Invalid request (CSRF token mismatch).'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $fields = $this->config['form']['fields'];
        $errors = [];
        $validatedData = [];

        foreach ($fields as $id => $field) {
            $value = $input[$id] ?? null;

            // Required check
            if (($field['required'] ?? false) && (empty($value) && $value !== '0')) {
                $errors[$id] = "{$field['label']} is required.";
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            // Type-specific validation
            $type = $field['field_type'] ?? 'text';
            if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$id] = "Please enter a valid email address.";
            } elseif ($type === 'multi_select') {
                if (!is_array($value)) {
                    $errors[$id] = "Invalid selection.";
                } else {
                    $options = array_keys($field['options'] ?? []);
                    foreach ($value as $val) {
                        if (!in_array($val, $options, true)) {
                            $errors[$id] = "Invalid selection.";
                            break;
                        }
                    }
                }
            }

            // Sanitization
            if (is_array($value)) {
                $validatedData[$id] = array_map(fn($v) => htmlspecialchars(trim((string)$v)), $value);
            } else {
                $validatedData[$id] = htmlspecialchars(trim((string)$value));
            }
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'errors' => $errors
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Store lead
        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO leads (data, ip_address, user_agent, captcha_score, created_at)
                VALUES (:data, :ip_address, :user_agent, :captcha_score, datetime('now'))
            ");

            $ipAddress = $this->getIpAddress($request);
            $userAgent = $request->getHeaderLine('User-Agent');
            $captchaScore = $request->getAttribute('captcha_score');

            $stmt->execute([
                ':data' => json_encode($validatedData),
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
                ':captcha_score' => $captchaScore,
            ]);

            // Success: regenerate CSRF token
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Send notification email (deferred)
            $this->deferred->defer(fn() => $this->mailer->sendLeadNotification($validatedData, $this->config));

            // Ping API (deferred)
            if ($this->config['on_submit']['ping_api']['enabled']) {
                $pingConfig = $this->config['on_submit']['ping_api'];
                $this->deferred->defer(fn() => $this->apiPinger->ping(
                    $pingConfig['api_endpoint'],
                    $pingConfig['api_key'],
                    array_merge($validatedData, ['created_at' => date('Y-m-d H:i:s')])
                ));
            }

            // Success response
            $response->getBody()->write(json_encode(['success' => true]));
            $response = $response->withHeader('Content-Type', 'application/json')
                                 ->withHeader('Connection', 'close');
            
            // Set Content-Length manually to help Connection: close
            $size = $response->getBody()->getSize();
            if ($size !== null) {
                $response = $response->withHeader('Content-Length', (string)$size);
            }

            return $response;
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'An error occurred while saving your information.'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    private function getIpAddress(Request $request): string
    {
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded) {
            return trim(explode(',', $forwarded)[0]);
        }

        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
