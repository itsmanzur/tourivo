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
                        const noticeText = document.createElement('p');
                        noticeText.textContent = data.data ? data.data.message : 'Sample data imported successfully!';
                        noticeBox.replaceChildren(noticeText);
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

    // 4b. Revoke previously shared voucher / calendar links of a booking
    document.querySelectorAll('.revoke-links-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            if (!confirm(`Revoke all voucher and calendar links already shared for booking #${this.dataset.id}?`)) {
                return;
            }

            this.disabled = true;

            const formData = new FormData();
            formData.append('action', 'tourivo_revoke_booking_links');
            formData.append('booking_id', this.dataset.id);
            formData.append('nonce', this.dataset.nonce);

            fetch(ajaxurl, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    alert(data.data ? data.data.message : 'Request finished.');
                    this.disabled = false;
                })
                .catch(() => {
                    alert('Network error while revoking links.');
                    this.disabled = false;
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
    const testEmailTypeSelect = document.getElementById('tourivo_test_email_type');
    const testEmailInput = document.getElementById('tourivo_test_email_recipient');
    const testEmailAlert = document.getElementById('tourivo-test-email-alert');

    function performTestEmailSend(btn, email, emailType, nonce) {
        if (!email) {
            alert('Please enter an email address.');
            return;
        }

        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Sending...';
        if (testEmailAlert) testEmailAlert.style.display = 'none';

        const formData = new FormData();
        formData.append('action', 'tourivo_send_test_email');
        formData.append('email', email);
        formData.append('email_type', emailType);
        formData.append('_wpnonce', nonce);

        fetch(ajaxurl, {
            method: 'POST',
            body: formData,
        })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = origHtml;

                if (testEmailAlert) {
                    if (data.success) {
                        testEmailAlert.style.color = '#15803d';
                        testEmailAlert.textContent = `✓ ${data.data ? data.data.message : 'Test email sent!'}`;
                    } else {
                        testEmailAlert.style.color = '#b91c1c';
                        testEmailAlert.textContent = `✕ ${data.data ? data.data.message : 'Failed to send.'}`;
                    }
                    testEmailAlert.style.display = 'block';
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                if (testEmailAlert) {
                    testEmailAlert.style.color = '#b91c1c';
                    testEmailAlert.innerHTML = '✕ Network error while sending test email.';
                    testEmailAlert.style.display = 'block';
                }
            });
    }

    if (sendTestEmailBtn && testEmailInput) {
        sendTestEmailBtn.addEventListener('click', function () {
            const email = testEmailInput.value.trim();
            const emailType = testEmailTypeSelect ? testEmailTypeSelect.value : 'customer_booking_confirmed';
            const nonce = this.dataset.nonce;
            performTestEmailSend(sendTestEmailBtn, email, emailType, nonce);
        });
    }

    document.querySelectorAll('.tourivo-send-single-test-email-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const emailType = this.dataset.emailType;
            const nonce = this.dataset.nonce;
            const email = testEmailInput ? testEmailInput.value.trim() : '';
            performTestEmailSend(this, email, emailType, nonce);
        });
    });

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
                            testWebhookAlert.textContent = `✓ ${data.data ? data.data.message : 'Webhook test successful!'}`;
                        } else {
                            testWebhookAlert.style.color = '#b91c1c';
                            testWebhookAlert.textContent = `✕ ${data.data ? data.data.message : 'Webhook ping failed.'}`;
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

    // 10. Privacy & GDPR Retention Dry-Run Button
    const retentionDryRunBtn = document.getElementById('tourivo-retention-dry-run-btn');
    const retentionDryRunResult = document.getElementById('tourivo-retention-dry-run-result');

    if (retentionDryRunBtn && retentionDryRunResult) {
        retentionDryRunBtn.addEventListener('click', function () {
            const nonce = this.dataset.nonce || (window.tourivoAdminConfig ? window.tourivoAdminConfig.nonce : '');
            retentionDryRunBtn.disabled = true;
            retentionDryRunBtn.textContent = 'Calculating...';
            retentionDryRunResult.style.display = 'none';

            const formData = new FormData();
            formData.append('action', 'tourivo_retention_dry_run');
            formData.append('nonce', nonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
            })
                .then(res => res.json())
                .then(data => {
                    retentionDryRunBtn.disabled = false;
                    retentionDryRunBtn.textContent = '🔍 Check Eligible Records Count';

                    if (data.success && data.data) {
                        retentionDryRunResult.style.color = '#0f766e';
                        const scanLabel = document.createElement('strong');
                        scanLabel.textContent = 'Dry-Run Scan Results:';
                        retentionDryRunResult.replaceChildren(
                            document.createTextNode('📊 '),
                            scanLabel,
                            document.createTextNode(` ${Number(data.data.bookings_to_anonymize)} completed/cancelled booking(s) eligible for anonymization, and ${Number(data.data.inquiries_to_delete)} inquiry/inquiries eligible for deletion.`)
                        );
                        retentionDryRunResult.style.display = 'block';
                    } else {
                        retentionDryRunResult.style.color = '#b91c1c';
                        retentionDryRunResult.textContent = 'Failed to calculate retention counts.';
                        retentionDryRunResult.style.display = 'block';
                    }
                })
                .catch(() => {
                    retentionDryRunBtn.disabled = false;
                    retentionDryRunBtn.textContent = '🔍 Check Eligible Records Count';
                    retentionDryRunResult.style.color = '#b91c1c';
                    retentionDryRunResult.textContent = 'Network error during dry-run scan.';
                    retentionDryRunResult.style.display = 'block';
                });
        });
    }

    // 10. Export Bookings & Reporting Modal Management
    const exportModal = document.getElementById('tourivo-export-modal');
    const openExportBtns = document.querySelectorAll('.tourivo-open-export-modal-btn');
    const exportForm = document.getElementById('tourivo-export-form');

    if (exportModal && exportForm) {
        function openExportDialog(btn) {
            exportModal.style.display = 'flex';

            // Pre-select status and search if provided by button dataset
            if (btn && btn.dataset) {
                if (btn.dataset.status && btn.dataset.status !== 'all') {
                    const statusSelect = document.getElementById('export_status');
                    if (statusSelect) statusSelect.value = btn.dataset.status;
                }
                if (btn.dataset.search) {
                    const searchInput = document.getElementById('export_search');
                    if (searchInput) searchInput.value = btn.dataset.search;
                }
            }

            fetchExportPreview();
        }

        function closeExportDialog() {
            exportModal.style.display = 'none';
        }

        openExportBtns.forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                openExportDialog(btn);
            });
        });

        exportModal.querySelectorAll('.close-modal-btn, .modal-overlay').forEach(el => {
            el.addEventListener('click', closeExportDialog);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && exportModal.style.display === 'flex') {
                closeExportDialog();
            }
        });

        // Quick Date Preset Buttons
        const presetBtns = exportModal.querySelectorAll('.export-preset-btn');
        const startDateInput = document.getElementById('export_start_date');
        const endDateInput = document.getElementById('export_end_date');

        function formatDateIso(d) {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        presetBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                presetBtns.forEach(b => b.classList.remove('active', 'button-primary'));
                this.classList.add('active', 'button-primary');

                const range = this.dataset.range;
                const now = new Date();

                if (range === 'all') {
                    if (startDateInput) startDateInput.value = '';
                    if (endDateInput) endDateInput.value = '';
                } else if (range === 'today') {
                    const todayStr = formatDateIso(now);
                    if (startDateInput) startDateInput.value = todayStr;
                    if (endDateInput) endDateInput.value = todayStr;
                } else if (range === '7days') {
                    const past = new Date(now);
                    past.setDate(past.getDate() - 7);
                    if (startDateInput) startDateInput.value = formatDateIso(past);
                    if (endDateInput) endDateInput.value = formatDateIso(now);
                } else if (range === 'this_month') {
                    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                    if (startDateInput) startDateInput.value = formatDateIso(firstDay);
                    if (endDateInput) endDateInput.value = formatDateIso(lastDay);
                } else if (range === 'last_month') {
                    const firstDayPrev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    const lastDayPrev = new Date(now.getFullYear(), now.getMonth(), 0);
                    if (startDateInput) startDateInput.value = formatDateIso(firstDayPrev);
                    if (endDateInput) endDateInput.value = formatDateIso(lastDayPrev);
                } else if (range === 'custom') {
                    if (startDateInput) startDateInput.focus();
                }

                fetchExportPreview();
            });
        });

        // Live preview calculation on filter changes
        let previewTimer = null;
        function debouncedPreview() {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(fetchExportPreview, 250);
        }

        const previewCountEl = document.getElementById('export-preview-count');
        const previewRevenueEl = document.getElementById('export-preview-revenue');

        function fetchExportPreview() {
            if (!previewCountEl || !previewRevenueEl) return;

            const nonce = (window.tourivoAdminConfig && window.tourivoAdminConfig.nonce)
                ? window.tourivoAdminConfig.nonce
                : (document.getElementById('tourivo_admin_nonce_val') ? document.getElementById('tourivo_admin_nonce_val').value : '');

            const status = document.getElementById('export_status')?.value || 'all';
            const paymentStatus = document.getElementById('export_payment_status')?.value || 'all';
            const dateType = document.getElementById('export_date_type')?.value || 'booking_date';
            const startDate = startDateInput?.value || '';
            const endDate = endDateInput?.value || '';
            const itemId = document.getElementById('export_item_id')?.value || '0';
            const search = document.getElementById('export_search')?.value || '';

            previewCountEl.textContent = '...';
            previewRevenueEl.textContent = 'calculating';

            const formData = new FormData();
            formData.append('action', 'tourivo_get_export_preview');
            formData.append('nonce', nonce);
            formData.append('status', status);
            formData.append('payment_status', paymentStatus);
            formData.append('date_type', dateType);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('item_id', itemId);
            formData.append('s', search);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data) {
                        previewCountEl.textContent = Number(data.data.count).toLocaleString();
                        previewRevenueEl.textContent = data.data.formatted_total || `$${Number(data.data.total_amount).toFixed(2)}`;
                    } else {
                        previewCountEl.textContent = '0';
                        previewRevenueEl.textContent = '$0.00';
                    }
                })
                .catch(() => {
                    previewCountEl.textContent = '—';
                    previewRevenueEl.textContent = '—';
                });
        }

        exportForm.querySelectorAll('input, select').forEach(field => {
            field.addEventListener('change', debouncedPreview);
            if (field.type === 'text') {
                field.addEventListener('input', debouncedPreview);
            }
        });
    }

    // 16. Admin Record Payment Modal Handler
    const paymentModal = document.getElementById('tourivo-record-payment-modal');
    const paymentForm = document.getElementById('tourivo-record-payment-form');
    if (paymentModal && paymentForm) {
        const bIdInput = document.getElementById('payment-modal-booking-id');
        const bNonceInput = document.getElementById('payment-modal-nonce');
        const bCodeSpan = document.getElementById('payment-modal-booking-code');
        const bCustomerEl = document.getElementById('payment-modal-customer');
        const bTotalEl = document.getElementById('payment-modal-total');
        const bPaidEl = document.getElementById('payment-modal-already-paid');
        const bDueEl = document.getElementById('payment-modal-due');
        const amountInput = document.getElementById('payment-amount');
        const statusSelect = document.getElementById('payment-status-select');
        const methodSelect = document.getElementById('payment-method-select');
        const txnIdInput = document.getElementById('payment-txn-id');
        const autoConfirmWrap = document.getElementById('payment-auto-confirm-wrap');
        const autoConfirmCheck = document.getElementById('payment-auto-confirm');
        const alertBox = document.getElementById('payment-modal-alert');
        const submitBtn = document.getElementById('tourivo-submit-payment-btn');

        document.querySelectorAll('.tourivo-open-payment-modal-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const bookingId = this.dataset.id;
                const bookingCode = this.dataset.code;
                const customer = this.dataset.customer;
                const total = parseFloat(this.dataset.total) || 0;
                const paid = parseFloat(this.dataset.paid) || 0;
                const due = parseFloat(this.dataset.due) || Math.max(0, total - paid);
                const paymentStatus = this.dataset.paymentStatus || 'pending';
                const paymentMethod = this.dataset.paymentMethod || 'bank_transfer';
                const txnId = this.dataset.transactionId || '';
                const bookingStatus = this.dataset.bookingStatus || 'pending';
                const nonce = this.dataset.nonce || '';

                bIdInput.value = bookingId;
                bNonceInput.value = nonce;
                bCodeSpan.textContent = '#' + bookingCode;
                bCustomerEl.textContent = customer || '—';
                bTotalEl.textContent = '$' + total.toFixed(2);
                bPaidEl.textContent = '$' + paid.toFixed(2);
                bDueEl.textContent = '$' + due.toFixed(2);

                // Default amount to due amount (or total if due is 0)
                amountInput.value = due > 0 ? due.toFixed(2) : total.toFixed(2);
                statusSelect.value = due <= 0 ? 'paid' : (paid > 0 ? 'partially_paid' : 'paid');
                methodSelect.value = (paymentMethod && methodSelect.querySelector(`option[value="${paymentMethod}"]`)) ? paymentMethod : 'bank_transfer';
                txnIdInput.value = txnId;

                if (autoConfirmWrap) {
                    autoConfirmWrap.style.display = (bookingStatus === 'pending') ? 'block' : 'none';
                    if (autoConfirmCheck) autoConfirmCheck.checked = (bookingStatus === 'pending');
                }

                if (alertBox) {
                    alertBox.style.display = 'none';
                    alertBox.textContent = '';
                }

                paymentModal.style.display = 'block';
            });
        });

        // Close modal buttons & background overlay
        paymentModal.querySelectorAll('.close-modal-btn, .tourivo-modal-overlay').forEach(el => {
            el.addEventListener('click', () => {
                paymentModal.style.display = 'none';
            });
        });

        // Submit Payment Record via AJAX
        paymentForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!bIdInput.value) return;

            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
            if (alertBox) alertBox.style.display = 'none';

            const formData = new FormData(paymentForm);
            formData.append('action', 'tourivo_record_payment');

            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
            })
                .then(res => res.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '✓ Save Payment Record';

                    if (data.success) {
                        if (alertBox) {
                            alertBox.style.display = 'block';
                            alertBox.style.background = '#dcfce7';
                            alertBox.style.color = '#15803d';
                            alertBox.textContent = data.data.message || 'Payment updated successfully!';
                        }
                        setTimeout(() => {
                            window.location.reload();
                        }, 800);
                    } else {
                        if (alertBox) {
                            alertBox.style.display = 'block';
                            alertBox.style.background = '#fee2e2';
                            alertBox.style.color = '#991b1b';
                            alertBox.textContent = data.data?.message || 'Failed to update payment.';
                        }
                    }
                })
                .catch(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '✓ Save Payment Record';
                    if (alertBox) {
                        alertBox.style.display = 'block';
                        alertBox.style.background = '#fee2e2';
                        alertBox.style.color = '#991b1b';
                        alertBox.textContent = 'Connection error. Please try again.';
                    }
                });
        });
    }
});

