<?php if ($captcha['enabled']): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($captcha['recaptcha_site_key']) ?>"></script>
<?php endif; ?>

<div id="form-container" class="w-full max-w-md bg-[var(--background-color)] rounded-xl shadow-lg border border-gray-100 overflow-hidden">
    <div id="form-view" class="p-8">
        <?php if (isset($form['logo_url'])): ?>
            <div class="flex justify-center mb-6">
                <img src="<?= htmlspecialchars($form['logo_url']) ?>" alt="Logo" class="max-h-16 object-contain">
            </div>
        <?php endif; ?>

        <?php if (isset($form['headline'])): ?>
            <h1 class="text-2xl font-bold text-center mb-2"><?= htmlspecialchars($form['headline']) ?></h1>
        <?php endif; ?>

        <?php if (isset($form['intro_text'])): ?>
            <p class="text-gray-600 text-center mb-8"><?= htmlspecialchars($form['intro_text']) ?></p>
        <?php endif; ?>

        <form id="lead-form" class="space-y-5" novalidate>
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <?php foreach ($fields as $id => $field): ?>
                <div class="space-y-1.5">
                    <label for="<?= htmlspecialchars($id) ?>" class="block text-sm font-medium">
                        <?= htmlspecialchars($field['label']) ?>
                        <?php if ($field['required'] ?? false): ?>
                            <span class="text-[var(--error-color)]">*</span>
                        <?php endif; ?>
                    </label>

                    <?php if (($field['field_type'] ?? 'text') === 'textarea'): ?>
                        <textarea
                            id="<?= htmlspecialchars($id) ?>"
                            name="<?= htmlspecialchars($id) ?>"
                            rows="4"
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-[var(--primary-color)] focus:border-[var(--primary-color)] outline-none transition-all duration-200"
                            <?= ($field['required'] ?? false) ? 'required' : '' ?>
                        ></textarea>
                    <?php elseif (($field['field_type'] ?? 'text') === 'multi_select'): ?>
                        <div class="space-y-2 mt-1">
                            <?php foreach (($field['options'] ?? []) as $option_id => $option_label): ?>
                                <label class="flex items-center space-x-3 cursor-pointer group">
                                    <input type="checkbox" name="<?= htmlspecialchars($id) ?>[]" value="<?= htmlspecialchars($option_id) ?>" class="w-5 h-5 rounded border-gray-300 text-[var(--primary-color)] focus:ring-[var(--primary-color)]">
                                    <span class="text-sm group-hover:text-gray-900 transition-colors"><?= htmlspecialchars($option_label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <input
                            type="<?= htmlspecialchars($field['field_type'] ?? 'text') ?>"
                            id="<?= htmlspecialchars($id) ?>"
                            name="<?= htmlspecialchars($id) ?>"
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-[var(--primary-color)] focus:border-[var(--primary-color)] outline-none transition-all duration-200"
                            <?= ($field['required'] ?? false) ? 'required' : '' ?>
                        >
                    <?php endif; ?>
                    
                    <div class="error-message text-xs text-[var(--error-color)] min-h-[1rem] opacity-0 transition-opacity duration-200"></div>
                </div>
            <?php endforeach; ?>

            <div id="form-error" class="hidden p-3 text-sm text-[var(--error-color)] bg-red-50 rounded-lg mb-4"></div>

            <button type="submit" class="w-full py-3.5 px-4 bg-[var(--primary-color)] text-white font-semibold rounded-lg hover:brightness-110 active:scale-[0.98] transition-all duration-200 shadow-md flex items-center justify-center space-x-2">
                <span>Submit</span>
                <svg class="animate-spin h-5 w-5 text-white hidden" id="submit-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </button>
        </form>
    </div>

    <?php include __DIR__ . '/success.php'; ?>
</div>

<script>
    (function() {
        const formView = document.getElementById('form-view');
        const successView = document.getElementById('success-view');
        const form = document.getElementById('lead-form');
        const submitBtn = form.querySelector('button[type="submit"]');
        const submitSpinner = document.getElementById('submit-spinner');
        const formError = document.getElementById('form-error');

        const validators = {
            required: (val) => val.trim().length > 0,
            email: (val) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val),
            tel: (val) => /^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\./0-9]*$/.test(val),
            url: (val) => /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/.test(val)
        };

        const getErrorMessage = (input) => {
            const label = input.closest('.space-y-1.5').querySelector('label').innerText.replace('*', '').trim();
            if (input.validity.valueMissing || (input.type === 'checkbox' && !form.querySelectorAll(`input[name="${input.name}"]:checked`).length)) {
                return `${label} is required.`;
            }
            if (input.type === 'email' && !validators.email(input.value)) {
                return 'Please enter a valid email address.';
            }
            if (input.type === 'tel' && input.value && !validators.tel(input.value)) {
                return 'Please enter a valid phone number.';
            }
            if (input.type === 'url' && input.value && !validators.url(input.value)) {
                return 'Please enter a valid URL.';
            }
            return '';
        };

        const validateField = (input) => {
            const container = input.closest('.space-y-1.5');
            const errorMsg = container.querySelector('.error-message');
            const message = getErrorMessage(input);

            if (message) {
                errorMsg.innerText = message;
                errorMsg.classList.remove('opacity-0');
                input.classList.add('border-[var(--error-color)]', 'ring-1', 'ring-[var(--error-color)]');
                return false;
            } else {
                errorMsg.classList.add('opacity-0');
                input.classList.remove('border-[var(--error-color)]', 'ring-1', 'ring-[var(--error-color)]');
                return true;
            }
        };

        const reportHeight = () => {
            const height = document.body.scrollHeight;
            window.parent.postMessage({ type: 'elc-resize', height: height }, '*');
        };

        // Resize observer to catch any height changes
        const resizeObserver = new ResizeObserver(reportHeight);
        resizeObserver.observe(document.body);

        form.querySelectorAll('input, textarea').forEach(input => {
            input.addEventListener('blur', () => validateField(input));
            input.addEventListener('input', () => {
                if (input.classList.contains('border-[var(--error-color)]')) {
                    validateField(input);
                }
            });
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            formError.classList.add('hidden');

            let isValid = true;
            form.querySelectorAll('input[required], textarea[required]').forEach(input => {
                if (!validateField(input)) {
                    if (isValid) input.focus();
                    isValid = false;
                }
            });

            if (!isValid) return;

            submitBtn.disabled = true;
            submitSpinner.classList.remove('hidden');

            try {
                const formData = new FormData(form);
                const data = {};
                formData.forEach((value, key) => {
                    if (key.endsWith('[]')) {
                        const cleanKey = key.slice(0, -2);
                        if (!data[cleanKey]) data[cleanKey] = [];
                        data[cleanKey].push(value);
                    } else {
                        data[key] = value;
                    }
                });

                <?php if ($captcha['enabled']): ?>
                    try {
                        data['captcha_token'] = await new Promise((resolve, reject) => {
                            grecaptcha.ready(() => {
                                grecaptcha.execute('<?= htmlspecialchars($captcha['recaptcha_site_key']) ?>', { action: 'submit' })
                                    .then(resolve)
                                    .catch(reject);
                            });
                        });
                    } catch (captchaErr) {
                        console.error('Captcha error:', captchaErr);
                        formError.innerText = 'Captcha verification failed to initialize. Please refresh.';
                        formError.classList.remove('hidden');
                        submitBtn.disabled = false;
                        submitSpinner.classList.add('hidden');
                        return;
                    }
                <?php endif; ?>

                const response = await fetch('<?= $submit_url ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    formView.classList.add('hidden');
                    successView.classList.remove('hidden');
                } else {
                    if (result.errors) {
                        Object.keys(result.errors).forEach(key => {
                            const input = form.querySelector(`[name="${key}"], [name="${key}[]"]`);
                            if (input) {
                                const container = input.closest('.space-y-1.5');
                                const errorMsg = container.querySelector('.error-message');
                                errorMsg.innerText = result.errors[key];
                                errorMsg.classList.remove('opacity-0');
                                input.classList.add('border-[var(--error-color)]', 'ring-1', 'ring-[var(--error-color)]');
                            }
                        });
                    }
                    formError.innerText = result.message || 'Please correct the errors below.';
                    formError.classList.remove('hidden');
                }
            } catch (err) {
                formError.innerText = 'Something went wrong. Please try again.';
                formError.classList.remove('hidden');
            } finally {
                submitBtn.disabled = false;
                submitSpinner.classList.add('hidden');
                reportHeight();
            }
        });

        // Initial height report
        window.addEventListener('load', reportHeight);
    })();
</script>
