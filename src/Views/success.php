<div id="success-view" class="hidden text-center py-8 px-4 animate-fade-in">
    <div class="flex justify-center mb-6">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center text-green-600">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
    </div>

    <h2 class="text-2xl font-bold mb-3 text-gray-900"><?= htmlspecialchars($on_submit['success_headline'] ?? 'Thank you!') ?></h2>
    <p class="text-gray-600 mb-8"><?= htmlspecialchars($on_submit['success_message'] ?? 'We will be in touch soon.') ?></p>

    <?php 
    $social = $on_submit['social_links'] ?? [];
    $hasLinkedin = !empty($social['linkedin']);
    $hasTwitter = !empty($social['twitter']);
    ?>

    <?php if ($hasLinkedin || $hasTwitter): ?>
        <div class="pt-6 border-t border-gray-100">
            <p class="text-sm font-medium text-gray-500 mb-4"><?= htmlspecialchars($social['message'] ?? 'Follow us:') ?></p>
            <div class="flex justify-center space-x-4">
                <?php if ($hasLinkedin): ?>
                    <a href="<?= htmlspecialchars($social['linkedin']) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center space-x-2 py-2 px-4 rounded-full bg-[#0A66C2] text-white hover:brightness-110 transition-all duration-200 shadow-sm">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                        </svg>
                        <span class="text-sm font-semibold">LinkedIn</span>
                    </a>
                <?php endif; ?>

                <?php if ($hasTwitter): ?>
                    <a href="<?= htmlspecialchars($social['twitter']) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center space-x-2 py-2 px-4 rounded-full bg-black text-white hover:brightness-110 transition-all duration-200 shadow-sm">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                        </svg>
                        <span class="text-sm font-semibold">X</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fadeIn 0.5s ease-out forwards;
    }
</style>
