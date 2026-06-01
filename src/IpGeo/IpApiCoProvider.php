<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\IpGeo;

class IpApiCoProvider implements IpGeoProvider
{
    private ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey;
    }

    public function lookup(string $ip): ?array
    {
        $url = 'https://ipapi.co/' . urlencode($ip) . '/json/';
        if ($this->apiKey !== null) {
            $url .= '?key=' . urlencode($this->apiKey);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => "User-Agent: EasyLeadCapture/1.0\r\n",
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['error'] ?? false)) {
            return null;
        }

        return [
            'country_code' => $data['country_code'] ?? null,
            'region' => $data['region'] ?? null,
            'city' => $data['city'] ?? null,
        ];
    }
}
