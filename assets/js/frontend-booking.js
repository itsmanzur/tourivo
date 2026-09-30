/**
 * Tourivo Frontend Booking Interactive JavaScript (Zero-jQuery Reactive UI)
 */

document.addEventListener('DOMContentLoaded', function () {
    const panels = document.querySelectorAll('.tourivo-booking-panel');

    panels.forEach(panel => {
        initBookingPanel(panel);
    });

    function initBookingPanel(panel) {
        const itemId = panel.dataset.itemId;
        const itemType = (panel.dataset.itemType === 'hotel_room' || panel.dataset.itemType === 'room') ? 'room' : 'tour';
        const baseUnitPrice = parseFloat(panel.dataset.unitPrice) || 0;

        const checkInInput = panel.querySelector('#tourivo-check-in');
        const checkOutInput = panel.querySelector('#tourivo-check-out');
        const roomsInput = panel.querySelector('#rooms-count');
        const adultsInput = panel.querySelector('#adults-count');
        const childrenInput = panel.querySelector('#children-count');
        const calcLabel = panel.querySelector('#breakdown-calc-label');
        const breakdownTotalVal = panel.querySelector('#breakdown-total-val');
        const grandTotalVal = panel.querySelector('#live-grand-total');
        const statusBadge = panel.querySelector('#tourivo-avail-status');
        const bookingForm = panel.querySelector('#tourivo-booking-form');
        const submitBtn = panel.querySelector('#tourivo-submit-btn');
        const alertBox = panel.querySelector('#tourivo-panel-alert');

        let checkAbortController = null;
        let checkDebounceTimer = null;

        // Helper to format currency
        function formatPrice(amount, symbol, position) {
            symbol = symbol || panel.dataset.currency || '$';
            position = position || 'left';
            const formatted = (Math.round(amount * 100) / 100).toFixed(2);
            return position === 'right' ? `${formatted}${symbol}` : `${symbol}${formatted}`;
        }

        // 1. Guest & Room +/- buttons
        panel.querySelectorAll('.counter-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const targetId = this.dataset.target;
                const input = panel.querySelector('#' + targetId);
                if (!input) return;

                let val = parseInt(input.value, 10) || 0;
                const min = parseInt(input.min, 10) || 0;
                const max = parseInt(input.max, 10) || 99;

                if (this.classList.contains('plus-btn')) {
                    if (val < max) val++;
                } else if (this.classList.contains('minus-btn')) {
                    if (val > min) val--;
                }

                input.value = val;
                recalculatePrice();
            });
        });

        // 2. Date input changes
        if (checkInInput) {
            checkInInput.addEventListener('change', function () {
                if (checkOutInput && checkOutInput.value <= this.value) {
                    const nextDay = new Date(this.value);
                    nextDay.setDate(nextDay.getDate() + 1);
                    checkOutInput.value = nextDay.toISOString().split('T')[0];
                }
                recalculatePrice();
            });
        }

        if (checkOutInput) {
            checkOutInput.addEventListener('change', recalculatePrice);
        }

        // 3. Recalculate Live Price & Availability
        function recalculatePrice() {
            const currentUnitPrice = parseFloat(panel.dataset.convertedUnitPrice) || baseUnitPrice;
            const currencySymbol = panel.dataset.currency || '$';
            const adults = parseInt(adultsInput ? adultsInput.value : 1, 10) || 1;
            const children = parseInt(childrenInput ? childrenInput.value : 0, 10) || 0;
            const rooms = parseInt(roomsInput ? roomsInput.value : 1, 10) || 1;
            const totalGuests = adults + children;
            const checkIn = checkInInput ? checkInInput.value : '';
            const checkOut = checkOutInput ? checkOutInput.value : '';

            // Calculate nights / days
            let nightsCount = 1;
            if (checkIn && checkOut && itemType === 'room') {
                const d1 = new Date(checkIn);
                const d2 = new Date(checkOut);
                const diffTime = Math.max(0, d2 - d1);
                nightsCount = Math.max(1, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));
            }

            let estimatedTotal = 0;
            if (itemType === 'room') {
                estimatedTotal = currentUnitPrice * rooms * nightsCount;
                if (calcLabel) {
                    calcLabel.textContent = `${formatPrice(currentUnitPrice, currencySymbol)} × ${rooms} Room(s) × ${nightsCount} Night(s)`;
                }
            } else {
                estimatedTotal = currentUnitPrice * totalGuests;
                if (calcLabel) {
                    calcLabel.textContent = `${formatPrice(currentUnitPrice, currencySymbol)} × ${totalGuests} Guest(s)`;
                }
            }

            if (breakdownTotalVal) {
                breakdownTotalVal.textContent = formatPrice(estimatedTotal, currencySymbol);
            }

            if (grandTotalVal) {
                grandTotalVal.innerHTML = '<strong>' + formatPrice(estimatedTotal, currencySymbol) + '</strong>';
            }

            // Real-time backend verification with debounce & AbortController
            if (window.tourivoData && window.tourivoData.restUrl && checkIn) {
                clearTimeout(checkDebounceTimer);
                checkDebounceTimer = setTimeout(function () {
                    if (checkAbortController) {
                        checkAbortController.abort();
                    }
                    checkAbortController = new AbortController();

                    const checkUrl = new URL(window.tourivoData.restUrl + 'tourivo/v1/availability/check');
                    checkUrl.searchParams.set('item_id', itemId);
                    checkUrl.searchParams.set('item_type', itemType);
                    checkUrl.searchParams.set('start_date', checkIn);
                    if (checkOut) checkUrl.searchParams.set('end_date', checkOut);
                    checkUrl.searchParams.set('guests', totalGuests);
                    checkUrl.searchParams.set('rooms', rooms);

                    fetch(checkUrl.toString(), { signal: checkAbortController.signal })
                        .then(res => res.json())
                        .then(data => {
                            if (data && statusBadge) {
                                statusBadge.innerHTML = '';
                                const badge = document.createElement('span');
                                badge.className = 'status-badge status-check';
                                if (data.available) {
                                    badge.style.background = '#dcfce7';
                                    badge.style.color = '#15803d';
                                    badge.textContent = '✓ ' + (data.message || 'Available');
                                    if (submitBtn) submitBtn.disabled = false;
                                } else {
                                    badge.style.background = '#fee2e2';
                                    badge.style.color = '#991b1b';
                                    badge.textContent = '✕ ' + (data.message || 'Sold Out / Unavailable');
                                    if (submitBtn) submitBtn.disabled = true;
                                }
                                statusBadge.appendChild(badge);
                            }
                        })
                        .catch(err => {
                            if (err.name !== 'AbortError') {
                                // Request failed silently on network issue
                            }
                        });
                }, 300);
            }
        }

        // 4. Handle Direct Booking Form Submission
        if (bookingForm) {
            bookingForm.addEventListener('submit', function (e) {
                e.preventDefault();

                if (!window.tourivoData || !window.tourivoData.restUrl) {
                    alert('Tourivo REST API endpoint is not configured.');
                    return;
                }

                const formData = new FormData(bookingForm);
                const payload = {
                    item_id: itemId,
                    item_type: itemType,
                    check_in: formData.get('check_in'),
                    check_out: formData.get('check_out') || '',
                    rooms: formData.get('rooms') || 1,
                    adults: formData.get('adults') || 1,
                    children: formData.get('children') || 0,
                    customer_name: formData.get('customer_name'),
                    customer_email: formData.get('customer_email'),
                    customer_phone: formData.get('customer_phone'),
                    customer_notes: formData.get('customer_notes') || '',
                    tourivo_hp_check: formData.get('tourivo_hp_check') || '',
                };

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Processing Booking...';
                if (alertBox) {
                    alertBox.style.display = 'none';
                    alertBox.textContent = '';
                }

                fetch(window.tourivoData.restUrl + 'tourivo/v1/bookings/direct', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.tourivoData.nonce || '',
                    },
                    body: JSON.stringify(payload),
                })
                    .then(res => res.json())
                    .then(data => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<span class="dashicons dashicons-lock"></span> Book Now (Pay Offline)';

                        if (data.success) {
                            if (alertBox) {
                                alertBox.className = 'panel-alert alert-success';
                                alertBox.textContent = 'Success! ' + (data.message || 'Your booking has been received.');
                                alertBox.style.display = 'block';
                            }
                            bookingForm.reset();
                        } else {
                            if (alertBox) {
                                alertBox.className = 'panel-alert alert-error';
                                alertBox.textContent = 'Booking Failed: ' + (data.message || 'Please check your inputs.');
                                alertBox.style.display = 'block';
                            }
                        }
                    })
                    .catch(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<span class="dashicons dashicons-lock"></span> Book Now (Pay Offline)';
                        if (alertBox) {
                            alertBox.className = 'panel-alert alert-error';
                            alertBox.textContent = 'An unexpected network error occurred. Please try again.';
                            alertBox.style.display = 'block';
                        }
                    });
            });
        }

        // 5. Trip Inquiry Modal Open / Close / Submit
        const openInquiryBtn = panel.querySelector('#tourivo-open-inquiry-modal');
        const inquiryModal = panel.querySelector('#tourivo-inquiry-modal');
        const closeInquiryBtns = panel.querySelectorAll('.close-inquiry-modal, .tourivo-modal-overlay');
        const inquiryForm = panel.querySelector('#tourivo-inquiry-form');
        const inquirySubmitBtn = panel.querySelector('#tourivo-inquiry-submit-btn');
        const inquiryAlert = panel.querySelector('#tourivo-inquiry-alert');

        if (openInquiryBtn && inquiryModal) {
            openInquiryBtn.addEventListener('click', function () {
                inquiryModal.style.display = 'flex';
                if (inquiryAlert) inquiryAlert.style.display = 'none';
            });

            closeInquiryBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    inquiryModal.style.display = 'none';
                });
            });
        }

        if (inquiryForm) {
            inquiryForm.addEventListener('submit', function (e) {
                e.preventDefault();

                if (!window.tourivoData || !window.tourivoData.ajaxUrl) {
                    alert('AJAX URL is not configured.');
                    return;
                }

                inquirySubmitBtn.disabled = true;
                inquirySubmitBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Sending Inquiry...';
                if (inquiryAlert) inquiryAlert.style.display = 'none';

                const formData = new FormData(inquiryForm);
                formData.append('_wpnonce', window.tourivoData.frontendNonce || '');

                fetch(window.tourivoData.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                })
                    .then(res => res.json())
                    .then(data => {
                        inquirySubmitBtn.disabled = false;
                        inquirySubmitBtn.innerHTML = '✈️ Send Inquiry to Specialists';

                        if (data.success) {
                            if (inquiryAlert) {
                                inquiryAlert.className = 'inquiry-alert alert-success';
                                inquiryAlert.textContent = data.data ? data.data.message : 'Inquiry sent successfully!';
                                inquiryAlert.style.display = 'block';
                            }
                            inquiryForm.reset();
                            setTimeout(() => {
                                inquiryModal.style.display = 'none';
                            }, 2500);
                        } else {
                            if (inquiryAlert) {
                                inquiryAlert.className = 'inquiry-alert alert-error';
                                inquiryAlert.textContent = data.data ? data.data.message : 'Failed to send inquiry.';
                                inquiryAlert.style.display = 'block';
                            }
                        }
                    })
                    .catch(() => {
                        inquirySubmitBtn.disabled = false;
                        inquirySubmitBtn.innerHTML = '✈️ Send Inquiry to Specialists';
                        if (inquiryAlert) {
                            inquiryAlert.className = 'inquiry-alert alert-error';
                            inquiryAlert.textContent = 'Connection error. Please try again.';
                            inquiryAlert.style.display = 'block';
                        }
                    });
            });
        }
    }

    /* =======================================================
       LIVE AJAX SEARCH & FILTER CONTROLLER
       ======================================================= */
    function initLiveFilters() {
        const filterWrappers = document.querySelectorAll('.tourivo-live-filter-wrapper');

        filterWrappers.forEach(wrap => {
            const form = wrap.querySelector('.tourivo-filter-form');
            const rangeInput = wrap.querySelector('.tourivo-filter-range');
            const priceIndicator = wrap.querySelector('.price-val-indicator');
            const resultsGrid = wrap.querySelector('.tourivo-live-results');
            const loader = wrap.querySelector('.tourivo-filter-loader');
            const counterNum = wrap.querySelector('.counter-num');
            const resetBtn = wrap.querySelector('.tourivo-btn-reset-filters');
            const type = wrap.dataset.type || 'tour';
            const count = wrap.dataset.count || 9;
            const cols = wrap.dataset.columns || 3;

            let debounceTimer = null;
            let filterAbortController = null;

            // Update range label live
            if (rangeInput && priceIndicator) {
                rangeInput.addEventListener('input', function () {
                    priceIndicator.textContent = this.value;
                });
            }

            function fetchFilteredResults() {
                if (!window.tourivoData || !window.tourivoData.ajaxUrl) return;

                if (loader) loader.style.display = 'flex';
                if (resultsGrid) resultsGrid.style.opacity = '0.4';

                if (filterAbortController) {
                    filterAbortController.abort();
                }
                filterAbortController = new AbortController();

                const formData = new FormData(form);
                const params = new URLSearchParams(formData);
                params.set('action', 'tourivo_filter_items');
                params.set('nonce', window.tourivoData.frontendNonce || '');
                params.set('type', type);
                params.set('count', count);
                params.set('columns', cols);

                fetch(`${window.tourivoData.ajaxUrl}?${params.toString()}`, { signal: filterAbortController.signal })
                    .then(res => res.json())
                    .then(res => {
                        if (loader) loader.style.display = 'none';
                        if (resultsGrid) {
                            resultsGrid.style.opacity = '1';
                            if (res.success && res.data) {
                                resultsGrid.innerHTML = res.data.html;
                                // Refresh wishlist button active states for newly injected cards
                                syncWishlistButtonStates();
                                // Count items
                                const cardCount = resultsGrid.querySelectorAll('.tourivo-card').length;
                                if (counterNum) counterNum.textContent = cardCount;
                            }
                        }
                    })
                    .catch(err => {
                        if (err.name !== 'AbortError') {
                            if (loader) loader.style.display = 'none';
                            if (resultsGrid) resultsGrid.style.opacity = '1';
                        }
                    });
            }

            // Initial counter sync
            if (resultsGrid && counterNum) {
                counterNum.textContent = resultsGrid.querySelectorAll('.tourivo-card').length;
            }

            // Listen to inputs
            wrap.querySelectorAll('.tourivo-filter-input, .tourivo-filter-range').forEach(input => {
                input.addEventListener('input', function () {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(fetchFilteredResults, 350);
                });
            });

            wrap.querySelectorAll('.tourivo-filter-select').forEach(select => {
                select.addEventListener('change', fetchFilteredResults);
            });

            // Reset button
            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
                    form.reset();
                    if (rangeInput && priceIndicator) {
                        rangeInput.value = rangeInput.max;
                        priceIndicator.textContent = rangeInput.max;
                    }
                    fetchFilteredResults();
                });
            }
        });
    }

    initLiveFilters();

    /* =======================================================
       TRAVELER WISHLIST CONTROLLER
       ======================================================= */
    const WISHLIST_STORAGE_KEY = 'tourivo_traveler_wishlist';

    function getLocalWishlist() {
        try {
            const data = localStorage.getItem(WISHLIST_STORAGE_KEY);
            return data ? JSON.parse(data) : [];
        } catch (e) {
            return [];
        }
    }

    function saveLocalWishlist(ids) {
        try {
            localStorage.setItem(WISHLIST_STORAGE_KEY, JSON.stringify(ids));
        } catch (e) {}

        // Background server sync if available
        if (window.tourivoData && window.tourivoData.ajaxUrl) {
            const fd = new FormData();
            fd.append('action', 'tourivo_sync_wishlist');
            fd.append('nonce', window.tourivoData.frontendNonce || '');
            ids.forEach(id => fd.append('ids[]', id));
            fetch(window.tourivoData.ajaxUrl, { method: 'POST', body: fd }).catch(() => {});
        }
    }

    function toggleWishlistItem(id) {
        let list = getLocalWishlist();
        const numId = parseInt(id, 10);
        const index = list.indexOf(numId);

        if (index > -1) {
            list.splice(index, 1);
        } else {
            list.push(numId);
        }

        saveLocalWishlist(list);
        syncWishlistButtonStates();
        return index === -1; // true if added, false if removed
    }

    function syncWishlistButtonStates() {
        const list = getLocalWishlist();
        document.querySelectorAll('.tourivo-wishlist-toggle').forEach(btn => {
            const id = parseInt(btn.dataset.id, 10);
            if (list.includes(id)) {
                btn.classList.add('is-active');
            } else {
                btn.classList.remove('is-active');
            }
        });

        document.querySelectorAll('.tourivo-wishlist-toggle-single').forEach(btn => {
            const id = parseInt(btn.dataset.id, 10);
            const label = btn.querySelector('.wishlist-btn-label');
            if (list.includes(id)) {
                btn.classList.add('is-active');
                if (label) label.textContent = 'Saved';
            } else {
                btn.classList.remove('is-active');
                if (label) label.textContent = 'Save';
            }
        });
    }

    // Global Click Listener for Wishlist Toggle Buttons
    document.addEventListener('click', function (e) {
        const cardBtn = e.target.closest('.tourivo-wishlist-toggle');
        if (cardBtn) {
            e.preventDefault();
            e.stopPropagation();
            const id = cardBtn.dataset.id;
            toggleWishlistItem(id);
            return;
        }

        const singleBtn = e.target.closest('.tourivo-wishlist-toggle-single');
        if (singleBtn) {
            e.preventDefault();
            const id = singleBtn.dataset.id;
            toggleWishlistItem(id);
            return;
        }
    });

    syncWishlistButtonStates();

    // Wishlist Shortcode Page Loader
    function initWishlistPage() {
        const wishlistWrap = document.querySelector('.tourivo-wishlist-wrap');
        if (!wishlistWrap) return;

        const loader = wishlistWrap.querySelector('#tourivo-wishlist-loading');
        const grid = wishlistWrap.querySelector('#tourivo-wishlist-grid');
        const emptyState = wishlistWrap.querySelector('#tourivo-wishlist-empty');
        const countLabel = wishlistWrap.querySelector('#tourivo-wishlist-total-count');

        const savedIds = getLocalWishlist();

        if (countLabel) countLabel.textContent = savedIds.length;

        if (savedIds.length === 0) {
            if (loader) loader.style.display = 'none';
            if (grid) grid.style.display = 'none';
            if (emptyState) emptyState.style.display = 'block';
            return;
        }

        if (!window.tourivoData || !window.tourivoData.ajaxUrl) return;

        const formData = new FormData();
        formData.append('action', 'tourivo_get_wishlist_items');
        formData.append('nonce', window.tourivoData.frontendNonce || '');
        savedIds.forEach(id => formData.append('ids[]', id));

        fetch(window.tourivoData.ajaxUrl, {
            method: 'POST',
            body: formData,
        })
            .then(res => res.json())
            .then(res => {
                if (loader) loader.style.display = 'none';

                if (res.success && res.data && res.data.html.trim().length > 0) {
                    if (grid) {
                        grid.innerHTML = res.data.html;
                        grid.style.display = 'grid';
                    }
                    if (emptyState) emptyState.style.display = 'none';
                    syncWishlistButtonStates();
                } else {
                    if (grid) grid.style.display = 'none';
                    if (emptyState) emptyState.style.display = 'block';
                }
            })
            .catch(() => {
                if (loader) loader.style.display = 'none';
            });
    }

    initWishlistPage();

    /* =======================================================
       INTERACTIVE STAR RATING PICKER
       ======================================================= */
    function initStarRatingPicker() {
        const picker = document.querySelector('#tourivo-star-picker');
        const ratingInput = document.querySelector('#tourivo_rating_input');

        if (!picker || !ratingInput) return;

        const stars = picker.querySelectorAll('.picker-star');

        function setStars(val) {
            stars.forEach(s => {
                const starVal = parseInt(s.dataset.val, 10);
                if (starVal <= val) {
                    s.classList.add('selected');
                } else {
                    s.classList.remove('selected');
                }
            });
        }

        // Default value 5
        setStars(5);

        stars.forEach(s => {
            s.addEventListener('mouseenter', function () {
                const hoverVal = parseInt(this.dataset.val, 10);
                stars.forEach(st => {
                    const stVal = parseInt(st.dataset.val, 10);
                    if (stVal <= hoverVal) {
                        st.classList.add('hover');
                    } else {
                        st.classList.remove('hover');
                    }
                });
            });

            s.addEventListener('mouseleave', function () {
                stars.forEach(st => st.classList.remove('hover'));
            });

            s.addEventListener('click', function () {
                const clickVal = parseInt(this.dataset.val, 10);
                ratingInput.value = clickVal;
                setStars(clickVal);
            });
        });
    }

    initStarRatingPicker();

    /* =======================================================
       MULTI-CURRENCY FRONTEND SWITCHER
       ======================================================= */
    function initCurrencySwitcher() {
        const currencySelect = document.getElementById('tourivo-currency-select');
        if (!currencySelect) return;

        let storedCurrency = null;
        try {
            storedCurrency = localStorage.getItem('tourivo_active_currency');
        } catch (e) {}

        if (storedCurrency && currencySelect.querySelector(`option[value="${storedCurrency}"]`)) {
            currencySelect.value = storedCurrency;
        }

        currencySelect.addEventListener('change', function () {
            const selectedOpt = this.options[this.selectedIndex];
            const code = selectedOpt.value;
            const symbol = selectedOpt.dataset.symbol || '$';
            const rate = parseFloat(selectedOpt.dataset.rate) || 1.0;

            try {
                localStorage.setItem('tourivo_active_currency', code);
            } catch (e) {}

            // Update live booking panel if present
            panels.forEach(panel => {
                panel.dataset.currency = symbol;
                const basePrice = parseFloat(panel.dataset.unitPrice) || 0;
                panel.dataset.convertedUnitPrice = (basePrice * rate).toFixed(2);

                const livePriceAmount = panel.querySelector('#tourivo-live-price');
                if (livePriceAmount) {
                    livePriceAmount.textContent = `${symbol}${(basePrice * rate).toFixed(2)}`;
                }

                // Dispatch input event to recalculate
                const checkInInput = panel.querySelector('#tourivo-check-in');
                if (checkInInput) {
                    checkInInput.dispatchEvent(new Event('change'));
                }
            });
        });
    }

    initCurrencySwitcher();
});


