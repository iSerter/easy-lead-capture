<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Controllers;

use Iserter\EasyLeadCapture\Database\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminController
{
    private array $config;
    private Database $db;

    public function __construct(array $config, Database $db)
    {
        $this->config = $config;
        $this->db = $db;
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
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

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

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

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
        }

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
        $queryParams = $request->getQueryParams();
        $from = $queryParams['from'] ?? null;
        $to = $queryParams['to'] ?? null;
        $status = $queryParams['status'] ?? null;

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

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

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

        $header[] = 'Status';
        $header[] = 'Notes';
        $header[] = 'Date';

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $header);

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

            $csvRow[] = $row['status'];
            $csvRow[] = $row['notes'];
            $csvRow[] = $row['created_at'];
            fputcsv($output, $csvRow);
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
}
