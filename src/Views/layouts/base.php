<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Lead Capture Form') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($base_path) ?>/assets/styles.css">
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($colors['primary'] ?? '#4F46E5') ?>;
            --background-color: <?= htmlspecialchars($colors['background'] ?? '#FFFFFF') ?>;
            --text-color: <?= htmlspecialchars($colors['text'] ?? '#111827') ?>;
            --error-color: <?= htmlspecialchars($colors['error'] ?? '#DC2626') ?>;
        }
    </style>
</head>
<body class="bg-transparent font-sans text-[var(--text-color)]">
    <div class="min-h-screen flex items-center justify-center p-4">
        <?= $content ?>
    </div>
</body>
</html>
