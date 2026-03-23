<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
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
<body class="bg-gray-50 font-sans text-[var(--text-color)]">
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <span class="text-xl font-bold text-[var(--primary-color)]">Easy Lead Capture</span>
                </div>
                <div class="flex items-center space-x-4">
                    <form action="<?= htmlspecialchars($base_path) ?>/admin/logout" method="POST">
                        <button type="submit" class="text-sm font-medium text-gray-500 hover:text-gray-700">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($title) ?></h1>
            <a href="<?= htmlspecialchars($base_path) ?>/admin/export?from=<?= htmlspecialchars($from ?? '') ?>&to=<?= htmlspecialchars($to ?? '') ?>" 
               class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-[var(--primary-color)] hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--primary-color)] mt-4 md:mt-0 transition-all">
                Export CSV
            </a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <form action="<?= htmlspecialchars($base_path) ?>/admin" method="GET" class="flex flex-wrap items-end gap-4">
                <div class="w-full sm:w-auto">
                    <label for="from" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                    <input type="date" name="from" id="from" value="<?= htmlspecialchars($from ?? '') ?>"
                           class="w-full sm:w-40 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-[var(--primary-color)] focus:border-[var(--primary-color)] outline-none text-sm">
                </div>
                <div class="w-full sm:w-auto">
                    <label for="to" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                    <input type="date" name="to" id="to" value="<?= htmlspecialchars($to ?? '') ?>"
                           class="w-full sm:w-40 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-[var(--primary-color)] focus:border-[var(--primary-color)] outline-none text-sm">
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm font-medium hover:bg-gray-800 transition-colors">Filter</button>
                    <a href="<?= htmlspecialchars($base_path) ?>/admin" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition-colors">Clear</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                            <?php foreach ($fields as $field): ?>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <?= htmlspecialchars($field['label']) ?>
                                </th>
                            <?php endforeach; ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($leads)): ?>
                            <tr>
                                <td colspan="<?= count($fields) + 2 ?>" class="px-6 py-12 text-center text-gray-500">
                                    No leads found.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($leads as $index => $lead): ?>
                            <tr class="<?= $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?>">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= ($totalLeads - (($page - 1) * 25) - $index) ?>
                                </td>
                                <?php foreach ($fields as $key => $field): ?>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php 
                                            $val = $lead['data'][$key] ?? '-';
                                            if (is_array($val)) {
                                                echo htmlspecialchars(implode(', ', $val));
                                            } else {
                                                echo htmlspecialchars((string)$val);
                                            }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($lead['created_at']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&from=<?= urlencode($from ?? '') ?>&to=<?= urlencode($to ?? '') ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&from=<?= urlencode($from ?? '') ?>&to=<?= urlencode($to ?? '') ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing
                                <span class="font-medium"><?= (($page - 1) * 25) + 1 ?></span>
                                to
                                <span class="font-medium"><?= min($page * 25, $totalLeads) ?></span>
                                of
                                <span class="font-medium"><?= $totalLeads ?></span>
                                results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>&from=<?= urlencode($from ?? '') ?>&to=<?= urlencode($to ?? '') ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                <?php endif; ?>
                                
                                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                    Page <?= $page ?> of <?= $totalPages ?>
                                </span>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?>&from=<?= urlencode($from ?? '') ?>&to=<?= urlencode($to ?? '') ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Next</span>
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
