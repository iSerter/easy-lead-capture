<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\IpGeo;

class IpSageProvider implements IpGeoProvider
{
    private string $endpoint;

    public function __construct(string $endpoint = 'http://127.0.0.1:8040')
    {
        $this->endpoint = rtrim($endpoint, '/');
    }

    public function lookup(string $ip): ?array
    {
        $url = $this->endpoint . '/lookup/' . urlencode($ip);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 3,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !($data['success'] ?? false) || !isset($data['data'])) {
            return null;
        }

        $geo = $data['data'];
        return [
            'country_code' => $geo['country_code'] ?? null,
            'region' => $geo['region'] ?? null,
            'city' => $geo['city'] ?? null,
        ];
    }
}
