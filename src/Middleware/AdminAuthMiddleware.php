<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Middleware;

use Iserter\EasyLeadCapture\Database\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class AdminAuthMiddleware implements MiddlewareInterface
{
    private Database $db;
    private string $basePath;

    public function __construct(Database $db, string $basePath)
    {
        $this->db = $db;
        $this->basePath = $basePath;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $cookies = $request->getCookieParams();
        $token = $cookies['elc_session'] ?? '';

        if (!$token) {
            return $this->redirect();
        }

        $stmt = $this->db->getConnection()->prepare("
            SELECT COUNT(*) FROM admin_sessions 
            WHERE token = :token AND expires_at > datetime('now')
        ");
        $stmt->execute([':token' => $token]);
        $isValid = (bool)$stmt->fetchColumn();

        if (!$isValid) {
            return $this->redirect();
        }

        // Cleanup expired sessions occasionally (1-in-10)
        if (random_int(1, 10) === 1) {
            $this->db->getConnection()->exec("DELETE FROM admin_sessions WHERE expires_at <= datetime('now')");
        }

        return $handler->handle($request);
    }

    private function redirect(): Response
    {
        $response = new SlimResponse();
        return $response
            ->withHeader('Location', $this->basePath . '/admin/login')
            ->withStatus(302);
    }
}
