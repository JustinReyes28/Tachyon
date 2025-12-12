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
