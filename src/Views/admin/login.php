<div class="w-full max-w-md bg-[var(--background-color)] rounded-xl shadow-lg p-8 border border-gray-100">
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-[var(--text-color)]"><?= htmlspecialchars($title) ?></h1>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-50 border border-[var(--error-color)] text-[var(--error-color)] rounded-lg text-sm">
            Invalid password. Please try again.
        </div>
    <?php endif; ?>

    <?php if ($locked): ?>
        <div class="mb-6 p-4 bg-red-50 border border-[var(--error-color)] text-[var(--error-color)] rounded-lg text-sm">
            Too many failed attempts. Please try again in 15 minutes.
        </div>
    <?php endif; ?>

    <form action="<?= htmlspecialchars($base_path) ?>/admin/login" method="POST" class="space-y-6">
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
            <input type="password" name="password" id="password" required
                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-[var(--primary-color)] focus:border-[var(--primary-color)] outline-none transition-all">
        </div>

        <button type="submit"
                class="w-full py-3 px-4 bg-[var(--primary-color)] text-white font-semibold rounded-lg hover:opacity-90 transition-all shadow-md focus:ring-2 focus:ring-offset-2 focus:ring-[var(--primary-color)] outline-none">
            Sign In
        </button>
    </form>
</div>
