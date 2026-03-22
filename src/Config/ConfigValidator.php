<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Config;

use InvalidArgumentException;

class ConfigValidator
{
    private const DEFAULT_CONFIG = [
        'base_path' => '',
        'database' => [
            'path' => null, // Will be set dynamically in validate()
        ],
        'form' => [
            'fields' => [
                'name' => ['label' => 'Name', 'required' => true, 'field_type' => 'text'],
                'email' => ['label' => 'E-mail', 'required' => true, 'field_type' => 'email'],
            ],
            'colors' => [
                'primary' => '#4F46E5',
                'background' => '#FFFFFF',
                'text' => '#111827',
                'error' => '#DC2626',
            ],
        ],
        'on_submit' => [
            'success_headline' => 'Thank you!',
            'success_message' => 'We will be in touch soon.',
            'social_links' => [
                'message' => 'Follow us:',
                'linkedin' => null,
                'twitter' => null,
            ],
            'ping_api' => [
                'enabled' => false,
                'api_endpoint' => null,
                'api_key' => null,
            ],
        ],
        'captcha' => [
            'enabled' => false,
            'recaptcha_site_key' => null,
            'recaptcha_secret_key' => null,
            'threshold' => 0.5,
        ],
        'admin' => [
            'email' => null,
            'password' => null, // Required
        ],
        'mail' => [
            'mailer' => 'sendmail',
            'host' => null,
            'port' => null,
            'username' => null,
            'password' => null,
            'encryption' => null,
            'from_address' => 'noreply@example.com',
            'from_name' => 'Easy Lead Capture',
        ],
    ];

    public static function validate(array $config): array
    {
        // Merge with defaults (recursive merge for some keys)
        $mergedConfig = self::arrayMergeRecursiveDistinct(self::DEFAULT_CONFIG, $config);

        // Set dynamic default for DB path if not provided
        if ($mergedConfig['database']['path'] === null) {
            $mergedConfig['database']['path'] = dirname(__DIR__, 2) . '/data/leads.db';
        }

        // 1. Required: admin.password
        if (empty($mergedConfig['admin']['password'])) {
            throw new InvalidArgumentException("Config error: 'admin.password' is required.");
        }

        // 2. Validate base_path
        if ($mergedConfig['base_path'] !== '' && !str_starts_with($mergedConfig['base_path'], '/')) {
            throw new InvalidArgumentException("Config error: 'base_path' must start with '/'.");
        }

        // 3. Validate form.fields
        if (!is_array($mergedConfig['form']['fields'])) {
            throw new InvalidArgumentException("Config error: 'form.fields' must be an array.");
        }
        foreach ($mergedConfig['form']['fields'] as $key => $field) {
            if (!is_array($field) || !isset($field['label'])) {
                throw new InvalidArgumentException("Config error: Field '{$key}' must have a 'label'.");
            }
        }

        // 4. Validate captcha
        if ($mergedConfig['captcha']['enabled']) {
            if (empty($mergedConfig['captcha']['recaptcha_site_key']) || empty($mergedConfig['captcha']['recaptcha_secret_key'])) {
                throw new InvalidArgumentException("Config error: 'captcha.recaptcha_site_key' and 'captcha.recaptcha_secret_key' are required when captcha is enabled.");
            }
        }

        // 5. Validate mail if admin.email is set
        if (!empty($mergedConfig['admin']['email'])) {
            $validMailers = ['smtp', 'sendmail', 'ses'];
            if (!in_array($mergedConfig['mail']['mailer'], $validMailers, true)) {
                throw new InvalidArgumentException("Config error: 'mail.mailer' must be one of: " . implode(', ', $validMailers));
            }

            if ($mergedConfig['mail']['mailer'] === 'smtp' || $mergedConfig['mail']['mailer'] === 'ses') {
                if (empty($mergedConfig['mail']['host'])) {
                    throw new InvalidArgumentException("Config error: 'mail.host' is required for smtp/ses mailer.");
                }
                if (empty($mergedConfig['mail']['port'])) {
                    throw new InvalidArgumentException("Config error: 'mail.port' is required for smtp/ses mailer.");
                }
            }
        }

        // 6. Validate ping_api
        if ($mergedConfig['on_submit']['ping_api']['enabled']) {
            $apiConfig = $mergedConfig['on_submit']['ping_api'];
            if (empty($apiConfig['api_endpoint']) || !filter_var($apiConfig['api_endpoint'], FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException("Config error: 'on_submit.ping_api.api_endpoint' must be a valid URL when enabled.");
            }
            if (empty($apiConfig['api_key'])) {
                throw new InvalidArgumentException("Config error: 'on_submit.ping_api.api_key' is required when enabled.");
            }
        }

        return $mergedConfig;
    }

    /**
     * @see https://www.php.net/manual/en/function.array-merge-recursive.php#92365
     */
    private static function arrayMergeRecursiveDistinct(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = self::arrayMergeRecursiveDistinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
