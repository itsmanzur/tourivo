<?php
/**
 * Tourivo Customer Account / My Bookings Template
 *
 * @package Tourivo
 * @var bool             $isGuest
 * @var string           $title
 * @var WP_User|null     $currentUser
 * @var bool             $showStats
 * @var array<string, mixed> $stats
 * @var string           $defaultTab
 * @var array<array<string, mixed>> $bookings
 * @var string           $loginUrl
 * @var string           $registerUrl
 * @var bool             $canRegister
 * @var string           $lookupUrl
 * @var string           $nonce
 * @var string           $primaryColor
 */

if (!defined('ABSPATH')) {
    exit;
}

$brandColor = !empty($primaryColor) ? $primaryColor : '#0d9488';
?>

<div class="tourivo-my-bookings-container" style="--trv-primary: <?php echo esc_attr($brandColor); ?>;">

<?php if (!empty($isGuest)) : ?>
    <!-- 1. Guest Visitor State -->
    <div class="tourivo-my-bookings-login-prompt">
        <div class="tourivo-prompt-card">
            <div class="tourivo-prompt-icon" aria-hidden="true">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="<?php echo esc_attr($brandColor); ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>
            <h2 class="tourivo-prompt-title"><?php echo esc_html($title); ?></h2>
            <p class="tourivo-prompt-desc">
                <?php esc_html_e('Please log in to your account to view your travel itineraries, download vouchers, or manage your bookings.', 'tourivo'); ?>
            </p>

            <div class="tourivo-prompt-actions">
                <a href="<?php echo esc_url($loginUrl); ?>" class="tourivo-btn-account tourivo-btn-primary">
                    <?php esc_html_e('Log in to Your Account', 'tourivo'); ?>
                </a>
                <?php if (!empty($canRegister) && !empty($registerUrl)) : ?>
                    <a href="<?php echo esc_url($registerUrl); ?>" class="tourivo-btn-account tourivo-btn-outline">
                        <?php esc_html_e('Register an Account', 'tourivo'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($lookupUrl)) : ?>
                <div class="tourivo-prompt-guest-lookup">
                    <span><?php esc_html_e('Booked as a guest without an account?', 'tourivo'); ?></span>
                    <a href="<?php echo esc_url($lookupUrl); ?>" class="tourivo-guest-link">
                        <?php esc_html_e('Track Your Booking with Reference Code →', 'tourivo'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else : ?>
    <!-- 2. Authenticated Traveler Dashboard State -->
    <div class="tourivo-my-bookings-dashboard">
        <!-- Top Welcome Header -->
        <div class="tourivo-dashboard-header">
            <div>
                <span class="tourivo-traveler-welcome">
                    <?php
                    printf(
                        /* translators: %s: Traveler display name */
                        esc_html__('Welcome back, %s 👋', 'tourivo'),
                        esc_html($currentUser ? ($currentUser->display_name ?: $currentUser->user_login) : '')
                    );
                    ?>
                </span>
                <h1 class="tourivo-dashboard-title"><?php echo esc_html($title); ?></h1>
            </div>
            <?php if (!empty($lookupUrl)) : ?>
                <div>
                    <a href="<?php echo esc_url($lookupUrl); ?>" class="tourivo-link-lookup">
                        🔍 <?php esc_html_e('Quick Booking Lookup', 'tourivo'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($showStats) && !empty($stats)) : ?>
            <!-- Stats KPI Cards -->
            <div class="tourivo-stats-grid">
                <div class="tourivo-stat-card">
                    <div class="tourivo-stat-icon">🌴</div>
                    <div class="tourivo-stat-data">
                        <span class="tourivo-stat-value"><?php echo esc_html((string) $stats['total']); ?></span>
                        <span class="tourivo-stat-label"><?php esc_html_e('Total Trips', 'tourivo'); ?></span>
                    </div>
                </div>

                <div class="tourivo-stat-card">
                    <div class="tourivo-stat-icon" style="color:#0d9488;">📅</div>
                    <div class="tourivo-stat-data">
                        <span class="tourivo-stat-value"><?php echo esc_html((string) $stats['upcoming']); ?></span>
                        <span class="tourivo-stat-label"><?php esc_html_e('Upcoming Trips', 'tourivo'); ?></span>
                    </div>
                </div>

                <div class="tourivo-stat-card">
                    <div class="tourivo-stat-icon" style="color:#10b981;">✅</div>
                    <div class="tourivo-stat-data">
                        <span class="tourivo-stat-value"><?php echo esc_html((string) $stats['completed']); ?></span>
                        <span class="tourivo-stat-label"><?php esc_html_e('Completed Trips', 'tourivo'); ?></span>
                    </div>
                </div>

                <div class="tourivo-stat-card">
                    <div class="tourivo-stat-icon" style="color:#6366f1;">💳</div>
                    <div class="tourivo-stat-data">
                        <span class="tourivo-stat-value"><?php echo esc_html($stats['total_spent']); ?></span>
                        <span class="tourivo-stat-label"><?php esc_html_e('Total Spent', 'tourivo'); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="tourivo-bookings-tabs" role="tablist">
            <button type="button" class="tourivo-tab-btn active" data-tab="all" role="tab" aria-selected="true">
                <?php esc_html_e('All Bookings', 'tourivo'); ?>
                <span class="tourivo-tab-count"><?php echo esc_html((string) ($stats['total'] ?? count($bookings))); ?></span>
            </button>
            <button type="button" class="tourivo-tab-btn" data-tab="upcoming" role="tab" aria-selected="false">
                <?php esc_html_e('Upcoming', 'tourivo'); ?>
                <span class="tourivo-tab-count"><?php echo esc_html((string) ($stats['upcoming'] ?? 0)); ?></span>
            </button>
            <button type="button" class="tourivo-tab-btn" data-tab="completed" role="tab" aria-selected="false">
                <?php esc_html_e('Completed', 'tourivo'); ?>
                <span class="tourivo-tab-count"><?php echo esc_html((string) ($stats['completed'] ?? 0)); ?></span>
            </button>
            <button type="button" class="tourivo-tab-btn" data-tab="cancelled" role="tab" aria-selected="false">
                <?php esc_html_e('Cancelled', 'tourivo'); ?>
                <span class="tourivo-tab-count"><?php echo esc_html((string) ($stats['cancelled'] ?? 0)); ?></span>
            </button>
        </div>

        <!-- Bookings List -->
        <div class="tourivo-bookings-list" id="tourivoBookingsList">
            <?php if (empty($bookings)) : ?>
                <div class="tourivo-no-bookings">
                    <div style="font-size: 40px; margin-bottom: 12px;">🗺️</div>
                    <h3><?php esc_html_e('No bookings found yet', 'tourivo'); ?></h3>
                    <p><?php esc_html_e("When you reserve a tour package or hotel stay, it will appear here in your dashboard.", 'tourivo'); ?></p>
                </div>
            <?php else : ?>
                <?php foreach ($bookings as $b) : ?>
                    <?php
                    $statusBg = '#f1f5f9';
                    $statusColor = '#475569';
                    if ($b['booking_status'] === 'confirmed' || $b['booking_status'] === 'completed') {
                        $statusBg = '#dcfce7';
                        $statusColor = '#15803d';
                    } elseif ($b['booking_status'] === 'pending') {
                        $statusBg = '#fef3c7';
                        $statusColor = '#b45309';
                    } elseif ($b['booking_status'] === 'cancelled') {
                        $statusBg = '#fee2e2';
                        $statusColor = '#b91c1c';
                    }

                    $payBg = ($b['payment_status'] === 'paid') ? '#dcfce7' : '#f1f5f9';
                    $payColor = ($b['payment_status'] === 'paid') ? '#15803d' : '#475569';
                    $item = $b['first_item'];
                    $itemTypeLabel = ($item && ($item->item_type === 'room' || $item->item_type === 'hotel_room'))
                        ? __('Hotel Stay', 'tourivo')
                        : __('Tour Package', 'tourivo');

                    $checkInDate = $item && !empty($item->check_in) ? gmdate('M d, Y', strtotime((string) $item->check_in)) : '—';
                    $checkOutDate = $item && !empty($item->check_out) ? gmdate('M d, Y', strtotime((string) $item->check_out)) : '';
                    $dateRange = $checkOutDate ? "{$checkInDate} → {$checkOutDate}" : $checkInDate;
                    ?>
                    <div class="tourivo-booking-card" data-category="<?php echo esc_attr((string) $b['category']); ?>">
                        <div class="tourivo-card-header">
                            <div class="tourivo-card-reference">
                                <span class="tourivo-ref-label"><?php esc_html_e('Booking Code', 'tourivo'); ?></span>
                                <strong class="tourivo-ref-code">#<?php echo esc_html((string) $b['booking_code']); ?></strong>
                            </div>

                            <div class="tourivo-card-badges">
                                <span class="tourivo-badge" style="background: <?php echo esc_attr($statusBg); ?>; color: <?php echo esc_attr($statusColor); ?>;">
                                    <?php echo esc_html(ucfirst((string) $b['booking_status'])); ?>
                                </span>
                                <span class="tourivo-badge" style="background: <?php echo esc_attr($payBg); ?>; color: <?php echo esc_attr($payColor); ?>;">
                                    <?php echo esc_html(ucfirst((string) $b['payment_status'])); ?>
                                </span>
                            </div>
                        </div>

                        <div class="tourivo-card-body">
                            <div class="tourivo-item-summary">
                                <div class="tourivo-item-type-badge"><?php echo esc_html($itemTypeLabel); ?></div>
                                <h3 class="tourivo-item-title"><?php echo esc_html($item ? (string) $item->item_title : __('Tour / Room', 'tourivo')); ?></h3>
                            </div>

                            <div class="tourivo-meta-grid">
                                <div>
                                    <span class="tourivo-meta-label">📅 <?php esc_html_e('Date / Duration', 'tourivo'); ?></span>
                                    <span class="tourivo-meta-val"><?php echo esc_html($dateRange); ?></span>
                                </div>
                                <?php if ($item && !empty($item->time_slot)) : ?>
                                    <div>
                                        <span class="tourivo-meta-label">⏰ <?php esc_html_e('Time Slot', 'tourivo'); ?></span>
                                        <span class="tourivo-meta-val"><?php echo esc_html((string) $item->time_slot); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <span class="tourivo-meta-label">👥 <?php esc_html_e('Guests / Qty', 'tourivo'); ?></span>
                                    <span class="tourivo-meta-val"><?php echo esc_html($item ? (string) $item->quantity : '1'); ?></span>
                                </div>
                                <div>
                                    <span class="tourivo-meta-label">💰 <?php esc_html_e('Total Amount', 'tourivo'); ?></span>
                                    <strong class="tourivo-meta-val" style="color: #0f172a; font-size: 15px;"><?php echo esc_html((string) $b['total_amount']); ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="tourivo-card-actions">
                            <button type="button" class="tourivo-btn-sm tourivo-btn-outline btnViewDetails" data-booking-id="<?php echo esc_attr((string) $b['id']); ?>">
                                📄 <?php esc_html_e('View Details', 'tourivo'); ?>
                            </button>

                            <a href="<?php echo esc_url((string) $b['voucher_url']); ?>" target="_blank" rel="noopener noreferrer" class="tourivo-btn-sm tourivo-btn-outline">
                                🎫 <?php esc_html_e('Download Voucher', 'tourivo'); ?>
                            </a>

                            <a href="<?php echo esc_url((string) $b['calendar_url']); ?>" class="tourivo-btn-sm tourivo-btn-outline">
                                📅 <?php esc_html_e('Add to Calendar', 'tourivo'); ?>
                            </a>

                            <?php if (!empty($b['can_cancel'])) : ?>
                                <button type="button" class="tourivo-btn-sm tourivo-btn-danger btnOpenCancelModal" data-booking-id="<?php echo esc_attr((string) $b['id']); ?>" data-code="<?php echo esc_attr((string) $b['booking_code']); ?>">
                                    ✕ <?php esc_html_e('Cancel Booking', 'tourivo'); ?>
                                </button>
                            <?php elseif (!empty($b['cancel_requested'])) : ?>
                                <span class="tourivo-cancel-pending-pill">
                                    ⏳ <?php esc_html_e('Cancellation Requested', 'tourivo'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. Booking Details Modal -->
    <div class="tourivo-modal-backdrop" id="tourivoDetailsModal" style="display:none;" aria-hidden="true">
        <div class="tourivo-modal-box">
            <div class="tourivo-modal-header">
                <h3 class="tourivo-modal-title"><?php esc_html_e('Reservation Details', 'tourivo'); ?></h3>
                <button type="button" class="tourivo-modal-close" onclick="TourivoMyBookings.closeDetailsModal();" aria-label="<?php esc_attr_e('Close', 'tourivo'); ?>">×</button>
            </div>
            <div class="tourivo-modal-body" id="tourivoDetailsBody">
                <div class="tourivo-modal-loader"><?php esc_html_e('Loading details...', 'tourivo'); ?></div>
            </div>
            <div class="tourivo-modal-footer">
                <button type="button" class="tourivo-btn-account tourivo-btn-outline" onclick="TourivoMyBookings.closeDetailsModal();">
                    <?php esc_html_e('Close', 'tourivo'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- 4. Cancellation Confirmation Modal -->
    <div class="tourivo-modal-backdrop" id="tourivoCancelModal" style="display:none;" aria-hidden="true">
        <div class="tourivo-modal-box">
            <div class="tourivo-modal-header">
                <h3 class="tourivo-modal-title"><?php esc_html_e('Confirm Cancellation', 'tourivo'); ?></h3>
                <button type="button" class="tourivo-modal-close" onclick="TourivoMyBookings.closeCancelModal();" aria-label="<?php esc_attr_e('Close', 'tourivo'); ?>">×</button>
            </div>
            <div class="tourivo-modal-body">
                <p style="margin-top:0; color:#475569;">
                    <?php esc_html_e('Are you sure you wish to cancel this reservation? Depending on our cancellation policy and booking status, this will either immediately release your reservation or submit a review request to our operations team.', 'tourivo'); ?>
                </p>
                <div style="margin-bottom: 16px;">
                    <label for="trvCancelReason" style="display:block; font-weight:600; margin-bottom:6px; color:#0f172a;">
                        <?php esc_html_e('Reason for Cancellation (optional):', 'tourivo'); ?>
                    </label>
                    <textarea id="trvCancelReason" rows="3" class="tourivo-input-textarea" placeholder="<?php esc_attr_e('e.g., Changed travel dates, medical reason...', 'tourivo'); ?>"></textarea>
                </div>
                <div id="trvCancelFeedback" style="display:none; padding:10px; border-radius:6px; font-size:13px; margin-bottom:12px;"></div>
            </div>
            <div class="tourivo-modal-footer">
                <button type="button" class="tourivo-btn-account tourivo-btn-outline" onclick="TourivoMyBookings.closeCancelModal();">
                    <?php esc_html_e('Nevermind, Keep Booking', 'tourivo'); ?>
                </button>
                <button type="button" class="tourivo-btn-account tourivo-btn-danger" id="btnConfirmCancel">
                    <?php esc_html_e('Confirm Cancellation', 'tourivo'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Inline Modern CSS Styles -->
    <style>
        .tourivo-my-bookings-container {
            max-width: 1100px;
            margin: 30px auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            box-sizing: border-box;
        }
        .tourivo-my-bookings-container * { box-sizing: border-box; }
        .tourivo-prompt-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 48px 24px;
            text-align: center;
            max-width: 580px;
            margin: 40px auto;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .tourivo-prompt-icon { margin-bottom: 16px; }
        .tourivo-prompt-title { font-size: 26px; font-weight: 700; margin: 0 0 10px; color: #0f172a; }
        .tourivo-prompt-desc { font-size: 15px; color: #64748b; line-height: 1.6; margin: 0 0 24px; }
        .tourivo-prompt-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-bottom: 24px; }
        .tourivo-prompt-guest-lookup { font-size: 14px; color: #64748b; border-top: 1px solid #f1f5f9; padding-top: 20px; }
        .tourivo-guest-link { color: var(--trv-primary); font-weight: 600; text-decoration: none; display: block; margin-top: 6px; }
        .tourivo-guest-link:hover { text-decoration: underline; }

        .tourivo-dashboard-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .tourivo-traveler-welcome { font-size: 14px; color: #64748b; font-weight: 500; display: block; margin-bottom: 4px; }
        .tourivo-dashboard-title { font-size: 28px; font-weight: 800; margin: 0; color: #0f172a; letter-spacing: -0.5px; }
        .tourivo-link-lookup { font-size: 14px; font-weight: 600; color: var(--trv-primary); text-decoration: none; padding: 6px 12px; background: #f0fdfa; border-radius: 6px; }

        .tourivo-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .tourivo-stat-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; display: flex; align-items: center; gap: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .tourivo-stat-icon { font-size: 28px; line-height: 1; }
        .tourivo-stat-value { font-size: 22px; font-weight: 800; color: #0f172a; display: block; line-height: 1.2; }
        .tourivo-stat-label { font-size: 13px; color: #64748b; font-weight: 500; }

        .tourivo-bookings-tabs { display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; margin-bottom: 24px; overflow-x: auto; padding-bottom: 2px; }
        .tourivo-tab-btn { background: none; border: none; padding: 10px 16px; font-size: 15px; font-weight: 600; color: #64748b; cursor: pointer; border-radius: 6px 6px 0 0; display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; transition: color 0.15s, border-color 0.15s; }
        .tourivo-tab-btn:hover { color: #0f172a; }
        .tourivo-tab-btn.active { color: var(--trv-primary); border-bottom: 2px solid var(--trv-primary); margin-bottom: -2px; }
        .tourivo-tab-count { background: #f1f5f9; color: #475569; font-size: 12px; font-weight: 700; padding: 2px 7px; border-radius: 10px; }
        .tourivo-tab-btn.active .tourivo-tab-count { background: #ccfbf1; color: #0f766e; }

        .tourivo-booking-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 16px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: box-shadow 0.2s, border-color 0.2s; }
        .tourivo-booking-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.06); }
        .tourivo-card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
        .tourivo-ref-label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; display: block; }
        .tourivo-ref-code { font-size: 16px; font-weight: 700; color: #0f172a; }
        .tourivo-card-badges { display: flex; gap: 8px; }
        .tourivo-badge { display: inline-block; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 6px; line-height: 1.2; text-transform: capitalize; }

        .tourivo-card-body { margin-bottom: 16px; }
        .tourivo-item-type-badge { font-size: 11px; text-transform: uppercase; font-weight: 700; color: #6366f1; background: #e0e7ff; padding: 2px 8px; border-radius: 4px; display: inline-block; margin-bottom: 6px; letter-spacing: 0.5px; }
        .tourivo-item-title { font-size: 18px; font-weight: 700; margin: 0 0 14px; color: #0f172a; line-height: 1.3; }
        .tourivo-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #f1f5f9; }
        .tourivo-meta-label { font-size: 12px; color: #64748b; display: block; margin-bottom: 2px; }
        .tourivo-meta-val { font-size: 14px; font-weight: 600; color: #334155; }

        .tourivo-card-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; padding-top: 8px; }
        .tourivo-btn-sm { padding: 8px 14px; font-size: 13px; font-weight: 600; border-radius: 6px; text-decoration: none; cursor: pointer; border: 1px solid transparent; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s; }
        .tourivo-btn-outline { background: #ffffff; border-color: #cbd5e1; color: #334155; }
        .tourivo-btn-outline:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }
        .tourivo-btn-danger { background: #fff1f2; border-color: #fecdd3; color: #e11d48; }
        .tourivo-btn-danger:hover { background: #ffe4e6; color: #be123c; }
        .tourivo-cancel-pending-pill { font-size: 12px; font-weight: 600; color: #b45309; background: #fef3c7; padding: 6px 12px; border-radius: 6px; }

        .tourivo-btn-account { padding: 10px 20px; font-size: 14px; font-weight: 600; border-radius: 8px; text-decoration: none; cursor: pointer; border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center; transition: all 0.15s; }
        .tourivo-btn-primary { background: var(--trv-primary); color: #ffffff; }
        .tourivo-btn-primary:hover { opacity: 0.92; }

        .tourivo-no-bookings { text-align: center; padding: 48px 24px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; color: #64748b; }
        .tourivo-no-bookings h3 { margin: 0 0 6px; color: #0f172a; }

        /* Modals */
        .tourivo-modal-backdrop { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); display: flex; align-items: center; justify-content: center; z-index: 99999; padding: 16px; }
        .tourivo-modal-box { background: #ffffff; border-radius: 12px; max-width: 580px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; animation: tourivoFadeIn 0.2s ease; }
        .tourivo-modal-header { padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .tourivo-modal-title { margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; }
        .tourivo-modal-close { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; line-height: 1; }
        .tourivo-modal-close:hover { color: #0f172a; }
        .tourivo-modal-body { padding: 24px; max-height: 70vh; overflow-y: auto; }
        .tourivo-modal-footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; }
        .tourivo-input-textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 14px; resize: vertical; box-sizing: border-box; }
        .tourivo-input-textarea:focus { outline: none; border-color: var(--trv-primary); }

        @keyframes tourivoFadeIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>

    <!-- Inline Vanilla JS Engine -->
    <script>
        window.TourivoMyBookings = {
            nonce: "<?php echo esc_js($nonce); ?>",
            ajaxUrl: "<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>",
            targetBookingId: 0,

            init: function() {
                // Tab switching
                var tabBtns = document.querySelectorAll('.tourivo-tab-btn');
                tabBtns.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        tabBtns.forEach(function(b) { b.classList.remove('active'); b.setAttribute('aria-selected', 'false'); });
                        btn.classList.add('active');
                        btn.setAttribute('aria-selected', 'true');
                        TourivoMyBookings.filterCards(btn.getAttribute('data-tab'));
                    });
                });

                // Open Cancel Modal
                document.querySelectorAll('.btnOpenCancelModal').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        TourivoMyBookings.targetBookingId = parseInt(btn.getAttribute('data-booking-id'), 10);
                        document.getElementById('trvCancelReason').value = '';
                        var feedback = document.getElementById('trvCancelFeedback');
                        feedback.style.display = 'none';
                        feedback.innerHTML = '';
                        document.getElementById('tourivoCancelModal').style.display = 'flex';
                    });
                });

                // Confirm Cancellation
                var btnConfirm = document.getElementById('btnConfirmCancel');
                if (btnConfirm) {
                    btnConfirm.addEventListener('click', function() {
                        TourivoMyBookings.submitCancellation();
                    });
                }

                // View Details
                document.querySelectorAll('.btnViewDetails').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var id = parseInt(btn.getAttribute('data-booking-id'), 10);
                        TourivoMyBookings.fetchDetails(id);
                    });
                });
            },

            filterCards: function(tab) {
                var cards = document.querySelectorAll('.tourivo-booking-card');
                cards.forEach(function(card) {
                    var category = card.getAttribute('data-category');
                    if (tab === 'all' || category === tab) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            },

            closeCancelModal: function() {
                document.getElementById('tourivoCancelModal').style.display = 'none';
                TourivoMyBookings.targetBookingId = 0;
            },

            closeDetailsModal: function() {
                document.getElementById('tourivoDetailsModal').style.display = 'none';
            },

            submitCancellation: function() {
                var btn = document.getElementById('btnConfirmCancel');
                var reason = document.getElementById('trvCancelReason').value.trim();
                var feedback = document.getElementById('trvCancelFeedback');

                btn.disabled = true;
                btn.textContent = "<?php echo esc_js(__('Processing...', 'tourivo')); ?>";

                var formData = new FormData();
                formData.append('action', 'tourivo_my_bookings_cancel');
                formData.append('nonce', TourivoMyBookings.nonce);
                formData.append('booking_id', TourivoMyBookings.targetBookingId);
                formData.append('reason', reason);

                fetch(TourivoMyBookings.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(res) {
                    btn.disabled = false;
                    btn.textContent = "<?php echo esc_js(__('Confirm Cancellation', 'tourivo')); ?>";

                    if (res && res.success) {
                        feedback.style.display = 'block';
                        feedback.style.background = '#dcfce7';
                        feedback.style.color = '#15803d';
                        feedback.style.border = '1px solid #86efac';
                        feedback.textContent = res.data && res.data.message ? res.data.message : "<?php echo esc_js(__('Cancellation submitted successfully.', 'tourivo')); ?>";

                        setTimeout(function() {
                            window.location.reload();
                        }, 1200);
                    } else {
                        feedback.style.display = 'block';
                        feedback.style.background = '#fee2e2';
                        feedback.style.color = '#b91c1c';
                        feedback.style.border = '1px solid #fca5a5';
                        feedback.textContent = res && res.data && res.data.message ? res.data.message : "<?php echo esc_js(__('Failed to process cancellation.', 'tourivo')); ?>";
                    }
                })
                .catch(function(err) {
                    btn.disabled = false;
                    btn.textContent = "<?php echo esc_js(__('Confirm Cancellation', 'tourivo')); ?>";
                    feedback.style.display = 'block';
                    feedback.style.background = '#fee2e2';
                    feedback.style.color = '#b91c1c';
                    feedback.textContent = "<?php echo esc_js(__('A network error occurred. Please try again.', 'tourivo')); ?>";
                });
            },

            fetchDetails: function(bookingId) {
                var modal = document.getElementById('tourivoDetailsModal');
                var body = document.getElementById('tourivoDetailsBody');
                modal.style.display = 'flex';
                body.innerHTML = '<div style="text-align:center; padding:30px; color:#64748b;"><?php echo esc_js(__('Loading booking details...', 'tourivo')); ?></div>';

                var formData = new FormData();
                formData.append('action', 'tourivo_my_bookings_details');
                formData.append('nonce', TourivoMyBookings.nonce);
                formData.append('booking_id', bookingId);

                fetch(TourivoMyBookings.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(res) {
                    if (res && res.success && res.data) {
                        var b = res.data.booking;
                        var items = res.data.items || [];
                        var html = '<div style="margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">';
                        html += '<div style="font-size:12px; color:#64748b; text-transform:uppercase;"><?php echo esc_js(__('Booking Reference', 'tourivo')); ?></div>';
                        html += '<strong style="font-size:20px; color:#0f172a;">#' + b.booking_code + '</strong>';
                        html += '<div style="margin-top:4px; font-size:13px; color:#64748b;"><?php echo esc_js(__('Placed on:', 'tourivo')); ?> ' + b.created_at + '</div>';
                        html += '</div>';

                        html += '<h4 style="margin:16px 0 8px; font-size:15px; color:#0f172a;"><?php echo esc_js(__('Reserved Items', 'tourivo')); ?></h4>';
                        items.forEach(function(it) {
                            var range = it.check_out ? (it.check_in + ' → ' + it.check_out) : it.check_in;
                            html += '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:8px;">';
                            html += '<div style="display:flex; justify-content:space-between; font-weight:700; color:#0f172a;">';
                            html += '<span>' + it.item_title + '</span>';
                            html += '<span>' + it.total_price + '</span>';
                            html += '</div>';
                            html += '<div style="font-size:13px; color:#64748b; margin-top:4px;">';
                            html += '📅 ' + range + (it.time_slot ? (' &bull; ⏰ ' + it.time_slot) : '') + ' &bull; 👥 Qty: ' + it.quantity;
                            html += '</div>';
                            html += '</div>';
                        });

                        html += '<h4 style="margin:16px 0 8px; font-size:15px; color:#0f172a;"><?php echo esc_js(__('Payment & Financial Summary', 'tourivo')); ?></h4>';
                        html += '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; font-size:14px;">';
                        html += '<div style="display:flex; justify-content:space-between; margin-bottom:4px;"><span><?php echo esc_js(__('Payment Method:', 'tourivo')); ?></span><strong>' + b.payment_method + '</strong></div>';
                        html += '<div style="display:flex; justify-content:space-between; margin-bottom:4px;"><span><?php echo esc_js(__('Payment Status:', 'tourivo')); ?></span><strong>' + b.payment_status + '</strong></div>';
                        html += '<div style="display:flex; justify-content:space-between; margin-bottom:4px;"><span><?php echo esc_js(__('Tax:', 'tourivo')); ?></span><span>' + b.tax_amount + '</span></div>';
                        html += '<div style="display:flex; justify-content:space-between; font-size:16px; font-weight:800; color:#0f172a; border-top:1px solid #e2e8f0; padding-top:6px; margin-top:6px;"><span><?php echo esc_js(__('Total:', 'tourivo')); ?></span><span>' + b.total_amount + '</span></div>';
                        html += '</div>';

                        if (b.customer_notes) {
                            html += '<h4 style="margin:16px 0 8px; font-size:15px; color:#0f172a;"><?php echo esc_js(__('Your Notes:', 'tourivo')); ?></h4>';
                            html += '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; font-size:13px; color:#475569;">' + b.customer_notes + '</div>';
                        }

                        body.innerHTML = html;
                    } else {
                        body.innerHTML = '<div style="color:#b91c1c; padding:20px; text-align:center;"><?php echo esc_js(__('Failed to load booking details.', 'tourivo')); ?></div>';
                    }
                })
                .catch(function(err) {
                    body.innerHTML = '<div style="color:#b91c1c; padding:20px; text-align:center;"><?php echo esc_js(__('A network error occurred.', 'tourivo')); ?></div>';
                });
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', window.TourivoMyBookings.init);
        } else {
            window.TourivoMyBookings.init();
        }
    </script>
<?php endif; ?>

</div>
