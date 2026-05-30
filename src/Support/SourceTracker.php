<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Support;

class SourceTracker
{
    /**
     * Extracts and sanitizes allowed parameters from the query string.
     */
    public static function extractFromQuery(array $queryParams, array $allowedParams): array
    {
        $captured = [];

        foreach ($allowedParams as $param) {
            $value = $queryParams[$param] ?? null;

            if ($value !== null && $value !== '') {
                // Sanitize: trim, truncate to 255, and htmlspecialchars
                $cleanValue = trim((string)$value);
                
                if ($cleanValue !== '') {
                    // Truncate to 255 chars as per security notes
                    $cleanValue = mb_substr($cleanValue, 0, 255);
                    $captured[$param] = htmlspecialchars($cleanValue);
                }
            }
        }

        return $captured;
    }

    /**
     * Merges source parameters into the lead data JSON structure.
     */
    public static function mergeIntoLeadData(array $validatedFormData, array $sourceParams): array
    {
        if (empty($sourceParams)) {
            return $validatedFormData;
        }

        $validatedFormData['_source'] = $sourceParams;
        return $validatedFormData;
    }
}
