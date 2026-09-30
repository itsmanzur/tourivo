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
});
