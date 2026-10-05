/**
 * Tourivo Admin MetaBox JavaScript (Tabs, Repeaters for Itinerary, Inclusions, FAQs)
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Tab switching
    document.addEventListener('click', function (e) {
        const link = e.target.closest('.tourivo-tabs-nav li a');
        if (!link) return;

        e.preventDefault();
        const parentWrapper = link.closest('.tourivo-metabox-wrapper');
        const parentLi = link.parentElement;
        const targetId = link.getAttribute('href');

        if (parentWrapper) {
            parentWrapper.querySelectorAll('.tourivo-tabs-nav li').forEach(li => li.classList.remove('active'));
            parentLi.classList.add('active');

            parentWrapper.querySelectorAll('.tourivo-tab-pane').forEach(pane => pane.classList.remove('active'));
            const targetPane = parentWrapper.querySelector(targetId);
            if (targetPane) {
                targetPane.classList.add('active');
            }
        }
    });


    // 2. Add Day to Itinerary
    const addItineraryBtn = document.getElementById('tourivo-add-itinerary-btn');
    const itineraryContainer = document.getElementById('tourivo-itinerary-repeater');
    if (addItineraryBtn && itineraryContainer) {
        addItineraryBtn.addEventListener('click', function () {
            const index = itineraryContainer.querySelectorAll('.tourivo-repeater-row').length;
            const dayNum = index + 1;

            const rowHtml = `
                <div class="tourivo-repeater-row">
                    <div class="row-header">
                        <strong>Day / Stage: ${dayNum}</strong>
                        <button type="button" class="button remove-row-btn">&times;</button>
                    </div>
                    <div class="row-body">
                        <div class="tourivo-row">
                            <div class="tourivo-col" style="flex: 0 0 100px;">
                                <label>Day #</label>
                                <input type="text" name="_tourivo_itinerary[${index}][day]" value="${dayNum}">
                            </div>
                            <div class="tourivo-col">
                                <label>Day Title</label>
                                <input type="text" name="_tourivo_itinerary[${index}][title]" placeholder="e.g. City Tour & Sunset Cruise">
                            </div>
                            <div class="tourivo-col" style="flex: 0 0 160px;">
                                <label>Included Meals</label>
                                <input type="text" name="_tourivo_itinerary[${index}][meals]" placeholder="Breakfast, Lunch">
                            </div>
                        </div>
                        <div class="tourivo-form-group" style="margin-top: 10px;">
                            <label>Description & Details</label>
                            <textarea rows="3" name="_tourivo_itinerary[${index}][desc]"></textarea>
                        </div>
                    </div>
                </div>
            `;
            itineraryContainer.insertAdjacentHTML('beforeend', rowHtml);
        });
    }

    // 3. Add Inclusion item
    const addIncBtn = document.getElementById('tourivo-add-inclusion-btn');
    const incList = document.getElementById('tourivo-inclusions-list');
    if (addIncBtn && incList) {
        addIncBtn.addEventListener('click', function () {
            const itemHtml = `
                <div class="list-item-row">
                    <span class="dashicons dashicons-yes-alt" style="color: #10b981;"></span>
                    <input type="text" name="_tourivo_inclusions[]" placeholder="e.g. Hotel Pickup and Drop-off">
                    <button type="button" class="button remove-list-item">&times;</button>
                </div>
            `;
            incList.insertAdjacentHTML('beforeend', itemHtml);
        });
    }

    // 4. Add Exclusion item
    const addExcBtn = document.getElementById('tourivo-add-exclusion-btn');
    const excList = document.getElementById('tourivo-exclusions-list');
    if (addExcBtn && excList) {
        addExcBtn.addEventListener('click', function () {
            const itemHtml = `
                <div class="list-item-row">
                    <span class="dashicons dashicons-dismiss" style="color: #ef4444;"></span>
                    <input type="text" name="_tourivo_exclusions[]" placeholder="e.g. Personal Expenses & Tips">
                    <button type="button" class="button remove-list-item">&times;</button>
                </div>
            `;
            excList.insertAdjacentHTML('beforeend', itemHtml);
        });
    }

    // 5. Add FAQ item
    const addFaqBtn = document.getElementById('tourivo-add-faq-btn');
    const faqList = document.getElementById('tourivo-faqs-list');
    if (addFaqBtn && faqList) {
        addFaqBtn.addEventListener('click', function () {
            const i = faqList.querySelectorAll('.tourivo-repeater-row').length;
            const faqHtml = `
                <div class="tourivo-repeater-row">
                    <div class="row-header">
                        <strong>Question & Answer</strong>
                        <button type="button" class="button remove-row-btn">&times;</button>
                    </div>
                    <div class="row-body">
                        <div class="tourivo-form-group">
                            <label>Question</label>
                            <input type="text" name="_tourivo_faqs[${i}][question]" placeholder="e.g. Is lunch provided?">
                        </div>
                        <div class="tourivo-form-group">
                            <label>Answer</label>
                            <textarea rows="2" name="_tourivo_faqs[${i}][answer]"></textarea>
                        </div>
                    </div>
                </div>
            `;
            faqList.insertAdjacentHTML('beforeend', faqHtml);
        });
    }

    // 6. Global event delegation for removal buttons
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-row-btn')) {
            e.target.closest('.tourivo-repeater-row').remove();
        }
        if (e.target.classList.contains('remove-list-item')) {
            e.target.closest('.list-item-row').remove();
        }
    });

    // =========================================================================
    // 7. Availability & Pricing Calendar Manager (Vanilla JS)
    // =========================================================================
    const availApp = document.getElementById('tourivo-availability-app');
    if (!availApp) return;

    const config = window.tourivoAvailabilityConfig || {
        ajaxUrl: '/wp-admin/admin-ajax.php',
        nonce: '',
        isBengali: false,
        useBengaliDigits: false,
        monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        i18n: {
            loading: 'Loading availability calendar...',
            applying: 'Applying changes...',
            noDatesSelected: 'Please select at least one date from the calendar.',
            noChanges: 'Please specify at least one change to apply (Status, Capacity, or Price).',
            selectedCount: '%d date(s) selected',
            statusAvailable: 'Available',
            statusBlocked: 'Blocked',
            statusSoldOut: 'Sold Out'
        }
    };

    const itemId = parseInt(availApp.getAttribute('data-item-id'), 10) || 0;
    const itemType = availApp.getAttribute('data-item-type') || 'tour';

    const now = new Date();
    let currentYear = now.getFullYear();
    let currentMonth = now.getMonth() + 1; // 1-indexed (1..12)

    let selectedDates = new Set();
    let lastClickedDate = null;
    let daysDataMap = {};

    const monthTitleEl = availApp.querySelector('.tourivo-avail-current-month');
    const gridEl = document.getElementById('tourivo-avail-grid');
    const prevBtn = availApp.querySelector('.tourivo-avail-nav-prev');
    const nextBtn = availApp.querySelector('.tourivo-avail-nav-next');
    const todayBtn = availApp.querySelector('.tourivo-avail-nav-today');
    const defCapEl = availApp.querySelector('.tourivo-avail-def-cap');
    const defPriceEl = availApp.querySelector('.tourivo-avail-def-price');
    const selectionBadge = document.getElementById('tourivo-selection-badge');
    const applyBtn = document.getElementById('tourivo-bulk-apply-btn');
    const clearBtn = document.getElementById('tourivo-bulk-clear-btn');
    const alertBox = document.getElementById('tourivo-panel-alert');

    function formatNumber(num) {
        if (!config.useBengaliDigits) return String(num);
        const bnDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        return String(num).replace(/[0-9]/g, digit => bnDigits[parseInt(digit, 10)]);
    }

    function showAlert(msg, isError = false) {
        if (!alertBox) return;
        alertBox.textContent = msg;
        alertBox.className = 'tourivo-panel-alert ' + (isError ? 'alert-error' : 'alert-success');
        alertBox.style.display = 'block';
        setTimeout(() => {
            if (alertBox && !isError) alertBox.style.display = 'none';
        }, 5000);
    }

    function updateSelectionBadge() {
        const count = selectedDates.size;
        const text = config.i18n.selectedCount.replace('%d', formatNumber(count));
        if (selectionBadge) {
            selectionBadge.textContent = text;
        }

        // Synchronize ARIA state and selection classes on rendered day elements
        const allDayTiles = gridEl.querySelectorAll('.tourivo-avail-day[data-date]');
        allDayTiles.forEach(tile => {
            const date = tile.getAttribute('data-date');
            const isSel = selectedDates.has(date);
            tile.classList.toggle('is-selected', isSel);
            tile.setAttribute('aria-selected', isSel ? 'true' : 'false');
        });
    }

    function fetchAndRenderCalendar(year, month) {
        if (!gridEl) return;
        gridEl.innerHTML = `<div style="grid-column: 1 / -1; text-align: center; padding: 40px 0; color: #64748b;"><span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>${config.i18n.loading}</div>`;

        const monthName = config.monthNames[month - 1] || '';
        if (monthTitleEl) {
            monthTitleEl.textContent = `${monthName} ${formatNumber(year)}`;
        }

        const formData = new FormData();
        formData.append('action', 'tourivo_get_admin_calendar');
        formData.append('nonce', config.nonce);
        formData.append('item_id', itemId);
        formData.append('item_type', itemType);
        formData.append('year', year);
        formData.append('month', month);

        fetch(config.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(response => {
            if (!response.success || !response.data) {
                showAlert(response.data?.message || 'Failed to load availability calendar.', true);
                gridEl.innerHTML = `<div style="grid-column: 1 / -1; text-align: center; padding: 30px; color: #ef4444;">${response.data?.message || 'Error loading calendar.'}</div>`;
                return;
            }

            const data = response.data;
            if (defCapEl) defCapEl.textContent = formatNumber(data.default_capacity);
            if (defPriceEl) defPriceEl.textContent = `${data.currency_symbol}${formatNumber(data.base_price)}`;

            daysDataMap = data.days || {};
            renderDaysGrid(year, month, daysDataMap);
        })
        .catch(err => {
            showAlert('Network error while loading availability.', true);
            gridEl.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 30px; color: #ef4444;">Network connection error.</div>';
        });
    }

    function renderDaysGrid(year, month, days) {
        gridEl.innerHTML = '';

        // Calculate leading blank days (1st of month day of week: Mon=1..Sun=7)
        const firstDateStr = `${year}-${String(month).padStart(2, '0')}-01`;
        const firstDayObj = new Date(year, month - 1, 1);
        let firstDayOfWeek = firstDayObj.getDay(); // Sun=0, Mon=1...
        firstDayOfWeek = firstDayOfWeek === 0 ? 7 : firstDayOfWeek; // ISO: 1 (Mon) to 7 (Sun)

        for (let i = 1; i < firstDayOfWeek; i++) {
            const blankTile = document.createElement('div');
            blankTile.className = 'tourivo-avail-day is-empty';
            blankTile.setAttribute('aria-hidden', 'true');
            gridEl.appendChild(blankTile);
        }

        const dateKeys = Object.keys(days).sort();
        dateKeys.forEach((dateStr, index) => {
            const dayData = days[dateStr];
            const isSelected = selectedDates.has(dateStr);

            const dayTile = document.createElement('div');
            dayTile.className = `tourivo-avail-day status-${dayData.status}`;
            if (isSelected) dayTile.classList.add('is-selected');
            if (dayData.is_today) dayTile.classList.add('is-today');
            if (dayData.is_past) dayTile.classList.add('is-past');

            dayTile.setAttribute('role', 'gridcell');
            dayTile.setAttribute('tabindex', index === 0 ? '0' : '-1');
            dayTile.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            dayTile.setAttribute('data-date', dateStr);
            dayTile.setAttribute('data-dow', String(dayData.day_of_week));

            const statusLabel = dayData.status === 'blocked'
                ? config.i18n.statusBlocked
                : (dayData.status === 'sold_out' ? config.i18n.statusSoldOut : config.i18n.statusAvailable);

            const ariaLabel = `${dateStr}: ${statusLabel}, ${dayData.available_spots} available of ${dayData.total_capacity}, Price: ${dayData.formatted_price}`;
            dayTile.setAttribute('aria-label', ariaLabel);

            const priceOverrideIcon = dayData.price_override !== null
                ? '<span class="tourivo-override-icon" title="Custom Price Override">★</span>'
                : '';

            dayTile.innerHTML = `
                <div class="tourivo-avail-day-top">
                    <span class="tourivo-day-num">${formatNumber(dayData.day)}</span>
                    <span class="tourivo-day-badge">${statusLabel}</span>
                </div>
                <div class="tourivo-day-meta">
                    <div>Cap: <strong>${formatNumber(dayData.total_capacity)}</strong> | Booked: <strong>${formatNumber(dayData.booked_count)}</strong></div>
                </div>
                <div class="tourivo-day-price">
                    <span>${dayData.formatted_price}</span>
                    ${priceOverrideIcon}
                </div>
            `;

            // Click handling (supports click, Ctrl/Cmd click, Shift range click)
            dayTile.addEventListener('click', function (e) {
                handleDayClick(dateStr, e.shiftKey, e.ctrlKey || e.metaKey);
            });

            // Keyboard support (Space / Enter selects, Arrow keys navigate)
            dayTile.addEventListener('keydown', function (e) {
                handleDayKeydown(e, dateStr, dayTile);
            });

            gridEl.appendChild(dayTile);
        });

        updateSelectionBadge();
    }

    function handleDayClick(clickedDate, isShift, isToggle) {
        if (isShift && lastClickedDate && lastClickedDate !== clickedDate) {
            // Range selection
            const allDateKeys = Object.keys(daysDataMap).sort();
            const startIdx = allDateKeys.indexOf(lastClickedDate);
            const endIdx = allDateKeys.indexOf(clickedDate);

            if (startIdx !== -1 && endIdx !== -1) {
                const min = Math.min(startIdx, endIdx);
                const max = Math.max(startIdx, endIdx);
                for (let i = min; i <= max; i++) {
                    selectedDates.add(allDateKeys[i]);
                }
            }
        } else if (isToggle) {
            if (selectedDates.has(clickedDate)) {
                selectedDates.delete(clickedDate);
            } else {
                selectedDates.add(clickedDate);
            }
        } else {
            // Normal click: toggle clicked date
            if (selectedDates.has(clickedDate) && selectedDates.size === 1) {
                selectedDates.delete(clickedDate);
            } else {
                selectedDates.add(clickedDate);
            }
        }

        lastClickedDate = clickedDate;
        updateSelectionBadge();
    }

    function handleDayKeydown(e, dateStr, tile) {
        if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            handleDayClick(dateStr, e.shiftKey, false);
            return;
        }

        const tiles = Array.from(gridEl.querySelectorAll('.tourivo-avail-day[data-date]'));
        const currentIdx = tiles.indexOf(tile);
        if (currentIdx === -1) return;

        let targetIdx = -1;
        if (e.key === 'ArrowRight') targetIdx = currentIdx + 1;
        else if (e.key === 'ArrowLeft') targetIdx = currentIdx - 1;
        else if (e.key === 'ArrowDown') targetIdx = currentIdx + 7;
        else if (e.key === 'ArrowUp') targetIdx = currentIdx - 7;

        if (targetIdx >= 0 && targetIdx < tiles.length) {
            e.preventDefault();
            tiles.forEach(t => t.setAttribute('tabindex', '-1'));
            tiles[targetIdx].setAttribute('tabindex', '0');
            tiles[targetIdx].focus();
        }
    }

    // Month Navigation Listeners
    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            currentMonth--;
            if (currentMonth < 1) {
                currentMonth = 12;
                currentYear--;
            }
            fetchAndRenderCalendar(currentYear, currentMonth);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            currentMonth++;
            if (currentMonth > 12) {
                currentMonth = 1;
                currentYear++;
            }
            fetchAndRenderCalendar(currentYear, currentMonth);
        });
    }

    if (todayBtn) {
        todayBtn.addEventListener('click', function () {
            const t = new Date();
            currentYear = t.getFullYear();
            currentMonth = t.getMonth() + 1;
            fetchAndRenderCalendar(currentYear, currentMonth);
        });
    }

    // Clear Selection Listener
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            selectedDates.clear();
            lastClickedDate = null;
            updateSelectionBadge();
        });
    }

    // Bulk Apply Action
    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            if (selectedDates.size === 0) {
                showAlert(config.i18n.noDatesSelected, true);
                return;
            }

            const statusVal = document.getElementById('tourivo-bulk-status')?.value || '';
            const capVal = document.getElementById('tourivo-bulk-capacity')?.value || '';
            const priceVal = document.getElementById('tourivo-bulk-price')?.value || '';
            const resetPriceVal = document.getElementById('tourivo-bulk-reset-price')?.checked || false;
            const forceVal = document.getElementById('tourivo-bulk-force')?.checked || false;

            // Day-of-week filters
            const dowChecks = Array.from(availApp.querySelectorAll('.tourivo-dow-check:checked')).map(cb => parseInt(cb.value, 10));

            if (!statusVal && capVal === '' && priceVal === '' && !resetPriceVal) {
                showAlert(config.i18n.noChanges, true);
                return;
            }

            const sortedDates = Array.from(selectedDates).sort();
            const startDate = sortedDates[0];
            const endDate = sortedDates[sortedDates.length - 1];

            applyBtn.disabled = true;
            applyBtn.classList.add('updating-message');

            const formData = new FormData();
            formData.append('action', 'tourivo_update_availability');
            formData.append('nonce', config.nonce);
            formData.append('item_id', itemId);
            formData.append('item_type', itemType);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            if (statusVal) formData.append('status', statusVal);
            if (capVal !== '') formData.append('capacity', capVal);
            if (priceVal !== '') formData.append('price_override', priceVal);
            if (resetPriceVal) formData.append('reset_price', '1');
            if (forceVal) formData.append('force', '1');

            dowChecks.forEach(dow => {
                formData.append('days_of_week[]', String(dow));
            });

            fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(res => res.json())
            .then(response => {
                applyBtn.disabled = false;
                applyBtn.classList.remove('updating-message');

                if (!response.success) {
                    showAlert(response.data?.message || 'Failed to update availability.', true);
                    return;
                }

                showAlert(response.data?.message || 'Availability updated successfully!', false);

                // Reset bulk form inputs
                if (document.getElementById('tourivo-bulk-status')) document.getElementById('tourivo-bulk-status').value = '';
                if (document.getElementById('tourivo-bulk-capacity')) document.getElementById('tourivo-bulk-capacity').value = '';
                if (document.getElementById('tourivo-bulk-price')) document.getElementById('tourivo-bulk-price').value = '';
                if (document.getElementById('tourivo-bulk-reset-price')) document.getElementById('tourivo-bulk-reset-price').checked = false;

                // Refresh calendar grid
                fetchAndRenderCalendar(currentYear, currentMonth);
            })
            .catch(err => {
                applyBtn.disabled = false;
                applyBtn.classList.remove('updating-message');
                showAlert('Network request failed.', true);
            });
        });
    }

    // Initial Load
    fetchAndRenderCalendar(currentYear, currentMonth);
});

