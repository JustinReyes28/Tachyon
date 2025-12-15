document.addEventListener('DOMContentLoaded', () => {
    // Inject Modal HTML
    const modalHtml = `
    <div class="modal-overlay" id="confirmModal">
        <div class="modal">
            <h3 class="modal-title" id="modalTitle">Confirm Action</h3>
            <p class="modal-body" id="modalMessage">Are you sure you want to proceed?</p>
            <div class="modal-actions">
                <button class="btn" id="modalCancel">Cancel</button>
                <button class="btn btn-danger" id="modalConfirm">Confirm</button>
            </div>
        </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    const modal = document.getElementById('confirmModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalConfirmBtn = document.getElementById('modalConfirm');
    const modalCancelBtn = document.getElementById('modalCancel');

    let currentConfirmCallback = null;

    function showModal(title, message, onConfirm) {
        modalTitle.textContent = title;
        modalMessage.textContent = message;
        currentConfirmCallback = onConfirm;
        modal.classList.add('active');
    }

    function hideModal() {
        modal.classList.remove('active');
        currentConfirmCallback = null;
    }

    modalCancelBtn.addEventListener('click', hideModal);

    modalConfirmBtn.addEventListener('click', () => {
        if (currentConfirmCallback) {
            currentConfirmCallback();
        }
        hideModal();
    });

    // Close on click outside
    modal.addEventListener('click', (e) => {
        if (e.target === modal) hideModal();
    });

    // Handle Delete Buttons
    const deleteForms = document.querySelectorAll('form[action="delete_todo.php"]');
    deleteForms.forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault(); // Stop default submission
            showModal(
                'Delete Task',
                'Are you sure you want to delete this task? This action cannot be undone.',
                () => form.submit() // Submit if confirmed
            );
        });
    });

    // Form Validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        // Skip validation for delete forms as they are handled above
        if (form.action.includes('delete_todo.php')) return;

        // Add real-time email validation
        const emailInput = form.querySelector('input[type="email"], input[name="email"]');
        if (emailInput) {
            // Create validation message element
            const emailFeedback = document.createElement('span');
            emailFeedback.className = 'email-feedback';
            emailFeedback.style.cssText = 'font-size: 0.75rem; margin-top: 0.25rem; display: block;';
            emailInput.parentNode.appendChild(emailFeedback);

            emailInput.addEventListener('input', () => {
                const email = emailInput.value.trim();
                if (email === '') {
                    emailFeedback.textContent = '';
                    emailInput.style.borderColor = '';
                    emailInput.style.boxShadow = '';
                } else if (isValidEmail(email)) {
                    emailFeedback.textContent = '✓ Valid email format';
                    emailFeedback.style.color = '#10B981';
                    emailInput.style.borderColor = '#10B981';
                    emailInput.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.2)';
                } else {
                    emailFeedback.textContent = '✕ Please enter a valid email address';
                    emailFeedback.style.color = '#EF4444';
                    emailInput.style.borderColor = '#EF4444';
                    emailInput.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.2)';
                }
            });

            emailInput.addEventListener('blur', () => {
                const email = emailInput.value.trim();
                if (email === '') {
                    emailFeedback.textContent = '';
                    emailInput.style.borderColor = '';
                    emailInput.style.boxShadow = '';
                }
            });
        }

        // Add password strength indicator for registration form
        const passwordInput = form.querySelector('input[name="password"]');
        const confirmPasswordInput = form.querySelector('input[name="confirm_password"]');

        if (passwordInput && confirmPasswordInput) {
            // Create password strength indicator
            const strengthIndicator = document.createElement('div');
            strengthIndicator.className = 'password-strength';
            strengthIndicator.style.cssText = 'font-size: 0.75rem; margin-top: 0.25rem;';
            passwordInput.parentNode.appendChild(strengthIndicator);

            passwordInput.addEventListener('input', () => {
                const password = passwordInput.value;
                const strength = getPasswordStrength(password);

                if (password === '') {
                    strengthIndicator.textContent = '';
                } else {
                    strengthIndicator.textContent = `Password strength: ${strength.label}`;
                    strengthIndicator.style.color = strength.color;
                }
            });

            // Real-time password match check
            const matchIndicator = document.createElement('span');
            matchIndicator.className = 'password-match';
            matchIndicator.style.cssText = 'font-size: 0.75rem; margin-top: 0.25rem; display: block;';
            confirmPasswordInput.parentNode.appendChild(matchIndicator);

            confirmPasswordInput.addEventListener('input', () => {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                if (confirmPassword === '') {
                    matchIndicator.textContent = '';
                    confirmPasswordInput.style.borderColor = '';
                    confirmPasswordInput.style.boxShadow = '';
                } else if (password === confirmPassword) {
                    matchIndicator.textContent = '✓ Passwords match';
                    matchIndicator.style.color = '#10B981';
                    confirmPasswordInput.style.borderColor = '#10B981';
                    confirmPasswordInput.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.2)';
                } else {
                    matchIndicator.textContent = '✕ Passwords do not match';
                    matchIndicator.style.color = '#EF4444';
                    confirmPasswordInput.style.borderColor = '#EF4444';
                    confirmPasswordInput.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.2)';
                }
            });
        }

        form.addEventListener('submit', (e) => {
            const inputs = form.querySelectorAll('input[required], select[required]');
            let isValid = true;

            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    highlightError(input);
                } else {
                    removeError(input);
                }
            });

            // Email validation check
            const emailField = form.querySelector('input[type="email"], input[name="email"]');
            if (emailField && emailField.value.trim() && !isValidEmail(emailField.value.trim())) {
                isValid = false;
                highlightError(emailField);
            }

            // Password confirmation check
            const password = form.querySelector('input[name="password"]');
            const confirmPassword = form.querySelector('input[name="confirm_password"]');
            if (password && confirmPassword) {
                if (password.value !== confirmPassword.value) {
                    isValid = false;
                    highlightError(confirmPassword);
                    // Optionally show a message
                    alert('Passwords do not match!');
                }
            }

            if (!isValid) {
                e.preventDefault();
            }
        });
    });

    // Email validation regex (RFC 5322 compliant, simplified)
    function isValidEmail(email) {
        const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
        return emailRegex.test(email);
    }

    // Password strength checker
    function getPasswordStrength(password) {
        let score = 0;

        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^a-zA-Z0-9]/.test(password)) score++;

        if (score <= 2) return { label: 'Weak', color: '#EF4444' };
        if (score <= 4) return { label: 'Medium', color: '#F59E0B' };
        return { label: 'Strong', color: '#10B981' };
    }

    function highlightError(input) {
        input.style.borderColor = '#ef4444';
        input.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.2)';
    }

    function removeError(input) {
        input.style.borderColor = '';
        input.style.boxShadow = '';
    }

    // Auto-dismiss alerts
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(() => {
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 3000);
    }
});
