<?php

declare(strict_types=1);

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);

$fields = $config['form']['fields'] ?? [];
$colors = $config['form']['colors'] ?? [];
$accentColor = $colors['primary'] ?? '#4F46E5';
$brandName = $config['confirmation_email']['from_name'] ?? $config['mail']['from_name'] ?? 'Easy Lead Capture';
$siteUrl = $config['site_url'] ?? $config['app_url'] ?? null;

if ($siteUrl === null && !empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $siteUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . ($config['base_path'] ?? '');
}

$leadName = null;
if (isset($leadData['name']) && is_scalar($leadData['name']) && trim((string)$leadData['name']) !== '') {
    $leadName = (string)$leadData['name'];
} else {
    foreach ($fields as $id => $field) {
        $label = strtolower((string)($field['label'] ?? ''));
        if (!str_contains((string)$id, 'name') && !str_contains($label, 'name')) {
            continue;
        }

        $value = $leadData[$id] ?? null;
        if (is_scalar($value) && trim((string)$value) !== '') {
            $leadName = (string)$value;
            break;
        }
    }
}

$submittedFields = [];
foreach ($fields as $id => $field) {
    if (!array_key_exists($id, $leadData)) {
        continue;
    }

    $value = $leadData[$id];
    if (is_array($value)) {
        $value = implode('; ', $value);
    }

    $submittedFields[] = [
        'label' => (string)($field['label'] ?? $id),
        'value' => (string)$value,
    ];
}

$greeting = $leadName === null ? "You're in." : "You're in, " . $leadName . ".";
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape((string)($config['confirmation_email']['subject'] ?? 'Thank you')) ?></title>
</head>
<body style="margin: 0; padding: 0; background: #f6f7f9; color: #111827; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width: 100%; border-collapse: collapse; background: #f6f7f9;">
        <tr>
            <td align="center" style="padding: 48px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width: 100%; max-width: 600px; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 0 0 16px 0; color: #6b7280; font-size: 14px; line-height: 20px;">
                            <?= $escape($brandName) ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 18px; box-shadow: 0 16px 40px rgba(17, 24, 39, 0.08); padding: 40px;">
                            <div style="width: 42px; height: 4px; background: <?= $escape($accentColor) ?>; border-radius: 999px; margin: 0 0 28px 0;"></div>
                            <h1 style="margin: 0 0 16px 0; color: #111827; font-size: 28px; line-height: 36px; font-weight: 700; letter-spacing: 0;">
                                <?= $escape($greeting) ?>
                            </h1>
                            <p style="margin: 0; color: #374151; font-size: 16px; line-height: 26px;">
                                Thanks for joining us. We received your details and will be in touch soon with the next step.
                            </p>

                            <?php if (!empty($submittedFields)): ?>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-top: 32px;">
                                    <tr>
                                        <td style="padding: 0 0 12px 0; color: #111827; font-size: 14px; line-height: 20px; font-weight: 700;">
                                            Your details
                                        </td>
                                    </tr>
                                    <?php foreach ($submittedFields as $field): ?>
                                        <tr>
                                            <td style="padding: 14px 0; border-top: 1px solid #eef0f3;">
                                                <div style="margin: 0 0 4px 0; color: #6b7280; font-size: 12px; line-height: 18px; text-transform: uppercase;">
                                                    <?= $escape($field['label']) ?>
                                                </div>
                                                <div style="color: #111827; font-size: 15px; line-height: 22px;">
                                                    <?= nl2br($escape($field['value'])) ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            <?php endif; ?>

                            <p style="margin: 32px 0 0 0; color: #4b5563; font-size: 15px; line-height: 24px;">
                                We appreciate you taking the time to reach out.
                            </p>
                            <p style="margin: 18px 0 0 0; color: #111827; font-size: 15px; line-height: 24px;">
                                &mdash; The <?= $escape($brandName) ?> team
                            </p>
                        </td>
                    </tr>
                    <?php if ($siteUrl !== null): ?>
                        <tr>
                            <td style="padding: 18px 4px 0 4px; color: #8a94a6; font-size: 12px; line-height: 18px; text-align: center;">
                                You're receiving this because you signed up at <?= $escape((string)$siteUrl) ?>.
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
