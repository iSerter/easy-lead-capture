<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Support;

class ApiPinger
{
    public function ping(string $endpoint, string $apiKey, array $leadData): void
    {
        $ch = curl_init($endpoint);
        if ($ch === false) {
            return;
        }

        $payload = json_encode($leadData);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'Content-Length: ' . strlen($payload)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        curl_exec($ch);
        curl_close($ch);
    }
}
