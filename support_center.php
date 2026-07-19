<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

$pdo = db();
$supportFaqs = [];
$supportContactCards = [];
try {
    $supportFaqs = $pdo->query(
        "SELECT id, question, answer
         FROM support_faqs
         WHERE is_active = 1
         ORDER BY sort_order ASC, id ASC"
    )->fetchAll() ?: [];
} catch (Throwable $e) {
    $supportFaqs = [];
}

try {
    $supportContactCards = $pdo->query(
        "SELECT icon, title, description, value, action_url
         FROM support_contact_cards
         WHERE is_active = 1
         ORDER BY sort_order ASC, id ASC"
    )->fetchAll() ?: [];
} catch (Throwable $e) {
    try {
        $supportContactCards = $pdo->query(
            "SELECT icon, title, description, value
             FROM support_contact_cards
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC"
        )->fetchAll() ?: [];
    } catch (Throwable $fallbackError) {
        $supportContactCards = [];
    }
}

if (empty($supportContactCards)) {
    $supportContactCards = [
        [
            'icon' => 'call',
            'title' => 'Call Us',
            'description' => 'Immediate help for urgent issues.',
            'value' => '1-800-MAX-HOME',
            'action_url' => 'tel:1800MAXHOME',
        ],
        [
            'icon' => 'mail',
            'title' => 'Email Support',
            'description' => 'Expect a response within 4 hours.',
            'value' => 'concierge@maxhome.tech',
            'action_url' => 'mailto:concierge@maxhome.tech',
        ],
        [
            'icon' => 'forum',
            'title' => 'Twitter Help',
            'description' => 'Tweet us @MaxHomeSupport.',
            'value' => 'Average response: 15m',
            'action_url' => 'https://x.com/MaxHomeSupport',
        ],
        [
            'icon' => 'location_on',
            'title' => 'Tech Bar',
            'description' => 'Visit a physical showroom bar.',
            'value' => 'Find a location',
            'action_url' => 'https://maps.google.com',
        ],
    ];
}
?>
<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;family=Manrope:wght@400;500;600&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?php echo filemtime(__DIR__ . '/assets/css/navbar.css'); ?>">
    <link rel="stylesheet" href="assets/css/support_center.css">
</head>

<body>
    <?php $currentPage = 'support'; ?>
    <?php include 'navbar.php'; ?>
    <main class="main">
        <!-- Hero Search Section -->
        <section class="hero">
            <div class="hero-content">
                <h1 class="hero-title">How can we help?</h1>
                <p class="hero-subtitle">Enter order number to get live tracking in seconds.</p>
                <div class="search-wrapper">
                    <span class="material-symbols-outlined search-icon">search</span>
                    <input class="search-input" id="support-search-input" placeholder="Search for products or help topics..." type="text" />
                    <div class="search-results" id="support-search-results" hidden></div>
                </div>
                <div class="popular-tags">
                    <span class="tag-label">Popular:</span>
                    <a class="tag-link" data-query="Reset Password" href="support_center.php">Reset Password</a>
                    <a class="tag-link" data-query="Warranty Claim" href="support_center.php">Warranty Claim</a>
                    <a class="tag-link" data-query="Order Status" href="support_center.php">Order Status</a>
                </div>
            </div>
            <!-- Decorative Background Element -->
            <div class="hero-bg-blob-1"></div>
            <div class="hero-bg-blob-2"></div>
        </section>
        <!-- Category Bento Grid -->
        <section class="bento-section">
            <div class="bento-grid">
                <!-- Orders -->
                <div class="bento-card">
                    <div class="bento-icon-box">
                        <span class="material-symbols-outlined bento-icon">shopping_bag</span>
                    </div>
                    <h3 class="bento-title">Orders</h3>
                    <p class="bento-desc">Enter your order number and email or phone to see the latest shipment status instantly.</p>
                    <ul class="bento-list">
                        <li><button class="bento-link bento-link-btn" id="track-shipment-toggle" type="button">Track shipment <span class="material-symbols-outlined chevron-mini">chevron_right</span></button></li>
                    </ul>
                    <div class="shipment-tracker" id="shipment-tracker" hidden>
                        <form id="shipment-form">
                            <label class="shipment-label" for="track-order-number">Order number</label>
                            <input class="shipment-input" id="track-order-number" name="order_number" placeholder="e.g. MH-2026-00125" required type="text">
                            <label class="shipment-label" for="track-contact">Email or phone</label>
                            <input class="shipment-input" id="track-contact" name="contact" placeholder="you@example.com or +994..." required type="text">
                            <button class="shipment-submit" type="submit">Check status</button>
                        </form>
                        <div class="shipment-result" id="shipment-result" hidden></div>
                    </div>
                </div>
                <!-- Shipping -->
                <div class="bento-card">
                    <div class="bento-icon-box">
                        <span class="material-symbols-outlined bento-icon">local_shipping</span>
                    </div>
                    <h3 class="bento-title">Shipping</h3>
                    <p class="bento-desc">Check international delivery times, shipping costs, and our carrier partners.</p>
                    <ul class="bento-list">
                        <li><button class="bento-link bento-link-btn" id="delivery-regions-toggle" type="button">Delivery regions <span class="material-symbols-outlined chevron-mini">chevron_right</span></button></li>
                        <li><button class="bento-link bento-link-btn" id="expedited-shipping-toggle" type="button">Expedited shipping <span class="material-symbols-outlined chevron-mini">chevron_right</span></button></li>
                    </ul>
                    <div class="shipping-info" id="shipping-info-panel" hidden>
                        <div class="shipping-info-block" id="delivery-regions-panel" hidden>
                            <h4 class="shipping-info-title">Delivery regions</h4>
                            <ul class="shipping-info-list">
                                <li>Azerbaijan (Baku): 1-2 business days</li>
                                <li>South Caucasus region: 2-4 business days</li>
                                <li>Europe: 4-7 business days</li>
                                <li>North America: 5-9 business days</li>
                                <li>Middle East and Asia: 4-8 business days</li>
                            </ul>
                        </div>
                        <div class="shipping-info-block" id="expedited-shipping-panel" hidden>
                            <h4 class="shipping-info-title">Expedited shipping</h4>
                            <ul class="shipping-info-list">
                                <li>Express delivery is available for eligible products at checkout.</li>
                                <li>Cut-off time: 14:00 (local time) for same-day dispatch.</li>
                                <li>Carrier partners: DHL Express, FedEx Priority, and UPS Saver.</li>
                                <li>Estimated delivery: 1-3 business days after dispatch.</li>
                                <li>Additional costs are calculated based on destination and parcel size.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- Returns -->
                <div class="bento-card">
                    <div class="bento-icon-box">
                        <span class="material-symbols-outlined bento-icon">assignment_return</span>
                    </div>
                    <h3 class="bento-title">Returns</h3>
                    <p class="bento-desc">Returns are currently unavailable for direct self-service in this panel.</p>
                    <ul class="bento-list">
                        <li><button class="bento-link bento-link-btn" id="return-policy-toggle" type="button">Return policy <span class="material-symbols-outlined chevron-mini">chevron_right</span></button></li>
                    </ul>
                    <div class="shipping-info" id="return-policy-panel" hidden>
                        <h4 class="shipping-info-title">Return policy</h4>
                        <ul class="shipping-info-list">
                            <li>Returns are accepted within 14 days after delivery for eligible products.</li>
                            <li>Item must be unused, in original packaging, and include all accessories.</li>
                            <li>Custom or personalized products are not eligible for return.</li>
                            <li>Refund is processed to the original payment method within 5-10 business days after inspection.</li>
                            <li>If the product is defective or shipped incorrectly, return shipping cost is covered by MAXHOME.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
        <!-- Warranty Info Section -->
        <section class="warranty-section">
            <div class="warranty-grid">
                <div class="warranty-image-wrapper">
                    <div class="warranty-image-decor"></div>
                    <img alt="MAXHOME Tech Support" class="warranty-image" data-alt="Modern high-tech diagnostic lab with a robotic arm and specialized tools on a clean white surface with soft blue ambient lighting" src="https://lh3.googleusercontent.com/aida-public/AB6AXuD180Yl_sssIBIIirlmZ2hnSXfN42IIJYgfWXKjzYL21zb_bqS59eSiEDYHaGKGiLODsJzw_eTkribj8UDV3a23sXjXSf6UWmxueiz8WMuKA7A-T2FPf9I_NPzzenMBQlWAYjsVT92rC5-Lo6MiYoM6zeQa-vefwhADhANp5R-zbL1zER5N5j64Px87ACRzhLUP_U-PFynedHhBK-r2WY8wVQr7O_X8NLNh86COI_u_GabZ1pExwD4TxGXB_1mlLj9wYOfyr3vUI4BO" />
                </div>
                <div class="warranty-content">
                    <h2 class="warranty-title">MAXCARE Warranty</h2>
                    <p class="warranty-desc">Your electronics are protected by our industry-leading 24-month comprehensive warranty. From hardware failure to accidental damage (with MaxCare+), we ensure your tech stays in peak condition.</p>
                    <div class="feature-list">
                        <div class="feature-item">
                            <span class="material-symbols-outlined feature-icon">verified</span>
                            <div>
                                <h4 class="feature-item-title">Certified Repairs</h4>
                                <p class="feature-item-desc">Only genuine MAXHOME components used by factory-trained technicians.</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <span class="material-symbols-outlined feature-icon">autorenew</span>
                            <div>
                                <h4 class="feature-item-title">Express Replacement</h4>
                                <p class="feature-item-desc">We ship your replacement device before you even return the old one.</p>
                            </div>
                        </div>
                    </div>
                    <button class="btn-primary">
                        Register Your Device
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </button>
                </div>
            </div>
        </section>
        <!-- Contact Cards -->
        <section class="contact-section" id="contact-support">
            <div class="contact-header">
                <h2 class="contact-title">Still need assistance?</h2>
                <p class="contact-subtitle">Our concierge team is available 24/7 across multiple channels.</p>
            </div>
            <div class="contact-grid">
                <?php foreach ($supportContactCards as $card): ?>
                    <div class="contact-card">
                        <span class="material-symbols-outlined contact-card-icon"><?php echo e((string) $card['icon']); ?></span>
                        <h3 class="contact-card-title"><?php echo e((string) $card['title']); ?></h3>
                        <p class="contact-card-desc"><?php echo e((string) $card['description']); ?></p>
                        <?php $cardUrl = trim((string) ($card['action_url'] ?? '')); ?>
                        <?php if ($cardUrl !== ''): ?>
                            <a class="contact-card-value" href="<?php echo e($cardUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo e((string) $card['value']); ?></a>
                        <?php else: ?>
                            <span class="contact-card-value"><?php echo e((string) $card['value']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="faq-section">
            <div class="faq-wrapper">
                <div class="faq-header">
                    <h2 class="faq-title">Verilen suallar</h2>
                    <p class="faq-subtitle">Bu bolmedeki suallar admin panelinden dinamik olaraq idare olunur.</p>
                </div>
                <?php if (empty($supportFaqs)): ?>
                    <div class="faq-empty">Hazirda gosterilecek sual yoxdur.</div>
                <?php else: ?>
                    <div class="faq-list">
                        <?php foreach ($supportFaqs as $faq): ?>
                            <details class="faq-item" id="faq-<?php echo (int) $faq['id']; ?>">
                                <summary class="faq-question"><?php echo e((string) $faq['question']); ?></summary>
                                <div class="faq-answer"><?php echo nl2br(e((string) $faq['answer'])); ?></div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-grid">
            <div>
                <div class="footer-brand-name">MAXHOME</div>
                <p class="footer-brand-desc">Elevating everyday living through precise engineering and a concierge-first service philosophy.</p>
            </div>
            <div class="footer-links">
                <span class="footer-col-title">Resources</span>
                <a class="footer-link" href="support_center.php">Newsletter</a>
                <a class="footer-link" href="support_center.php">Trust Center</a>
                <a class="footer-link" href="support_center.php">Privacy Policy</a>
            </div>
            <div class="footer-links">
                <span class="footer-col-title">Shop</span>
                <a class="footer-link" href="support_center.php">Shipping</a>
                <a class="footer-link" href="support_center.php">Returns</a>
                <a class="footer-link" href="support_center.php">Track Order</a>
            </div>
            <div>
                <span class="footer-col-title">Stay Updated</span>
                <div class="newsletter-form">
                    <input class="newsletter-input" placeholder="Email address" type="email" />
                    <button class="newsletter-btn">Join</button>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p class="copyright">© 2026 | Developed by INTBAKU LLC</p>
        </div>
    </footer>
    <!-- Live Chat Floating CTA -->
    <div class="floating-cta">
        <button class="chat-btn">
            <span class="chat-icon-bg">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">chat</span>
            </span>
            <span class="chat-btn-text">Live Concierge</span>
            <span class="status-badge">
                <span class="status-ping"></span>
                <span class="status-dot"></span>
            </span>
        </button>
    </div>
    <script>
        (function () {
            const input = document.getElementById('support-search-input');
            const resultsBox = document.getElementById('support-search-results');
            const tags = Array.from(document.querySelectorAll('.tag-link[data-query]'));
            const trackerToggle = document.getElementById('track-shipment-toggle');
            const trackerPanel = document.getElementById('shipment-tracker');
            const shipmentForm = document.getElementById('shipment-form');
            const shipmentResult = document.getElementById('shipment-result');
            const deliveryRegionsToggle = document.getElementById('delivery-regions-toggle');
            const expeditedShippingToggle = document.getElementById('expedited-shipping-toggle');
            const shippingInfoPanel = document.getElementById('shipping-info-panel');
            const deliveryRegionsPanel = document.getElementById('delivery-regions-panel');
            const expeditedShippingPanel = document.getElementById('expedited-shipping-panel');
            const returnPolicyToggle = document.getElementById('return-policy-toggle');
            const returnPolicyPanel = document.getElementById('return-policy-panel');
            if (!input || !resultsBox) {
                return;
            }

            let timer = null;
            let requestVersion = 0;

            const escapeHtml = (value) => value
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

            const hideResults = () => {
                resultsBox.hidden = true;
                resultsBox.innerHTML = '';
            };

            const renderState = (text) => {
                resultsBox.hidden = false;
                resultsBox.innerHTML = '<div class="search-result-state">' + escapeHtml(text) + '</div>';
            };

            const runSearch = (rawQuery) => {
                const query = (rawQuery || '').trim();
                requestVersion += 1;
                const currentVersion = requestVersion;

                if (query.length < 2) {
                    hideResults();
                    return;
                }

                renderState('Axtarilir...');
                fetch('ajax/support_search.php?q=' + encodeURIComponent(query), {
                    headers: { 'Accept': 'application/json' }
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Request failed');
                        }
                        return response.json();
                    })
                    .then((payload) => {
                        if (currentVersion !== requestVersion) {
                            return;
                        }
                        if (!payload || payload.ok !== true || !Array.isArray(payload.faqs)) {
                            throw new Error('Bad response');
                        }
                        if (payload.faqs.length === 0) {
                            renderState('Netice tapilmadi.');
                            return;
                        }

                        const items = payload.faqs.map((item) => {
                            const q = escapeHtml(String(item.question || ''));
                            const excerpt = escapeHtml(String(item.excerpt || ''));
                            const id = Number(item.id || 0);
                            return '<button class="search-result-item" type="button" data-faq-id="' + id + '">' +
                                '<span class="search-result-q">' + q + '</span>' +
                                '<span class="search-result-a">' + excerpt + '</span>' +
                                '</button>';
                        }).join('');
                        resultsBox.hidden = false;
                        resultsBox.innerHTML = items;
                    })
                    .catch(() => {
                        if (currentVersion !== requestVersion) {
                            return;
                        }
                        renderState('Axtaris zamani xeta bas verdi.');
                    });
            };

            input.addEventListener('input', (event) => {
                if (timer) {
                    clearTimeout(timer);
                }
                const value = event.target && 'value' in event.target ? event.target.value : '';
                timer = setTimeout(() => runSearch(value), 300);
            });

            resultsBox.addEventListener('click', (event) => {
                const target = event.target instanceof Element ? event.target.closest('.search-result-item') : null;
                if (!target) {
                    return;
                }
                const faqId = target.getAttribute('data-faq-id');
                if (!faqId) {
                    return;
                }
                const faq = document.getElementById('faq-' + faqId);
                if (faq) {
                    faq.open = true;
                    faq.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                hideResults();
            });

            document.addEventListener('click', (event) => {
                const el = event.target instanceof Element ? event.target : null;
                if (!el) {
                    return;
                }
                if (!resultsBox.contains(el) && el !== input) {
                    hideResults();
                }
            });

            tags.forEach((tag) => {
                tag.addEventListener('click', (event) => {
                    event.preventDefault();
                    const query = tag.getAttribute('data-query') || tag.textContent || '';
                    input.value = query.trim();
                    runSearch(input.value);
                    input.focus();
                });
            });

            const formatStatus = (value) => String(value || '')
                .replace(/_/g, ' ')
                .replace(/\b\w/g, (char) => char.toUpperCase());

            const formatDateTime = (value) => {
                const raw = String(value || '').trim();
                if (!raw) {
                    return '-';
                }
                const date = new Date(raw.replace(' ', 'T'));
                if (Number.isNaN(date.getTime())) {
                    return escapeHtml(raw);
                }
                return escapeHtml(date.toLocaleString());
            };

            const renderShipmentResult = (payload) => {
                if (!shipmentResult) {
                    return;
                }
                if (!payload || payload.ok !== true) {
                    shipmentResult.hidden = false;
                    shipmentResult.innerHTML = '<p class="shipment-error">Unable to check shipment right now.</p>';
                    return;
                }
                if (payload.found !== true) {
                    shipmentResult.hidden = false;
                    shipmentResult.innerHTML = '<p class="shipment-error">No shipment found for this order and contact.</p>' +
                        '<a class="shipment-live-support" href="support_center.php#contact-support">Canlı dəstəyə keç</a>';
                    return;
                }
                shipmentResult.hidden = false;
                shipmentResult.innerHTML =
                    '<div class="shipment-success">' +
                    '<p><strong>Order:</strong> ' + escapeHtml(String(payload.order_number || '')) + '</p>' +
                    '<p><strong>Order status:</strong> ' + escapeHtml(formatStatus(payload.order_status || '')) + '</p>' +
                    '<p><strong>Carrier:</strong> ' + escapeHtml(String(payload.carrier || '-')) + '</p>' +
                    '<p><strong>Shipment status:</strong> ' + escapeHtml(formatStatus(payload.shipment_status || '')) + '</p>' +
                    '<p><strong>Tracking number:</strong> ' + escapeHtml(String(payload.tracking_number || '-')) + '</p>' +
                    '<p><strong>Shipped at:</strong> ' + formatDateTime(payload.shipped_at) + '</p>' +
                    '<p><strong>Delivered at:</strong> ' + formatDateTime(payload.delivered_at) + '</p>' +
                    '</div>';
            };

            if (trackerToggle && trackerPanel) {
                trackerToggle.addEventListener('click', () => {
                    trackerPanel.hidden = !trackerPanel.hidden;
                    if (!trackerPanel.hidden) {
                        const orderInput = document.getElementById('track-order-number');
                        if (orderInput) {
                            orderInput.focus();
                        }
                    }
                });
            }

            const toggleShippingInfo = (target) => {
                if (!shippingInfoPanel || !deliveryRegionsPanel || !expeditedShippingPanel) {
                    return;
                }
                const shouldOpen = target.hidden;
                shippingInfoPanel.hidden = !shouldOpen;
                deliveryRegionsPanel.hidden = true;
                expeditedShippingPanel.hidden = true;
                if (shouldOpen) {
                    target.hidden = false;
                } else {
                    shippingInfoPanel.hidden = true;
                }
            };

            if (deliveryRegionsToggle && deliveryRegionsPanel) {
                deliveryRegionsToggle.addEventListener('click', () => {
                    toggleShippingInfo(deliveryRegionsPanel);
                });
            }

            if (expeditedShippingToggle && expeditedShippingPanel) {
                expeditedShippingToggle.addEventListener('click', () => {
                    toggleShippingInfo(expeditedShippingPanel);
                });
            }

            if (returnPolicyToggle && returnPolicyPanel) {
                returnPolicyToggle.addEventListener('click', () => {
                    returnPolicyPanel.hidden = !returnPolicyPanel.hidden;
                });
            }

            if (shipmentForm && shipmentResult) {
                shipmentForm.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const orderNumber = (shipmentForm.order_number && shipmentForm.order_number.value ? shipmentForm.order_number.value : '').trim();
                    const contact = (shipmentForm.contact && shipmentForm.contact.value ? shipmentForm.contact.value : '').trim();
                    if (!orderNumber || !contact) {
                        shipmentResult.hidden = false;
                        shipmentResult.innerHTML = '<p class="shipment-error">Order number and email/phone are required.</p>';
                        return;
                    }
                    shipmentResult.hidden = false;
                    shipmentResult.innerHTML = '<p class="shipment-loading">Checking live status...</p>';

                    fetch('ajax/track_shipment.php', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            order_number: orderNumber,
                            contact: contact
                        })
                    })
                        .then((response) => {
                            if (!response.ok) {
                                throw new Error('Request failed');
                            }
                            return response.json();
                        })
                        .then((payload) => {
                            renderShipmentResult(payload);
                        })
                        .catch(() => {
                            shipmentResult.hidden = false;
                            shipmentResult.innerHTML = '<p class="shipment-error">Unable to check shipment right now.</p>';
                        });
                });
            }
        })();
    </script>
</body>

</html>