/**
 * Tourivo Admin Dashboard, Documentation & AJAX Management JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Documentation Tab Navigation (Sidebar + In-Content Deep Links)
    function switchDocTab(targetId) {
        if (!targetId || !targetId.startsWith('#doc-')) return;

        const targetPane = document.querySelector(targetId);
        if (!targetPane) return;

        // Switch pane
        document.querySelectorAll('.tourivo-doc-pane').forEach(pane => pane.classList.remove('active'));
        targetPane.classList.add('active');

        // Set active nav item in sidebar
        document.querySelectorAll('.tourivo-docs-nav li').forEach(li => {
            const link = li.querySelector('a');
            if (link && link.getAttribute('href') === targetId) {
                li.classList.add('active');
            } else {
                li.classList.remove('active');
            }
        });

        // Update URL hash without jumping abruptly
        if (history.pushState) {
            history.pushState(null, null, targetId);
        } else {
            location.hash = targetId;
        }
    }

    // Global listener for all documentation navigation links
    document.addEventListener('click', function (e) {
        const docLink = e.target.closest('a[href^="#doc-"]');
        if (docLink) {
            e.preventDefault();
            const targetId = docLink.getAttribute('href');
            switchDocTab(targetId);
        }
    });

    // Check URL hash on initial page load
    if (window.location.hash && window.location.hash.startsWith('#doc-')) {
        switchDocTab(window.location.hash);
    }

    // 2. Universal 1-Click Copy Helper (Supports HTTP, HTTPS, Localhost, and older browsers)
    function copyToClipboard(text, btnElement) {
        if (!text) return;

        function showSuccess() {
            if (!btnElement) return;
            const originalText = btnElement.textContent;
            btnElement.textContent = '✓ Copied!';
            btnElement.classList.add('button-primary');
            btnElement.style.background = '#15803d';
            btnElement.style.borderColor = '#15803d';
            btnElement.style.color = '#ffffff';

            setTimeout(() => {
                btnElement.textContent = originalText;
                btnElement.classList.remove('button-primary');
                btnElement.style.background = '';
                btnElement.style.borderColor = '';
                btnElement.style.color = '';
            }, 1800);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text)
                .then(showSuccess)
                .catch(() => execFallbackCopy(text, showSuccess));
        } else {
            execFallbackCopy(text, showSuccess);
        }
    }

    function execFallbackCopy(text, callback) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.left = '-999999px';
        textarea.style.top = '-999999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        try {
            const successful = document.execCommand('copy');
            if (successful) {
                callback();
            } else {
                prompt('Copy this shortcode manually:', text);
            }
        } catch (err) {
            prompt('Copy this shortcode manually:', text);
        } finally {
            document.body.removeChild(textarea);
        }
    }

    // Global listener for all copy buttons
    document.addEventListener('click', function (e) {
        const copyBtn = e.target.closest('.copy-code-btn');
        if (copyBtn) {
            e.preventDefault();
            const code = copyBtn.dataset.code || copyBtn.getAttribute('data-code');
            copyToClipboard(code, copyBtn);
        }
    });

    // 3. 1-Click Sample Data Importer (Supports Dashboard, Notice, and Docs Hub)
    const importButtons = document.querySelectorAll('.tourivo-import-sample-btn, #tourivo-import-sample-btn');

    importButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const nonce = this.dataset.nonce || (window.tourivoAdminConfig ? window.tourivoAdminConfig.nonce : '');
            if (!confirm('Are you sure you want to import sample tours, hotels, and rooms data? This will add starter packages.')) {
                return;
            }

            // Disable all import buttons during process
            importButtons.forEach(b => {
                b.disabled = true;
                b.innerHTML = '<span class="dashicons dashicons-update spin"></span> Importing Starter Packages...';
            });

            const formData = new FormData();
            formData.append('action', 'tourivo_import_sample_data');
            formData.append('nonce', nonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
            })
                .then(res => res.json())
                .then(data => {
                    importButtons.forEach(b => {
                        b.disabled = false;
                        b.innerHTML = '<span class="dashicons dashicons-yes"></span> Imported Successfully!';
                    });

                    document.querySelectorAll('.tourivo-seeder-notice, #tourivo-seeder-notice').forEach(noticeBox => {
                        noticeBox.className = 'notice notice-success inline';
                        noticeBox.innerHTML = `<p>${data.data ? data.data.message : 'Sample data imported successfully!'}</p>`;
                        noticeBox.style.display = 'block';
                    });

                    setTimeout(() => {
                        window.location.reload();
                    }, 1200);
                })
                .catch(() => {
                    importButtons.forEach(b => {
                        b.disabled = false;
                        b.innerHTML = '<span class="dashicons dashicons-cloud-upload"></span> ⚡ Import Starter Demo Content';
                    });
                    alert('An error occurred while importing sample data.');
                });
        });
    });

    // 3.1 Welcome Notice Dismissal Persistence
    document.addEventListener('click', function (e) {
        if (e.target.matches('.tourivo-welcome-notice .notice-dismiss') || e.target.closest('.tourivo-welcome-notice .notice-dismiss')) {
            const noticeEl = e.target.closest('.tourivo-welcome-notice');
            const nonce = noticeEl ? noticeEl.dataset.nonce : (window.tourivoAdminConfig ? window.tourivoAdminConfig.nonce : '');

            const formData = new FormData();
            formData.append('action', 'tourivo_dismiss_welcome_notice');
            formData.append('nonce', nonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
            });
        }
    });

    // 4. Change Booking Status (Inline Ajax)
    document.querySelectorAll('.change-status-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const bookingId = this.dataset.id;
            const newStatus = this.dataset.status;
            const nonce = this.dataset.nonce;

            if (!confirm(`Change status of booking #${bookingId} to "${newStatus}"?`)) {
                return;
            }

            this.disabled = true;

            const formData = new FormData();
            formData.append('action', 'tourivo_update_booking_status');
            formData.append('booking_id', bookingId);
            formData.append('status', newStatus);
            formData.append('nonce', nonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.getElementById('status-badge-' + bookingId);
                        if (badge) {
                            badge.className = 'tourivo-badge tourivo-badge-' + newStatus;
                            badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                        }
                    } else {
                        alert(data.data ? data.data.message : 'Could not update status.');
                    }
                })
                .catch(() => {
                    alert('Network error while updating booking status.');
                });
        });
    });

    // 5. Manual Booking Modal & Submission
    const openManualBookingBtn = document.getElementById('tourivo-open-manual-booking-btn');
    const manualBookingModal = document.getElementById('tourivo-manual-booking-modal');
    const manualBookingForm = document.getElementById('tourivo-manual-booking-form');
    const itemSelect = document.getElementById('manual_item_select');
    const itemTypeInput = document.getElementById('manual_item_type');

    if (openManualBookingBtn && manualBookingModal) {
        openManualBookingBtn.addEventListener('click', function () {
            manualBookingModal.style.display = 'flex';
        });

        manualBookingModal.querySelectorAll('.close-modal-btn, .modal-overlay').forEach(el => {
            el.addEventListener('click', function () {
                manualBookingModal.style.display = 'none';
            });
        });
    }

    if (itemSelect && itemTypeInput) {
        itemSelect.addEventListener('change', function () {
            const selectedOpt = this.options[this.selectedIndex];
            const type = selectedOpt.dataset.type || 'tour';
            itemTypeInput.value = type;
        });
    }

    if (manualBookingForm) {
        manualBookingForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const submitBtn = document.getElementById('manual-booking-submit-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Booking...';

            const formData = new FormData(manualBookingForm);
            formData.append('action', 'tourivo_create_manual_booking');
            formData.append('nonce', window.tourivoAdminConfig ? window.tourivoAdminConfig.nonce : '');

            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
            })
                .then(res => res.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '✓ Confirm & Create Booking';

                    if (data.success) {
                        alert(data.data ? data.data.message : 'Booking created successfully!');
                        window.location.reload();
                    } else {
                        alert(data.data ? data.data.message : 'Error creating manual booking.');
                    }
                })
                .catch(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '✓ Confirm & Create Booking';
                    alert('Network error creating booking.');
                });
        });
    }

    // 6. Inquiries Table Status Changer & Delete
    document.querySelectorAll('.change-inquiry-status-select').forEach(select => {
        select.addEventListener('change', function () {
            const inqId = this.dataset.id;
            const newStatus = this.value;
            const nonce = this.dataset.nonce;

            const formData = new FormData();
            formData.append('action', 'tourivo_update_inquiry_status');
            formData.append('id', inqId);
            formData.append('status', newStatus);
            formData.append('_wpnonce', nonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        alert(data.data ? data.data.message : 'Error updating inquiry.');
                    }
                });
        });
    });

    document.querySelectorAll('.delete-inquiry-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const inqId = this.dataset.id;
            const nonce = this.dataset.nonce;

            if (!confirm('Are you sure you want to permanently delete this inquiry?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'tourivo_delete_inquiry');
            formData.append('id', inqId);
            formData.append('_wpnonce', nonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('inquiry-row-' + inqId)?.remove();
                    } else {
                        alert(data.data ? data.data.message : 'Error deleting inquiry.');
                    }
                });
        });
    });

    // 7. Settings Page Tabs Navigation
    const settingsTabs = document.querySelectorAll('.tourivo-settings-tabs .nav-tab');
    if (settingsTabs.length > 0) {
        settingsTabs.forEach(tab => {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                const tabName = this.dataset.tab;

                settingsTabs.forEach(t => t.classList.remove('nav-tab-active'));
                this.classList.add('nav-tab-active');

                document.querySelectorAll('.tourivo-settings-tab-pane').forEach(pane => {
                    pane.style.display = 'none';
                });

                const activePane = document.getElementById('settings-tab-' + tabName);
                if (activePane) {
                    activePane.style.display = 'block';
                }
            });
        });
    }

    // 8. Test Email Dispatcher
    const sendTestEmailBtn = document.getElementById('tourivo-send-test-email-btn');
    const testEmailInput = document.getElementById('tourivo_test_email_recipient');
    const testEmailAlert = document.getElementById('tourivo-test-email-alert');

    if (sendTestEmailBtn && testEmailInput) {
        sendTestEmailBtn.addEventListener('click', function () {
            const email = testEmailInput.value.trim();
            const nonce = this.dataset.nonce;

            if (!email) {
                alert('Please enter an email address.');
                return;
            }

            sendTestEmailBtn.disabled = true;
            sendTestEmailBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Sending...';
            if (testEmailAlert) testEmailAlert.style.display = 'none';

            const formData = new FormData();
            formData.append('action', 'tourivo_send_test_email');
            formData.append('email', email);
            formData.append('_wpnonce', nonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
            })
                .then(res => res.json())
                .then(data => {
                    sendTestEmailBtn.disabled = false;
                    sendTestEmailBtn.innerHTML = '✉️ Send Test Email';

                    if (testEmailAlert) {
                        if (data.success) {
                            testEmailAlert.style.color = '#15803d';
                            testEmailAlert.innerHTML = `✓ ${data.data ? data.data.message : 'Test email sent!'}`;
                        } else {
                            testEmailAlert.style.color = '#b91c1c';
                            testEmailAlert.innerHTML = `✕ ${data.data ? data.data.message : 'Failed to send.'}`;
                        }
                        testEmailAlert.style.display = 'block';
                    }
                })
                .catch(() => {
                    sendTestEmailBtn.disabled = false;
                    sendTestEmailBtn.innerHTML = '✉️ Send Test Email';
                    if (testEmailAlert) {
                        testEmailAlert.style.color = '#b91c1c';
                        testEmailAlert.innerHTML = '✕ Network error while sending test email.';
                        testEmailAlert.style.display = 'block';
                    }
                });
        });
    }

    // 8b. Webhook Secret Generator
    const genSecretBtn = document.getElementById('tourivo-gen-secret-btn');
    const secretInput = document.getElementById('webhook_secret');
    if (genSecretBtn && secretInput) {
        genSecretBtn.addEventListener('click', function () {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let secret = 'sec_trv_';
            for (let i = 0; i < 24; i++) {
                secret += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            secretInput.value = secret;
        });
    }

    // 8c. Test Webhook Dispatcher
    const sendTestWebhookBtn = document.getElementById('tourivo-send-test-webhook-btn');
    const webhookUrlInput = document.getElementById('webhook_url');
    const testWebhookAlert = document.getElementById('tourivo-test-webhook-alert');

    if (sendTestWebhookBtn && webhookUrlInput) {
        sendTestWebhookBtn.addEventListener('click', function () {
            const url = webhookUrlInput.value.trim();
            const secret = secretInput ? secretInput.value.trim() : '';
            const nonce = this.dataset.nonce;

            if (!url) {
                alert('Please enter a Webhook Target URL first.');
                return;
            }

            sendTestWebhookBtn.disabled = true;
            sendTestWebhookBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Testing...';
            if (testWebhookAlert) testWebhookAlert.style.display = 'none';

            const formData = new FormData();
            formData.append('action', 'tourivo_send_test_webhook');
            formData.append('url', url);
            formData.append('secret', secret);
            formData.append('_wpnonce', nonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
            })
                .then(res => res.json())
                .then(data => {
                    sendTestWebhookBtn.disabled = false;
                    sendTestWebhookBtn.innerHTML = '🚀 Send Test Ping Payload';

                    if (testWebhookAlert) {
                        if (data.success) {
                            testWebhookAlert.style.color = '#15803d';
                            testWebhookAlert.innerHTML = `✓ ${data.data ? data.data.message : 'Webhook test successful!'}`;
                        } else {
                            testWebhookAlert.style.color = '#b91c1c';
                            testWebhookAlert.innerHTML = `✕ ${data.data ? data.data.message : 'Webhook ping failed.'}`;
                        }
                        testWebhookAlert.style.display = 'block';
                    }
                })
                .catch(() => {
                    sendTestWebhookBtn.disabled = false;
                    sendTestWebhookBtn.innerHTML = '🚀 Send Test Ping Payload';
                    if (testWebhookAlert) {
                        testWebhookAlert.style.color = '#b91c1c';
                        testWebhookAlert.innerHTML = '✕ Network error while contacting test endpoint.';
                        testWebhookAlert.style.display = 'block';
                    }
                });
        });
    }

    // 9. Booking Details & Activity Timeline Modal
    const timelineModal = document.getElementById('tourivo-booking-timeline-modal');
    const logsContainer = document.getElementById('timeline-logs-container');
    const addNoteForm = document.getElementById('tourivo-add-note-form');
    let currentTimelineNonce = '';

    document.querySelectorAll('.open-timeline-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const code = this.dataset.code;
            const name = this.dataset.name;
            const email = this.dataset.email;
            const phone = this.dataset.phone || 'N/A';
            const item = this.dataset.item;
            const amount = this.dataset.amount;
            currentTimelineNonce = this.dataset.nonce;

            if (timelineModal) {
                document.getElementById('timeline-modal-code').textContent = `Booking #${code}`;
                document.getElementById('timeline-modal-name').textContent = name;
                document.getElementById('timeline-modal-email').textContent = email;
                document.getElementById('timeline-modal-phone').textContent = phone;
                document.getElementById('timeline-modal-item').textContent = item;
                document.getElementById('timeline-modal-amount').textContent = amount;
                document.getElementById('timeline-booking-id').value = id;

                timelineModal.style.display = 'flex';
                fetchTimelineLogs(id, currentTimelineNonce);
            }
        });
    });

    if (timelineModal) {
        timelineModal.querySelectorAll('.close-modal-btn, .modal-overlay').forEach(el => {
            el.addEventListener('click', function () {
                timelineModal.style.display = 'none';
            });
        });
    }

    function fetchTimelineLogs(bookingId, nonce) {
        if (!logsContainer) return;
        logsContainer.innerHTML = '<div class="timeline-loading" style="text-align:center; color:#64748b; padding:10px;"><span class="dashicons dashicons-update spin"></span> Loading activity logs...</div>';

        const formData = new FormData();
        formData.append('action', 'tourivo_get_booking_timeline');
        formData.append('booking_id', bookingId);
        formData.append('_wpnonce', nonce);

        fetch(ajaxurl, {
            method: 'POST',
            body: formData,
        })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    logsContainer.innerHTML = data.data.html;
                } else {
                    logsContainer.innerHTML = '<div style="color:#ef4444; padding:8px;">Failed to load logs.</div>';
                }
            })
            .catch(() => {
                logsContainer.innerHTML = '<div style="color:#ef4444; padding:8px;">Error loading logs.</div>';
            });
    }

    if (addNoteForm) {
        addNoteForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const noteInput = document.getElementById('internal-note-text');
            const bookingId = document.getElementById('timeline-booking-id').value;
            const submitBtn = document.getElementById('add-note-submit-btn');

            if (!noteInput || !noteInput.value.trim()) return;

            submitBtn.disabled = true;
            submitBtn.textContent = 'Adding...';

            const formData = new FormData();
            formData.append('action', 'tourivo_add_booking_note');
            formData.append('booking_id', bookingId);
            formData.append('note', noteInput.value.trim());
            formData.append('_wpnonce', currentTimelineNonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
            })
                .then(res => res.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '➕ Add Note';

                    if (data.success) {
                        noteInput.value = '';
                        fetchTimelineLogs(bookingId, currentTimelineNonce);
                    } else {
                        alert(data.data ? data.data.message : 'Error recording note.');
                    }
                })
                .catch(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '➕ Add Note';
                    alert('Network error while adding note.');
                });
        });
    }
});

