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
            <a href="<?= htmlspecialchars($base_path) ?>/admin/export?from=<?= htmlspecialchars($from ?? '') ?>&to=<?= htmlspecialchars($to ?? '') ?>&status=<?= htmlspecialchars($status ?? '') ?>" 
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
                <div class="w-full sm:w-auto">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="status" class="w-full sm:w-40 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-[var(--primary-color)] focus:border-[var(--primary-color)] outline-none text-sm">
                        <option value="">All Statuses</option>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>" <?= ($status === $s) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $s))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                            <?php if ($source_tracking['enabled']): ?>
                                <?php foreach ($source_tracking['params'] as $param): ?>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <?= htmlspecialchars(ucwords(str_replace(['utm_', '_'], ['', ' '], $param))) ?>
                                    </th>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($leads)): ?>
                            <tr>
                                <td colspan="<?= count($fields) + ($source_tracking['enabled'] ? count($source_tracking['params']) : 0) + 4 ?>" class="px-6 py-12 text-center text-gray-500">
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
                                <?php if ($source_tracking['enabled']): ?>
                                    <?php 
                                        $source = $lead['data']['_source'] ?? [];
                                        foreach ($source_tracking['params'] as $param): 
                                    ?>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <?= htmlspecialchars((string)($source[$param] ?? '-')) ?>
                                        </td>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <select onchange="updateStatus(<?= $lead['id'] ?>, this.value, this)" 
                                            class="status-badge px-2 py-1 rounded-full text-xs font-semibold cursor-pointer outline-none border-none
                                            <?= $lead['status'] === 'new' ? 'bg-gray-100 text-gray-800' : '' ?>
                                            <?= $lead['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800' : '' ?>
                                            <?= $lead['status'] === 'contacted' ? 'bg-purple-100 text-purple-800' : '' ?>
                                            <?= $lead['status'] === 'qualified' ? 'bg-green-100 text-green-800' : '' ?>
                                            <?= $lead['status'] === 'junk' ? 'bg-red-100 text-red-800' : '' ?>">
                                        <?php foreach ($statuses as $s): ?>
                                            <option value="<?= htmlspecialchars($s) ?>" <?= ($lead['status'] === $s) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $s))) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs overflow-hidden text-ellipsis whitespace-nowrap">
                                    <button onclick="editNotes(<?= $lead['id'] ?>, this.getAttribute('data-notes'))" 
                                            data-notes="<?= htmlspecialchars($lead['notes'] ?? '') ?>"
                                            class="text-[var(--primary-color)] hover:underline flex items-center gap-1 note-btn-<?= $lead['id'] ?>">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        <span id="note-text-<?= $lead['id'] ?>"><?= $lead['notes'] ? htmlspecialchars($lead['notes']) : 'Add Note' ?></span>
                                    </button>
                                </td>
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
                            <a href="?page=<?= $page - 1 ?>&from=<?= urlencode($from ?? '') ?>&to=<?= urlencode($to ?? '') ?>&status=<?= urlencode($status ?? '') ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&from=<?= urlencode($from ?? '') ?>&to=<?= urlencode($to ?? '') ?>&status=<?= urlencode($status ?? '') ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
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
                                    <a href="?page=<?= $page - 1 ?>&from=<?= urlencode($from ?? '') ?>&to=<?= urlencode($to ?? '') ?>&status=<?= urlencode($status ?? '') ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
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
                                    <a href="?page=<?= $page + 1 ?>&from=<?= urlencode($from ?? '') ?>&to=<?= urlencode($to ?? '') ?>&status=<?= urlencode($status ?? '') ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
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

    <!-- Notes Modal -->
    <div id="notes-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">Edit Lead Notes</h3>
                <button onclick="closeNotesModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="p-6">
                <input type="hidden" id="modal-lead-id">
                <textarea id="modal-lead-notes" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-[var(--primary-color)] focus:border-[var(--primary-color)] outline-none text-sm" placeholder="Internal notes about this lead..."></textarea>
            </div>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                <button onclick="closeNotesModal()" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-100 transition-colors">Cancel</button>
                <button onclick="saveNotes()" class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm font-medium hover:bg-gray-800 transition-colors">Save Notes</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-4 right-4 transform translate-y-20 opacity-0 transition-all duration-300 z-50">
        <div class="bg-gray-900 text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-3">
            <span id="toast-message"></span>
        </div>
    </div>

    <script>
        function showToast(message) {
            const toast = document.getElementById('toast');
            const toastMsg = document.getElementById('toast-message');
            toastMsg.textContent = message;
            toast.classList.remove('translate-y-20', 'opacity-0');
            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
            }, 3000);
        }

        async function updateStatus(id, status, select) {
            try {
                const response = await fetch('<?= $base_path ?>/admin/leads/' + id + '/status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'status=' + encodeURIComponent(status)
                });
                
                if (response.ok) {
                    showToast('Status updated');
                    // Update badge colors
                    select.classList.remove('bg-gray-100', 'text-gray-800', 'bg-blue-100', 'text-blue-800', 'bg-purple-100', 'text-purple-800', 'bg-green-100', 'text-green-800', 'bg-red-100', 'text-red-800');
                    
                    if (status === 'new') select.classList.add('bg-gray-100', 'text-gray-800');
                    else if (status === 'in_progress') select.classList.add('bg-blue-100', 'text-blue-800');
                    else if (status === 'contacted') select.classList.add('bg-purple-100', 'text-purple-800');
                    else if (status === 'qualified') select.classList.add('bg-green-100', 'text-green-800');
                    else if (status === 'junk') select.classList.add('bg-red-100', 'text-red-800');
                } else {
                    alert('Error updating status');
                }
            } catch (err) {
                console.error(err);
                alert('Network error');
            }
        }

        function editNotes(id, notes) {
            document.getElementById('modal-lead-id').value = id;
            document.getElementById('modal-lead-notes').value = notes || '';
            document.getElementById('notes-modal').classList.remove('hidden');
            document.getElementById('notes-modal').classList.add('flex');
        }

        function closeNotesModal() {
            document.getElementById('notes-modal').classList.add('hidden');
            document.getElementById('notes-modal').classList.remove('flex');
        }

        async function saveNotes() {
            const id = document.getElementById('modal-lead-id').value;
            const notes = document.getElementById('modal-lead-notes').value;
            
            try {
                const response = await fetch('<?= $base_path ?>/admin/leads/' + id + '/notes', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'notes=' + encodeURIComponent(notes)
                });
                
                if (response.ok) {
                    showToast('Notes saved');
                    const noteSpan = document.getElementById('note-text-' + id);
                    noteSpan.textContent = notes || 'Add Note';
                    
                    // Update the data-notes attribute for future edits
                    const btn = document.querySelector('.note-btn-' + id);
                    if (btn) {
                        btn.setAttribute('data-notes', notes);
                    }
                    
                    closeNotesModal();
                } else {
                    alert('Error saving notes');
                }
            } catch (err) {
                console.error(err);
                alert('Network error');
            }
        }
    </script>
</body>
</html>
