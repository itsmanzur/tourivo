/**
 * Tourivo Frontend Booking Interactive JavaScript (Zero-jQuery Reactive UI)
 */

document.addEventListener('DOMContentLoaded', function () {
    const panels = document.querySelectorAll('.tourivo-booking-panel');

    function formatMoney(amount, symbol) {
        const money = (window.tourivoData && window.tourivoData.money) || {};
        const decimals = Number.isInteger(money.decimals) ? money.decimals : 2;
        const decimalSep = typeof money.decimal === 'string' ? money.decimal : '.';
        const thousandSep = typeof money.thousand === 'string' ? money.thousand : ',';
        const position = money.position || 'left';
        const glyph = symbol || money.symbol || '$';
        const value = Number(amount);
        const negative = value < 0;
        const fixed = Math.abs(Number.isFinite(value) ? value : 0).toFixed(decimals);
        const parts = fixed.split('.');
        const whole = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
        let number = decimals > 0 ? whole + decimalSep + (parts[1] || '') : whole;
        if (negative) {
            number = '-' + number;
        }
        if (money.bangla) {
            const digits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
            number = number.replace(/\d/g, function (digit) {
                return digits[digit];
            });
        }
        if (position === 'right') return number + glyph;
        if (position === 'left_space') return glyph + ' ' + number;
        if (position === 'right_space') return number + ' ' + glyph;
        return glyph + number;
    }

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
        const infantsInput = panel.querySelector('#infants-count');
        const breakdownItems = panel.querySelector('#tourivo-breakdown-items');
        const calcLabel = panel.querySelector('#breakdown-calc-label');
        const breakdownTotalVal = panel.querySelector('#breakdown-total-val');
        const subtotalRow = panel.querySelector('#breakdown-subtotal-row');
        const subtotalVal = panel.querySelector('#breakdown-subtotal-val');
        const discountRow = panel.querySelector('#breakdown-discount-row');
        const discountLabel = panel.querySelector('#breakdown-discount-label');
        const discountVal = panel.querySelector('#breakdown-discount-val');
        const taxRow = panel.querySelector('#breakdown-tax-row');
        const taxLabel = panel.querySelector('#breakdown-tax-label');
        const taxVal = panel.querySelector('#breakdown-tax-val');
        const grandTotalVal = panel.querySelector('#live-grand-total');
        const statusBadge = panel.querySelector('#tourivo-avail-status');
        const bookingForm = panel.querySelector('#tourivo-booking-form');
        const submitBtn = panel.querySelector('#tourivo-submit-btn');
        const alertBox = panel.querySelector('#tourivo-panel-alert');

        let checkAbortController = null;
        let checkDebounceTimer = null;
        let quoteSerial = 0;
        const priceBox = panel.querySelector('#tourivo-price-breakdown');

        function setQuotePending(pending) {
            if (!priceBox) return;
            priceBox.classList.toggle('is-calculating', pending);
            priceBox.setAttribute('aria-busy', pending ? 'true' : 'false');
            let status = priceBox.querySelector('.tourivo-calculating');
            if (!pending) {
                if (status) status.remove();
                return;
            }
            if (!status) {
                status = document.createElement('p');
                status.className = 'tourivo-calculating';
                priceBox.appendChild(status);
            }
            const label = window.tourivoData && window.tourivoData.i18n && window.tourivoData.i18n.calculating;
            status.textContent = label || 'Calculating the price...';
        }

        function syncCheckoutStep(available) {
            const datesReady = itemType !== 'room' || !!(checkOutInput && checkOutInput.value);
            const open = !!available && datesReady;
            panel.querySelectorAll('.tourivo-checkout-step').forEach(function (el) {
                el.hidden = !open;
            });
        }

        // Helper to format currency
        function formatPrice(amount, symbol) {
            return formatMoney(amount, symbol || panel.dataset.currency || '');
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

        function addDaysIso(iso, days) {
            const parts = String(iso || '').split('-').map(Number);
            if (parts.length !== 3 || parts.some(n => !n && n !== 0)) {
                return iso;
            }
            const next = new Date(parts[0], parts[1] - 1, parts[2] + days);
            const month = String(next.getMonth() + 1).padStart(2, '0');
            const day = String(next.getDate()).padStart(2, '0');
            return next.getFullYear() + '-' + month + '-' + day;
        }

        // 2. Date input changes
        if (checkInInput) {
            checkInInput.addEventListener('change', function () {
                if (checkOutInput && checkOutInput.value && checkOutInput.value <= this.value) {
                    checkOutInput.value = addDaysIso(this.value, 1);
                }
                recalculatePrice();
            });
        }

        if (checkOutInput) {
            checkOutInput.addEventListener('change', recalculatePrice);
        }

        panel.querySelectorAll('.tourivo-addon-checkbox').forEach(function (cb) {
            cb.addEventListener('change', recalculatePrice);
        });

        const couponInput = panel.querySelector('[name="coupon_code"]');
        const couponMessage = panel.querySelector('.tourivo-coupon-message');
        panel.querySelector('.tourivo-apply-coupon')?.addEventListener('click', function () {
            if (checkInInput && !checkInInput.value) {
                if (couponMessage) {
                    couponMessage.textContent = 'Choose your travel dates before applying a code.';
                }
                return;
            }
            recalculatePrice();
        });

        initAvailabilityCalendar();

        function initAvailabilityCalendar() {
            const cal = panel.querySelector('.tourivo-cal');
            const summary = panel.querySelector('.tourivo-cal-summary');
            const dateFields = panel.querySelector('.tourivo-date-fields');
            if (!cal || !checkInInput) {
                return;
            }

            const copy = (window.tourivoData && window.tourivoData.calendar) || {};
            const weekdays = Array.isArray(copy.weekdays) && copy.weekdays.length === 7
                ? copy.weekdays
                : ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const months = Array.isArray(copy.months) && copy.months.length === 12
                ? copy.months
                : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            const text = {
                soldOut: copy.soldOut || 'Sold out',
                left: copy.left || '%d left',
                loading: copy.loading || 'Loading dates...',
                pickCheckout: copy.pickCheckout || 'Now choose a check-out date.',
                closedRange: copy.closedRange || 'Those nights are not fully open. Choose another check-out.',
                range: copy.range || 'Check-in: %1$s | Check-out: %2$s',
            };

            const today = cal.dataset.today || '';
            const title = cal.querySelector('.tourivo-cal-title');
            const weekdayRow = cal.querySelector('.tourivo-cal-weekdays');
            const grid = cal.querySelector('.tourivo-cal-grid');
            const note = cal.querySelector('.tourivo-cal-note');
            const prev = cal.querySelector('.tourivo-cal-prev');
            const next = cal.querySelector('.tourivo-cal-next');
            const cache = {};
            const inflight = {};
            let awaitingCheckout = false;
            let busy = false;

            const startFrom = checkInInput.value || today;
            const startParts = startFrom.split('-').map(Number);
            const view = {
                y: startParts[0] || new Date().getFullYear(),
                m: startParts[1] || (new Date().getMonth() + 1),
            };

            function useNativeDates() {
                cal.hidden = true;
                if (summary) summary.hidden = true;
                dateFields?.classList.add('is-native');
            }

            function pretty(iso) {
                const parts = String(iso || '').split('-').map(Number);
                if (parts.length !== 3) return iso;
                return parts[2] + ' ' + (months[parts[1] - 1] || '');
            }

            function updateSummary() {
                if (!summary) return;
                const cin = checkInInput.value || '';
                const cout = checkOutInput ? checkOutInput.value : '';
                if (itemType === 'room' && cin && cout) {
                    summary.textContent = text.range.replace('%1$s', pretty(cin)).replace('%2$s', pretty(cout));
                    return;
                }
                summary.textContent = cin ? pretty(cin) : '';
            }

            function setNote(message, warn) {
                if (!note) return;
                note.textContent = message || '';
                note.classList.toggle('is-warn', !!warn && !!message);
            }

            function dayInfo(iso) {
                const parts = iso.split('-').map(Number);
                const monthData = cache[parts[0] + '-' + parts[1]];
                return monthData ? monthData[iso] : null;
            }

            function isClosed(info) {
                return !info || info.status === 'sold_out' || info.status === 'blocked' || Number(info.spots) <= 0;
            }

            function monthIndex(year, month) {
                return year * 12 + (month - 1);
            }

            function paint() {
                if (!grid || !title) return;
                const todayParts = today.split('-').map(Number);
                const ahead = monthIndex(view.y, view.m) - monthIndex(todayParts[0] || view.y, todayParts[1] || view.m);
                if (prev) prev.disabled = ahead <= 0;
                if (next) next.disabled = ahead >= 18;
                title.textContent = (months[view.m - 1] || '') + ' ' + view.y;

                if (weekdayRow && !weekdayRow.childElementCount) {
                    weekdays.forEach(function (name) {
                        const cell = document.createElement('span');
                        cell.textContent = name;
                        weekdayRow.appendChild(cell);
                    });
                }

                grid.textContent = '';
                const monthData = cache[view.y + '-' + view.m];
                if (!monthData) {
                    const loading = document.createElement('p');
                    loading.className = 'tourivo-cal-loading';
                    loading.textContent = text.loading;
                    grid.appendChild(loading);
                    return;
                }

                const firstDow = new Date(view.y, view.m - 1, 1).getDay();
                const daysInMonth = new Date(view.y, view.m, 0).getDate();
                const cin = checkInInput.value || '';
                const cout = checkOutInput ? checkOutInput.value : '';

                for (let i = 0; i < firstDow; i++) {
                    grid.appendChild(document.createElement('span')).className = 'tourivo-cal-pad';
                }

                for (let day = 1; day <= daysInMonth; day++) {
                    const iso = view.y + '-' + String(view.m).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                    const info = monthData[iso];
                    const closed = isClosed(info);
                    const past = today && iso < today;
                    const canLeave = awaitingCheckout && cin && iso > cin;
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'tourivo-cal-day';
                    button.dataset.date = iso;
                    if (past) button.classList.add('is-past');
                    if (closed) button.classList.add('is-soldout');
                    else if (info && info.status === 'limited') button.classList.add('is-limited');
                    if (cin && iso === cin) button.classList.add('is-checkin');
                    if (cout && iso === cout) button.classList.add('is-checkout');
                    if (cin && cout && iso > cin && iso < cout) button.classList.add('is-in-range');
                    button.disabled = past || !info || (closed && !canLeave);

                    const num = document.createElement('span');
                    num.className = 'tourivo-cal-num';
                    num.textContent = String(day);
                    const spots = document.createElement('span');
                    spots.className = 'tourivo-cal-spots';
                    if (!past && info) {
                        spots.textContent = closed ? text.soldOut : String(info.spots);
                    }
                    button.append(num, spots);

                    let label = pretty(iso);
                    if (!past && info) {
                        label += ', ' + (closed ? text.soldOut : text.left.replace('%d', String(info.spots)));
                    }
                    button.setAttribute('aria-label', label);
                    button.setAttribute('aria-pressed', (iso === cin || iso === cout) ? 'true' : 'false');
                    grid.appendChild(button);
                }
                updateSummary();
            }

            async function fetchMonth(year, month) {
                const key = year + '-' + month;
                if (cache[key]) return;
                if (inflight[key]) {
                    await inflight[key];
                    return;
                }
                const rest = window.tourivoData && window.tourivoData.restUrl;
                if (!rest) throw new Error('calendar');
                const url = new URL(rest + 'tourivo/v1/availability/calendar');
                url.searchParams.set('item_id', itemId);
                url.searchParams.set('item_type', itemType);
                url.searchParams.set('year', String(year));
                url.searchParams.set('month', String(month));
                inflight[key] = fetch(url.toString())
                    .then(function (res) {
                        if (!res.ok) throw new Error('calendar');
                        return res.json();
                    })
                    .then(function (data) {
                        if (!data || !data.calendar) throw new Error('calendar');
                        cache[key] = data.calendar;
                    })
                    .finally(function () {
                        delete inflight[key];
                    });
                await inflight[key];
            }

            async function showMonth(year, month) {
                view.y = year;
                view.m = month;
                paint();
                try {
                    await fetchMonth(year, month);
                } catch (err) {
                    if (!cache[year + '-' + month] && Object.keys(cache).length === 0) {
                        useNativeDates();
                        return;
                    }
                }
                if (view.y === year && view.m === month) paint();
            }

            function shiftMonth(delta) {
                let year = view.y;
                let month = view.m + delta;
                if (month < 1) {
                    month = 12;
                    year -= 1;
                }
                if (month > 12) {
                    month = 1;
                    year += 1;
                }
                showMonth(year, month);
            }

            async function rangeIsOpen(start, end) {
                let cursor = start;
                let guard = 0;
                while (cursor < end && guard < 370) {
                    const parts = cursor.split('-').map(Number);
                    await fetchMonth(parts[0], parts[1]);
                    if (isClosed(dayInfo(cursor)) || (today && cursor < today)) return false;
                    cursor = addDaysIso(cursor, 1);
                    guard += 1;
                }
                return cursor === end;
            }

            async function choose(date) {
                if (busy || !date || (today && date < today)) return;
                const info = dayInfo(date);
                if (isClosed(info) && !(awaitingCheckout && checkInInput.value && date > checkInInput.value)) return;

                if (itemType !== 'room') {
                    checkInInput.value = date;
                    setNote('');
                    paint();
                    checkInInput.dispatchEvent(new Event('change'));
                    return;
                }

                const cin = checkInInput.value;
                if (!awaitingCheckout || !cin || date <= cin) {
                    if (isClosed(info)) return;
                    awaitingCheckout = true;
                    checkInInput.value = date;
                    if (checkOutInput) checkOutInput.value = '';
                    setNote(text.pickCheckout, false);
                    paint();
                    checkInInput.dispatchEvent(new Event('change'));
                    return;
                }

                busy = true;
                let open = false;
                try {
                    open = await rangeIsOpen(cin, date);
                } catch (err) {
                    open = false;
                }
                busy = false;
                if (!open) {
                    setNote(text.closedRange, true);
                    paint();
                    return;
                }
                if (checkOutInput) checkOutInput.value = date;
                awaitingCheckout = false;
                setNote('');
                paint();
                checkInInput.dispatchEvent(new Event('change'));
            }

            prev?.addEventListener('click', function () { shiftMonth(-1); });
            next?.addEventListener('click', function () { shiftMonth(1); });
            grid?.addEventListener('click', function (event) {
                const button = event.target.closest('.tourivo-cal-day');
                if (!button || button.disabled) return;
                choose(button.dataset.date || '');
            });

            if (!window.tourivoData || !window.tourivoData.restUrl) {
                useNativeDates();
                return;
            }
            updateSummary();
            showMonth(view.y, view.m);
        }

        // 3. Recalculate Live Price, Breakdown & Availability
        function recalculatePrice() {
            const currentUnitPrice = parseFloat(panel.dataset.convertedUnitPrice) || baseUnitPrice;
            const currencySymbol = panel.dataset.currency || '$';
            const adults = parseInt(adultsInput ? adultsInput.value : 1, 10) || 1;
            const children = parseInt(childrenInput ? childrenInput.value : 0, 10) || 0;
            const infants = parseInt(infantsInput ? infantsInput.value : 0, 10) || 0;
            const rooms = parseInt(roomsInput ? roomsInput.value : 1, 10) || 1;
            const totalGuests = adults + children + infants;
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
                estimatedTotal = currentUnitPrice * (adults + children);
                if (calcLabel) {
                    calcLabel.textContent = `${formatPrice(currentUnitPrice, currencySymbol)} × ${adults + children} Guest(s)`;
                }
            }

            if (breakdownTotalVal) {
                breakdownTotalVal.textContent = formatPrice(estimatedTotal, currencySymbol);
            }

            if (grandTotalVal) {
                grandTotalVal.innerHTML = '<strong>' + formatPrice(estimatedTotal, currencySymbol) + '</strong>';
            }

            // Real-time backend quote & availability verification with debounce (~250ms) & AbortController
            if (window.tourivoData && window.tourivoData.restUrl && checkIn) {
                if (itemType === 'room' && checkOutInput && !checkOutInput.value) {
                    syncCheckoutStep(false);
                }
                setQuotePending(true);
                clearTimeout(checkDebounceTimer);
                checkDebounceTimer = setTimeout(function () {
                    if (checkAbortController) {
                        checkAbortController.abort();
                    }
                    checkAbortController = new AbortController();
                    const serial = ++quoteSerial;

                    // 1. Fetch Authoritative Price Quote
                    const quoteUrl = new URL(window.tourivoData.restUrl + 'tourivo/v1/pricing/quote');
                    quoteUrl.searchParams.set('item_id', itemId);
                    quoteUrl.searchParams.set('item_type', itemType);
                    quoteUrl.searchParams.set('check_in', checkIn);
                    if (checkOut) quoteUrl.searchParams.set('check_out', checkOut);
                    quoteUrl.searchParams.set('adults', adults);
                    quoteUrl.searchParams.set('children', children);
                    quoteUrl.searchParams.set('infants', infants);
                    quoteUrl.searchParams.set('rooms', rooms);
                    panel.querySelectorAll('.tourivo-addon-checkbox:checked').forEach(function (cb) {
                        if (cb.value) {
                            quoteUrl.searchParams.append('selected_addons[]', cb.value);
                        }
                    });
                    if (couponInput && couponInput.value.trim()) {
                        quoteUrl.searchParams.set('coupon_code', couponInput.value.trim());
                    }

                    fetch(quoteUrl.toString(), { signal: checkAbortController.signal })
                        .then(res => res.json())
                        .then(quote => {
                            if (serial !== quoteSerial) return;
                            setQuotePending(false);
                            if (quote && quote.success) {
                                const symbol = quote.currency_symbol || currencySymbol;

                                // Render itemized lines
                                if (breakdownItems && Array.isArray(quote.lines) && quote.lines.length > 0) {
                                    breakdownItems.innerHTML = '';
                                    quote.lines.forEach(line => {
                                        const row = document.createElement('div');
                                        row.className = 'breakdown-row';
                                        const desc = document.createElement('span');
                                        desc.className = 'breakdown-desc';
                                        desc.textContent = line.adjustment
                                            ? line.title
                                            : `${line.title} (${line.quantity} × ${formatPrice(line.rate, symbol)})`;
                                        const val = document.createElement('span');
                                        val.className = 'breakdown-val';
                                        val.textContent = Number(line.total) < 0
                                            ? '-' + formatPrice(-Number(line.total), symbol)
                                            : formatPrice(line.total, symbol);
                                        row.append(desc, val);
                                        breakdownItems.appendChild(row);
                                    });
                                }

                                // Discount, including a promo code applied on top of the quoted total
                                const couponAmount = quote.coupon && quote.coupon.amount > 0 ? Number(quote.coupon.amount) : 0;
                                if (couponMessage) {
                                    if (quote.coupon_error) {
                                        couponMessage.style.color = '#b91c1c';
                                        couponMessage.textContent = quote.coupon_error;
                                    } else if (quote.coupon && quote.coupon.label) {
                                        couponMessage.style.color = '#0f766e';
                                        couponMessage.textContent = quote.coupon.label;
                                    } else if (couponInput && couponInput.value.trim()) {
                                        couponMessage.textContent = '';
                                    }
                                }
                                // Discounts already itemised as lines are not repeated in the discount row.
                                const unlistedDiscount = Math.max(0, Number(quote.discount || 0) - Number(quote.line_discount || 0));
                                const shownDiscount = couponAmount > 0 ? couponAmount : unlistedDiscount;
                                const shownSubtotal = couponAmount > 0 ? quote.subtotal + couponAmount : quote.subtotal;
                                if (subtotalRow && subtotalVal) {
                                    if (shownDiscount > 0 || quote.tax > 0) {
                                        subtotalRow.style.display = 'flex';
                                        subtotalVal.textContent = formatPrice(shownSubtotal, symbol);
                                    } else {
                                        subtotalRow.style.display = 'none';
                                    }
                                }

                                if (discountRow && discountVal) {
                                    if (shownDiscount > 0) {
                                        discountRow.style.display = 'flex';
                                        discountVal.textContent = '-' + formatPrice(shownDiscount, symbol);
                                        if (discountLabel) {
                                            discountLabel.textContent = (quote.coupon && quote.coupon.label) ? quote.coupon.label : 'Discount';
                                        }
                                    } else {
                                        discountRow.style.display = 'none';
                                    }
                                }

                                // Tax
                                if (taxRow && taxVal) {
                                    if (quote.tax > 0) {
                                        taxRow.style.display = 'flex';
                                        taxVal.textContent = formatPrice(quote.tax, symbol);
                                        if (taxLabel && quote.tax_label) {
                                            taxLabel.textContent = quote.tax_label + (quote.tax_rate > 0 ? ` (${quote.tax_rate}%)` : '');
                                        }
                                    } else {
                                        taxRow.style.display = 'none';
                                    }
                                }

                                // Total
                                if (grandTotalVal) {
                                    grandTotalVal.innerHTML = '<strong>' + formatPrice(quote.total, symbol) + '</strong>';
                                }
                            }
                        })
                        .catch(err => {
                            if (err.name === 'AbortError' || serial !== quoteSerial) return;
                            setQuotePending(false);
                        });

                    // 2. Availability Check
                    const checkUrl = new URL(window.tourivoData.restUrl + 'tourivo/v1/availability/check');
                    checkUrl.searchParams.set('item_id', itemId);
                    checkUrl.searchParams.set('item_type', itemType);
                    checkUrl.searchParams.set('start_date', checkIn);
                    if (checkOut) checkUrl.searchParams.set('end_date', checkOut);
                    checkUrl.searchParams.set('guests', adults + children);
                    checkUrl.searchParams.set('rooms', rooms);

                    fetch(checkUrl.toString(), { signal: checkAbortController.signal })
                        .then(res => res.json())
                        .then(data => {
                            if (serial !== quoteSerial || !data) return;
                            syncCheckoutStep(!!data.available);
                            if (statusBadge) {
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
                            if (err.name === 'AbortError' || serial !== quoteSerial) return;
                            syncCheckoutStep(true);
                        });
                }, 250);
            } else {
                setQuotePending(false);
            }
        }

        recalculatePrice();

        // 4. Handle Direct Booking Form Submission
        if (bookingForm) {
            bookingForm.addEventListener('submit', function (e) {
                e.preventDefault();

                if (!window.tourivoData || !window.tourivoData.restUrl) {
                    alert('Tourivo REST API endpoint is not configured.');
                    return;
                }

                const formData = new FormData(bookingForm);
                const customFields = {};
                formData.forEach(function (value, key) {
                    const match = key.match(/^tourivo_fields\[(.+)\]$/);
                    if (match) {
                        customFields[match[1]] = value;
                    }
                });
                const payload = {
                    item_id: itemId,
                    item_type: itemType,
                    check_in: formData.get('check_in'),
                    check_out: formData.get('check_out') || '',
                    rooms: formData.get('rooms') || 1,
                    adults: formData.get('adults') || 1,
                    children: formData.get('children') || 0,
                    infants: formData.get('infants') || 0,
                    customer_name: formData.get('customer_name'),
                    customer_email: formData.get('customer_email'),
                    customer_phone: formData.get('customer_phone'),
                    customer_notes: formData.get('customer_notes') || '',
                    payment_method: formData.get('payment_method') || 'offline',
                    consent: formData.get('consent') ? 1 : 0,
                    tourivo_hp_check: formData.get('tourivo_hp_check') || '',
                    selected_addons: formData.getAll('selected_addons[]'),
                    coupon_code: formData.get('coupon_code') || '',
                    custom_fields: customFields,
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
                            if (data.redirect_mode === 'thankyou' && data.redirect_url) {
                                window.location.href = data.redirect_url;
                                return;
                            }
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
                    livePriceAmount.textContent = formatMoney(basePrice * rate, symbol);
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

    /* =======================================================
       STEP 4: ZERO-DEPENDENCY PHOTO LIGHTBOX & GALLERY
       ======================================================= */
    function initLightbox() {
        const galleryImages = Array.from(document.querySelectorAll(
            '.tourivo-hero-img img, .tourivo-featured-banner img, .tourivo-gallery-item img, .tourivo-gallery-grid img, .tourivo-room-card-img img'
        ));

        if (galleryImages.length === 0) return;

        let currentIndex = 0;
        let activeImagesList = [];

        let overlay = document.getElementById('tourivo-lightbox-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'tourivo-lightbox-overlay';
            overlay.className = 'tourivo-lightbox-overlay';
            overlay.innerHTML = `
                <div class="tourivo-lightbox-container">
                    <button type="button" class="tourivo-lightbox-close" aria-label="Close">&times;</button>
                    <button type="button" class="tourivo-lightbox-prev" aria-label="Previous">&#8249;</button>
                    <img class="tourivo-lightbox-img" src="" alt="">
                    <div class="tourivo-lightbox-caption"></div>
                    <button type="button" class="tourivo-lightbox-next" aria-label="Next">&#8250;</button>
                </div>
            `;
            document.body.appendChild(overlay);
        }

        const imgEl = overlay.querySelector('.tourivo-lightbox-img');
        const captionEl = overlay.querySelector('.tourivo-lightbox-caption');
        const closeBtn = overlay.querySelector('.tourivo-lightbox-close');
        const prevBtn = overlay.querySelector('.tourivo-lightbox-prev');
        const nextBtn = overlay.querySelector('.tourivo-lightbox-next');

        function showImage(index) {
            if (index < 0) index = activeImagesList.length - 1;
            if (index >= activeImagesList.length) index = 0;
            currentIndex = index;

            const target = activeImagesList[currentIndex];
            const src = target.getAttribute('data-full-src') || target.currentSrc || target.src;
            const caption = target.getAttribute('alt') || target.getAttribute('title') || '';

            imgEl.src = src;
            imgEl.alt = caption;
            captionEl.textContent = caption;

            prevBtn.style.display = activeImagesList.length > 1 ? 'flex' : 'none';
            nextBtn.style.display = activeImagesList.length > 1 ? 'flex' : 'none';
        }

        function openLightbox(list, index) {
            activeImagesList = list;
            showImage(index);
            overlay.classList.add('is-active');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            overlay.classList.remove('is-active');
            document.body.style.overflow = '';
            imgEl.src = '';
        }

        galleryImages.forEach((img) => {
            img.style.cursor = 'zoom-in';
            img.addEventListener('click', function (e) {
                e.preventDefault();
                const parentSection = img.closest('.tourivo-container') || document;
                const sectionImgs = Array.from(parentSection.querySelectorAll(
                    '.tourivo-hero-img img, .tourivo-featured-banner img, .tourivo-gallery-item img, .tourivo-gallery-grid img, .tourivo-room-card-img img'
                ));
                const list = sectionImgs.length > 0 ? sectionImgs : galleryImages;
                const idx = list.indexOf(img);
                openLightbox(list, idx >= 0 ? idx : 0);
            });
        });

        closeBtn.addEventListener('click', closeLightbox);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                closeLightbox();
            }
        });

        prevBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            showImage(currentIndex - 1);
        });

        nextBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            showImage(currentIndex + 1);
        });

        document.addEventListener('keydown', function (e) {
            if (!overlay.classList.contains('is-active')) return;
            if (e.key === 'Escape') {
                closeLightbox();
            } else if (e.key === 'ArrowLeft') {
                showImage(currentIndex - 1);
            } else if (e.key === 'ArrowRight') {
                showImage(currentIndex + 1);
            }
        });
    }

    initLightbox();

    function initSearchCalendars() {
        const forms = document.querySelectorAll('.tourivo-search-form');
        if (!forms.length || !window.tourivoData || !window.tourivoData.restUrl) {
            return;
        }

        const text = window.tourivoData.calendar || {};
        const weekdays = text.weekdays || [];
        const months = text.months || [];
        const banglaDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

        function digits(value) {
            const raw = String(value);
            if (!window.tourivoData.money || !window.tourivoData.money.bangla) {
                return raw;
            }
            return raw.replace(/\d/g, function (digit) {
                return banglaDigits[digit];
            });
        }

        forms.forEach(function (form) {
            const field = form.querySelector('.tourivo-search-date');
            const button = field && field.querySelector('.tourivo-search-date-btn');
            const label = field && field.querySelector('.tourivo-search-date-label');
            const input = field && field.querySelector('.tourivo-search-native');
            const pop = field && field.querySelector('.tourivo-search-cal');
            const grid = field && field.querySelector('.tourivo-cal-grid');
            const title = field && field.querySelector('.tourivo-cal-title');
            const prev = field && field.querySelector('.tourivo-cal-prev');
            const next = field && field.querySelector('.tourivo-cal-next');
            const heads = field && field.querySelector('.tourivo-cal-weekdays');
            if (!field || !button || !label || !input || !pop || !grid || !title || !prev || !next) {
                return;
            }

            const todayIso = pop.dataset.today || '';
            let view = todayIso ? new Date(todayIso + 'T12:00:00') : new Date();
            if (/^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
                view = new Date(input.value + 'T12:00:00');
            }
            let cache = {};
            let serial = 0;
            let open = false;

            if (heads && weekdays.length) {
                heads.innerHTML = weekdays.map(function (name) {
                    return '<span>' + name + '</span>';
                }).join('');
            }

            function pretty(iso) {
                const parts = iso.split('-');
                return digits(Number(parts[2])) + ' ' + (months[Number(parts[1]) - 1] || '');
            }

            function setLabel() {
                label.textContent = input.value ? pretty(input.value) : (text.anyDay || 'Any day');
            }
            setLabel();

            function guests() {
                const guestInput = form.querySelector('[name="tourivo_guests"]');
                const count = guestInput ? parseInt(guestInput.value, 10) : 1;
                return Math.max(1, Math.min(50, Number.isFinite(count) ? count : 1));
            }

            function destination() {
                const select = form.querySelector('[name="tourivo_destination"]');
                return select ? select.value : '';
            }

            function cacheKey(year, month) {
                return year + '-' + month + '|' + destination() + '|' + guests();
            }

            function load(year, month) {
                const key = cacheKey(year, month);
                if (cache[key]) {
                    return Promise.resolve(cache[key]);
                }
                const url = new URL(window.tourivoData.restUrl + 'tourivo/v1/availability/search');
                url.searchParams.set('year', String(year));
                url.searchParams.set('month', String(month));
                url.searchParams.set('guests', String(guests()));
                if (destination()) {
                    url.searchParams.set('destination', destination());
                }
                return fetch(url.toString()).then(function (res) {
                    if (!res.ok) {
                        throw new Error('search calendar');
                    }
                    return res.json();
                }).then(function (data) {
                    cache[key] = data.calendar || {};
                    return cache[key];
                });
            }

            function paint(days) {
                const year = view.getFullYear();
                const month = view.getMonth();
                title.textContent = (months[month] || '') + ' ' + digits(year);
                const startPad = new Date(year, month, 1).getDay();
                const count = new Date(year, month + 1, 0).getDate();
                const min = todayIso ? new Date(todayIso + 'T12:00:00') : new Date();
                const max = new Date(min.getFullYear(), min.getMonth() + 18, 1);
                prev.disabled = year < min.getFullYear() || (year === min.getFullYear() && month <= min.getMonth());
                next.disabled = new Date(year, month, 1) >= max;

                grid.innerHTML = '';
                for (let pad = 0; pad < startPad; pad++) {
                    const blank = document.createElement('span');
                    blank.className = 'tourivo-cal-pad';
                    grid.appendChild(blank);
                }

                for (let day = 1; day <= count; day++) {
                    const iso = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                    const info = days[iso] || { open: 0, hotels: 0, status: 'sold_out' };
                    const past = todayIso !== '' && iso < todayIso;
                    const tours = Number(info.open) || 0;
                    const hotels = Number(info.hotels) || 0;
                    const closed = past || (tours < 1 && hotels < 1);
                    const dayButton = document.createElement('button');
                    dayButton.type = 'button';
                    dayButton.className = 'tourivo-cal-day ' + (closed ? 'is-soldout' : (info.status === 'limited' ? 'is-limited' : 'is-available'));
                    if (input.value === iso) {
                        dayButton.classList.add('is-selected');
                    }
                    dayButton.dataset.date = iso;
                    dayButton.disabled = closed;
                    const tourLabel = (text.toursOpen || '%d tours open').replace('%d', digits(tours));
                    const hotelLabel = (text.hotelsOpen || '%d hotels open').replace('%d', digits(hotels));
                    dayButton.setAttribute('aria-label', digits(day) + ' ' + (months[month] || '') + ', ' + tourLabel + ', ' + hotelLabel);
                    const spots = past ? '' : (closed ? (text.soldOut || '0') : '<span class="tourivo-cal-tours">' + digits(tours) + '</span><span class="tourivo-cal-hotels">' + digits(hotels) + '</span>');
                    dayButton.innerHTML = '<span class="tourivo-cal-num">' + digits(day) + '</span><span class="tourivo-cal-spots">' + spots + '</span>';
                    grid.appendChild(dayButton);
                }

                if (input.value && days[input.value] && (Number(days[input.value].open) || 0) < 1 && (Number(days[input.value].hotels) || 0) < 1) {
                    input.value = '';
                    setLabel();
                }
            }

            function showMonth() {
                const mine = ++serial;
                grid.textContent = text.loading || '';
                load(view.getFullYear(), view.getMonth() + 1).then(function (days) {
                    if (mine !== serial) {
                        return;
                    }
                    paint(days);
                }).catch(function () {
                    if (mine !== serial) {
                        return;
                    }
                    field.classList.add('is-native');
                    pop.hidden = true;
                    open = false;
                });
            }

            function toggle(force) {
                const current = field.dataset.open === '1';
                open = typeof force === 'boolean' ? force : !current;
                field.dataset.open = open ? '1' : '0';
                pop.hidden = !open;
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    showMonth();
                }
            }

            button.addEventListener('click', function () {
                toggle();
            });
            prev.addEventListener('click', function () {
                view = new Date(view.getFullYear(), view.getMonth() - 1, 1);
                showMonth();
            });
            next.addEventListener('click', function () {
                view = new Date(view.getFullYear(), view.getMonth() + 1, 1);
                showMonth();
            });
            grid.addEventListener('click', function (event) {
                const day = event.target.closest('.tourivo-cal-day');
                if (!day || day.disabled || !day.dataset.date) {
                    return;
                }
                input.value = day.dataset.date;
                setLabel();
                toggle(false);
            });

            function refresh() {
                cache = {};
                if (open) {
                    showMonth();
                    return;
                }
                if (!/^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
                    return;
                }
                const parts = input.value.split('-');
                const selected = input.value;
                load(Number(parts[0]), Number(parts[1])).then(function (days) {
                    const info = days[selected];
                    if (!info || ((Number(info.open) || 0) < 1 && (Number(info.hotels) || 0) < 1)) {
                        input.value = '';
                        setLabel();
                    }
                }).catch(function () {});
            }
            const guestInput = form.querySelector('[name="tourivo_guests"]');
            const destInput = form.querySelector('[name="tourivo_destination"]');
            const shownGuests = form.querySelector('.tourivo-guest-digits');
            if (shownGuests && guestInput) {
                const guestAscii = { '০': '0', '১': '1', '২': '2', '৩': '3', '৪': '4', '৫': '5', '৬': '6', '৭': '7', '৮': '8', '৯': '9' };
                function readGuests(raw) {
                    const ascii = String(raw).replace(/[০-৯]/g, function (digit) {
                        return guestAscii[digit] || '';
                    }).replace(/[^\d]/g, '');
                    if (ascii === '') {
                        return null;
                    }
                    const count = parseInt(ascii, 10);
                    if (!Number.isFinite(count)) {
                        return null;
                    }
                    return Math.max(1, Math.min(50, count));
                }
                function paintGuests(commit) {
                    const parsed = readGuests(shownGuests.value);
                    if (parsed === null) {
                        if (!commit) {
                            return;
                        }
                        const fallback = readGuests(guestInput.value) || 1;
                        guestInput.value = String(fallback);
                        shownGuests.value = digits(fallback);
                        return;
                    }
                    const next = String(parsed);
                    const pretty = digits(parsed);
                    if (guestInput.value !== next) {
                        guestInput.value = next;
                        guestInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    if (shownGuests.value !== pretty) {
                        shownGuests.value = pretty;
                    }
                }
                shownGuests.addEventListener('input', function () {
                    paintGuests(false);
                });
                shownGuests.addEventListener('blur', function () {
                    paintGuests(true);
                });
                form.addEventListener('submit', function () {
                    paintGuests(true);
                });
            }
            if (guestInput) {
                guestInput.addEventListener('change', refresh);
            }
            if (destInput) {
                destInput.addEventListener('change', refresh);
            }
            document.addEventListener('click', function (event) {
                if (!field.contains(event.target)) {
                    toggle(false);
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }
            document.querySelectorAll('.tourivo-search-date').forEach(function (field) {
                field.dataset.open = '0';
                const pop = field.querySelector('.tourivo-search-cal');
                const button = field.querySelector('.tourivo-search-date-btn');
                if (pop) {
                    pop.hidden = true;
                }
                if (button) {
                    button.setAttribute('aria-expanded', 'false');
                }
            });
        });
    }

    initSearchCalendars();
    initGuestSteppers();
});

function initGuestSteppers() {
    const banglaDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
    const guestAscii = { '০': '0', '১': '1', '২': '2', '৩': '3', '৪': '4', '৫': '5', '৬': '6', '৭': '7', '৮': '8', '৯': '9' };

    function toDigits(value) {
        const raw = String(value);
        if (!window.tourivoData || !window.tourivoData.money || !window.tourivoData.money.bangla) {
            return raw;
        }
        return raw.replace(/\d/g, function (digit) {
            return banglaDigits[digit];
        });
    }

    function readGuests(raw) {
        const ascii = String(raw).replace(/[০-৯]/g, function (digit) {
            return guestAscii[digit] || '';
        }).replace(/[^\d]/g, '');
        if (ascii === '') {
            return null;
        }
        const count = parseInt(ascii, 10);
        if (!Number.isFinite(count)) {
            return null;
        }
        return Math.max(1, Math.min(50, count));
    }

    document.querySelectorAll('.tourivo-guest-stepper').forEach(function (stepper) {
        const field = stepper.closest('.guests-field') || stepper;
        const shown = stepper.querySelector('.tourivo-guest-digits');
        const named = field.querySelector('[name="tourivo_guests"]');
        const minus = stepper.querySelector('.tourivo-guest-minus');
        const plus = stepper.querySelector('.tourivo-guest-plus');
        if (!named || !minus || !plus) {
            return;
        }

        function current() {
            const fromShown = shown ? readGuests(shown.value) : null;
            return fromShown !== null ? fromShown : (readGuests(named.value) || 1);
        }

        function setCount(next) {
            const count = Math.max(1, Math.min(50, next));
            const value = String(count);
            if (named.value !== value) {
                named.value = value;
                named.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (shown) {
                shown.value = toDigits(count);
            }
            minus.disabled = count <= 1;
            plus.disabled = count >= 50;
        }

        minus.addEventListener('click', function () {
            setCount(current() - 1);
        });
        plus.addEventListener('click', function () {
            setCount(current() + 1);
        });
        setCount(current());
    });
}


