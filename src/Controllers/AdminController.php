<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Controllers;

use Iserter\EasyLeadCapture\Database\Database;
use Iserter\EasyLeadCapture\IpGeo\IpApiCoProvider;
use Iserter\EasyLeadCapture\IpGeo\IpGeoProvider;
use Iserter\EasyLeadCapture\IpGeo\IpSageProvider;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminController
{
    private const CONSENT_FILTERS = ['yes' => 'Opted in', 'no' => 'Not opted in', 'unknown' => 'Unknown'];

    private array $config;
    private Database $db;
    private IpGeoProvider $ipGeoProvider;

    public function __construct(array $config, Database $db, IpGeoProvider $ipGeoProvider)
    {
        $this->config = $config;
        $this->db = $db;
        $this->ipGeoProvider = $ipGeoProvider;
    }

    public function loginForm(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $error = $queryParams['error'] ?? null;
        $locked = $queryParams['locked'] ?? null;

        $viewData = [
            'title' => 'Admin Login',
            'base_path' => $this->config['base_path'],
            'colors' => $this->config['form']['colors'],
            'error' => $error,
            'locked' => $locked,
        ];

        ob_start();
        extract($viewData);
        include dirname(__DIR__) . '/Views/admin/login.php';
        $content = ob_get_clean();

        ob_start();
        extract($viewData);
        include dirname(__DIR__) . '/Views/layouts/base.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response;
    }

    public function login(Request $request, Response $response): Response
    {
        $ipAddress = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $data = $request->getParsedBody();
        $password = $data['password'] ?? '';

        // Rate limit check
        $stmt = $this->db->getConnection()->prepare("
            SELECT COUNT(*) 
            FROM login_attempts 
            WHERE ip_address = :ip AND attempted_at > datetime('now', '-15 minutes')
        ");
        $stmt->execute([':ip' => $ipAddress]);
        $attempts = (int)$stmt->fetchColumn();

        if ($attempts >= 5) {
            return $response
                ->withHeader('Location', $this->config['base_path'] . '/admin/login?locked=1')
                ->withStatus(302);
        }

        // Verify password
        $configPassword = $this->config['admin']['password'];
        $isHashed = str_starts_with($configPassword, '$2y$') || str_starts_with($configPassword, '$argon2');
        $isValid = $isHashed ? password_verify($password, $configPassword) : hash_equals($configPassword, $password);

        if ($isValid) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = (new \DateTime('+24 hours'))->format('Y-m-d H:i:s');

            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO admin_sessions (token, expires_at) VALUES (:token, :expires_at)
            ");
            $stmt->execute([
                ':token' => $token,
                ':expires_at' => $expiresAt
            ]);

            $cookieHeader = "elc_session=" . $token . "; Path=" . ($this->config['base_path'] ?: '/') . "; HttpOnly; SameSite=Lax";
            if ($request->getUri()->getScheme() === 'https') {
                $cookieHeader .= "; Secure";
            }

            return $response
                ->withHeader('Set-Cookie', $cookieHeader)
                ->withHeader('Location', $this->config['base_path'] . '/admin')
                ->withStatus(302);
        }

        // Failure
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO login_attempts (ip_address) VALUES (:ip)
        ");
        $stmt->execute([':ip' => $ipAddress]);

        return $response
            ->withHeader('Location', $this->config['base_path'] . '/admin/login?error=1')
            ->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        $cookies = $request->getCookieParams();
        $token = $cookies['elc_session'] ?? '';

        if ($token) {
            $stmt = $this->db->getConnection()->prepare("DELETE FROM admin_sessions WHERE token = :token");
            $stmt->execute([':token' => $token]);
        }

        $cookieHeader = "elc_session=; Path=" . ($this->config['base_path'] ?: '/') . "; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly; SameSite=Lax";
        if ($request->getUri()->getScheme() === 'https') {
            $cookieHeader .= "; Secure";
        }

        return $response
            ->withHeader('Set-Cookie', $cookieHeader)
            ->withHeader('Location', $this->config['base_path'] . '/admin/login')
            ->withStatus(302);
    }

    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $page = (int)($queryParams['page'] ?? 1);
        $from = $queryParams['from'] ?? null;
        $to = $queryParams['to'] ?? null;
        $status = $queryParams['status'] ?? null;
        $consent = self::normalizeConsentFilter($queryParams['consent'] ?? null);
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildFilters($queryParams);

        // Count total
        $countStmt = $this->db->getConnection()->prepare("SELECT COUNT(*) FROM leads $whereSql");
        $countStmt->execute($params);
        $totalLeads = (int)$countStmt->fetchColumn();
        $totalPages = (int)ceil($totalLeads / $perPage);

        // Fetch leads
        $stmt = $this->db->getConnection()->prepare("
            SELECT * FROM leads $whereSql 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $leads = $stmt->fetchAll();

        foreach ($leads as &$lead) {
            $lead['data'] = json_decode($lead['data'], true);
            $lead['consent_state'] = self::marketingConsentState($lead['data'] ?? []);
        }
        unset($lead);

        // Decided across all leads (not just this page) so the column and filter stay stable while paging
        $showConsent = $this->config['consent']['marketing']['enabled']
            || $consent !== null
            || $this->db->getConnection()
                ->query("SELECT 1 FROM leads WHERE json_extract(data, '$._consent.marketing') IS NOT NULL LIMIT 1")
                ->fetchColumn() !== false;

        $viewData = [
            'title' => 'Admin Dashboard',
            'base_path' => $this->config['base_path'],
            'colors' => $this->config['form']['colors'],
            'leads' => $leads,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalLeads' => $totalLeads,
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'statuses' => ['new', 'in_progress', 'contacted', 'qualified', 'junk'],
            'consent' => $consent,
            'showConsent' => $showConsent,
            'consentFilters' => self::CONSENT_FILTERS,
            'fields' => $this->config['form']['fields'],
            'source_tracking' => $this->config['source_tracking'],
        ];

        ob_start();
        extract($viewData);
        include dirname(__DIR__) . '/Views/admin/dashboard.php';
        $content = ob_get_clean();

        $response->getBody()->write($content);
        return $response;
    }

    public function export(Request $request, Response $response): Response
    {
        [$whereSql, $params] = $this->buildFilters($request->getQueryParams());

        $stmt = $this->db->getConnection()->prepare("
            SELECT * FROM leads $whereSql 
            ORDER BY created_at ASC
        ");
        $stmt->execute($params);

        $filename = 'leads-' . date('Y-m-d') . '.csv';

        // Set headers for CSV download
        $response = $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');

        $body = $response->getBody();

        // UTF-8 BOM for Excel compatibility
        $body->write("\xEF\xBB\xBF");

        $fields = $this->config['form']['fields'];
        $sourceTracking = $this->config['source_tracking'];
        $header = [];
        foreach ($fields as $field) {
            $header[] = $field['label'];
        }

        if ($sourceTracking['enabled']) {
            foreach ($sourceTracking['params'] as $param) {
                // Convert utm_source to Source, etc.
                $label = ucwords(str_replace(['utm_', '_'], ['', ' '], $param));
                $header[] = $label;
            }
        }

        $header[] = 'Marketing Consent';
        $header[] = 'Consent Text';
        $header[] = 'Consent Date';
        $header[] = 'Consent Withdrawn At';
        $header[] = 'Status';
        $header[] = 'Notes';
        $header[] = 'Country Code';
        $header[] = 'Region';
        $header[] = 'City';
        $header[] = 'Date';

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $header, escape: '\\');

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $data = json_decode($row['data'], true);
            $csvRow = [];
            foreach ($fields as $key => $field) {
                $val = $data[$key] ?? '';
                if (is_array($val)) {
                    $val = implode('; ', $val);
                }
                $csvRow[] = $val;
            }

            if ($sourceTracking['enabled']) {
                $source = $data['_source'] ?? [];
                foreach ($sourceTracking['params'] as $param) {
                    $csvRow[] = $source[$param] ?? '';
                }
            }

            $leadConsent = $data['_consent'] ?? [];
            $csvRow[] = match (self::marketingConsentState($data)) {
                'opted_in' => 'Yes',
                'withdrawn' => 'Withdrawn',
                'declined' => 'No',
                default => 'Unknown',
            };
            $csvRow[] = $leadConsent['marketing_text'] ?? '';
            $csvRow[] = $leadConsent['captured_at'] ?? '';
            $csvRow[] = $leadConsent['marketing_withdrawn_at'] ?? '';
            $csvRow[] = $row['status'];
            $csvRow[] = $row['notes'];
            $csvRow[] = $row['ip_country_code'] ?? '';
            $csvRow[] = $row['ip_region'] ?? '';
            $csvRow[] = $row['ip_city'] ?? '';
            $csvRow[] = $row['created_at'];
            fputcsv($output, $csvRow, escape: '\\');
        }

        rewind($output);
        $body->write(stream_get_contents($output));
        fclose($output);

        return $response;
    }

    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $data = $request->getParsedBody();
        $status = $data['status'] ?? '';
        $validStatuses = ['new', 'in_progress', 'contacted', 'qualified', 'junk'];

        if (!in_array($status, $validStatuses)) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => 'Invalid status']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $stmt = $this->db->getConnection()->prepare("UPDATE leads SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function updateNotes(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $data = $request->getParsedBody();
        $notes = trim($data['notes'] ?? '');

        $stmt = $this->db->getConnection()->prepare("UPDATE leads SET notes = :notes WHERE id = :id");
        $stmt->execute([':notes' => $notes, ':id' => $id]);

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function withdrawConsent(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];

        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("SELECT 1 FROM leads WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($stmt->fetchColumn() === false) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => 'Lead not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Check-and-set in one statement; json_set leaves the rest of the stored JSON untouched
        $withdrawnAt = gmdate('c');
        $updateStmt = $pdo->prepare("
            UPDATE leads
            SET data = json_set(data, '$._consent.marketing_withdrawn_at', :withdrawn_at)
            WHERE id = :id
              AND json_extract(data, '$._consent.marketing') = 1
              AND json_extract(data, '$._consent.marketing_withdrawn_at') IS NULL
        ");
        $updateStmt->execute([':withdrawn_at' => $withdrawnAt, ':id' => $id]);

        if ($updateStmt->rowCount() === 0) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => 'Lead has no active marketing consent']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $response->getBody()->write(json_encode(['success' => true, 'withdrawn_at' => $withdrawnAt]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Returns 'opted_in', 'declined', 'withdrawn', or 'unknown' (lead captured before consent tracking).
     */
    public static function marketingConsentState(array $leadData): string
    {
        $consent = $leadData['_consent'] ?? null;
        if (!is_array($consent) || !array_key_exists('marketing', $consent)) {
            return 'unknown';
        }
        if (!empty($consent['marketing_withdrawn_at'])) {
            return 'withdrawn';
        }
        return $consent['marketing'] === true ? 'opted_in' : 'declined';
    }

    private static function normalizeConsentFilter(mixed $value): ?string
    {
        return is_string($value) && isset(self::CONSENT_FILTERS[$value]) ? $value : null;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function buildFilters(array $queryParams): array
    {
        $from = $queryParams['from'] ?? null;
        $to = $queryParams['to'] ?? null;
        $status = $queryParams['status'] ?? null;
        $consent = self::normalizeConsentFilter($queryParams['consent'] ?? null);

        $where = [];
        $params = [];
        if ($from) {
            $where[] = "created_at >= :from";
            $params[':from'] = $from . ' 00:00:00';
        }
        if ($to) {
            $where[] = "created_at <= :to";
            $params[':to'] = $to . ' 23:59:59';
        }
        if ($status) {
            $where[] = "status = :status";
            $params[':status'] = $status;
        }

        // Fixed SQL fragments: json_extract returns integers for JSON booleans, so bound text params would not match
        $consentFilters = [
            'yes' => "(json_extract(data, '$._consent.marketing') = 1 AND json_extract(data, '$._consent.marketing_withdrawn_at') IS NULL)",
            'no' => "(json_extract(data, '$._consent.marketing') = 0 OR json_extract(data, '$._consent.marketing_withdrawn_at') IS NOT NULL)",
            'unknown' => "json_extract(data, '$._consent.marketing') IS NULL",
        ];
        if ($consent !== null) {
            $where[] = $consentFilters[$consent];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return [$whereSql, $params];
    }

    public function lookupGeo(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];

        $stmt = $this->db->getConnection()->prepare("SELECT id, ip_address FROM leads WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $lead = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$lead) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => 'Lead not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        if (empty($lead['ip_address'])) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => 'No IP address']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $queryParams = $request->getQueryParams();
        $providerOverride = $queryParams['provider'] ?? null;

        $provider = $this->ipGeoProvider;
        $ipGeoConfig = $this->config['ip_geo'] ?? [];
        if ($providerOverride === 'ipapico') {
            $provider = new IpApiCoProvider($ipGeoConfig['ipapico_api_key'] ?? null);
        } elseif ($providerOverride === 'ipsage') {
            $provider = new IpSageProvider($ipGeoConfig['ipsage_endpoint'] ?? 'http://127.0.0.1:8040');
        }

        $geo = $provider->lookup($lead['ip_address']);

        if ($geo === null) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => 'Geolocation failed']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(502);
        }

        $updateStmt = $this->db->getConnection()->prepare("
            UPDATE leads SET ip_country_code = :country_code, ip_region = :region, ip_city = :city WHERE id = :id
        ");
        $updateStmt->execute([
            ':country_code' => $geo['country_code'],
            ':region' => $geo['region'],
            ':city' => $geo['city'],
            ':id' => $id,
        ]);

        $response->getBody()->write(json_encode([
            'success' => true,
            'country_code' => $geo['country_code'],
            'region' => $geo['region'],
            'city' => $geo['city'],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
