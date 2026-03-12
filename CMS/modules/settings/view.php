<!-- File: view.php -->
<div class="content-section" id="settings">
    <form id="settingsForm" class="settings-dashboard" enctype="multipart/form-data">
        <div class="a11y-dashboard" id="settingsDashboard" data-last-saved="">
            <header class="a11y-hero settings-hero">
                <div class="a11y-hero-content settings-hero-content">
                    <div>
                        <span class="hero-eyebrow settings-hero-eyebrow">Configuration Hub</span>
                        <h2 class="a11y-hero-title settings-hero-title">Site Settings</h2>
                        <p class="a11y-hero-subtitle settings-hero-subtitle">Fine-tune your brand voice, analytics, and sharing defaults from a single, polished workspace.</p>
                    </div>
                    <div class="a11y-hero-actions settings-hero-actions">
                        <button type="submit" class="a11y-btn a11y-btn--primary" id="saveSettingsButton" data-save-settings>
                            <i class="fas fa-save" aria-hidden="true"></i>
                            <span>Save Settings</span>
                        </button>
                        <span class="a11y-hero-meta settings-hero-meta">
                            <i class="fas fa-clock" aria-hidden="true"></i>
                            Last updated <span id="settingsLastSaved">—</span>
                        </span>
                    </div>
                </div>
                <div class="a11y-overview-grid">
                    <div class="a11y-overview-card">
                        <div class="a11y-overview-value" id="settingsOverviewName">—</div>
                        <div class="a11y-overview-label">Site Name</div>
                    </div>
                    <div class="a11y-overview-card">
                        <div class="a11y-overview-value" id="settingsOverviewSocials">0</div>
                        <div class="a11y-overview-label">Social Profiles</div>
                    </div>
                    <div class="a11y-overview-card">
                        <div class="a11y-overview-value" id="settingsOverviewTracking">0</div>
                        <div class="a11y-overview-label">Tracking IDs</div>
                    </div>
                    <div class="a11y-overview-card">
                        <div class="a11y-overview-value" id="settingsOverviewVisibility">—</div>
                        <div class="a11y-overview-label">Visibility</div>
                    </div>
                    <div class="a11y-overview-card">
                        <div class="a11y-overview-value" id="settingsOverviewPayments">Stripe off</div>
                        <div class="a11y-overview-label">Payments</div>
                    </div>
                </div>
            </header>

            <section class="settings-layout" aria-label="Settings sections">
                <nav class="settings-tabs" role="tablist" aria-label="Settings categories">
                    <button type="button" class="a11y-btn a11y-btn--secondary settings-tab" role="tab" aria-selected="true" aria-controls="settings-tab-branding" id="settings-tab-branding-tab" data-tab-target="settings-tab-branding">
                        <i class="fas fa-palette" aria-hidden="true"></i>
                        <span>Branding</span>
                    </button>
                    <button type="button" class="a11y-btn a11y-btn--secondary settings-tab" role="tab" aria-selected="false" aria-controls="settings-tab-seo" id="settings-tab-seo-tab" data-tab-target="settings-tab-seo" tabindex="-1">
                        <i class="fas fa-share-alt" aria-hidden="true"></i>
                        <span>SEO &amp; Social</span>
                    </button>
                    <button type="button" class="a11y-btn a11y-btn--secondary settings-tab" role="tab" aria-selected="false" aria-controls="settings-tab-analytics" id="settings-tab-analytics-tab" data-tab-target="settings-tab-analytics" tabindex="-1">
                        <i class="fas fa-chart-line" aria-hidden="true"></i>
                        <span>Analytics</span>
                    </button>
                    <button type="button" class="a11y-btn a11y-btn--secondary settings-tab" role="tab" aria-selected="false" aria-controls="settings-tab-payments" id="settings-tab-payments-tab" data-tab-target="settings-tab-payments" tabindex="-1">
                        <i class="fas fa-credit-card" aria-hidden="true"></i>
                        <span>Payments</span>
                    </button>
                    <button type="button" class="a11y-btn a11y-btn--secondary settings-tab" role="tab" aria-selected="false" aria-controls="settings-tab-apikeys" id="settings-tab-apikeys-tab" data-tab-target="settings-tab-apikeys" tabindex="-1">
                        <i class="fas fa-key" aria-hidden="true"></i>
                        <span>API Keys</span>
                    </button>
                </nav>

                <div class="settings-tab-panels">
                    <section id="settings-tab-branding" class="settings-tab-panel" role="tabpanel" aria-labelledby="settings-tab-branding-tab">
                        <article class="a11y-detail-card">
                            <h2>Branding &amp; Basics</h2>
                            <p class="settings-card-description">Control the essentials that shape how your site appears to visitors.</p>

                            <details class="settings-accordion" open>
                                <summary>Brand identity</summary>
                                <div class="settings-accordion__body">
                                    <div class="form-group">
                                        <label class="form-label" for="site_name">Site Name</label>
                                        <input type="text" class="form-input" name="site_name" id="site_name" autocomplete="organization" placeholder="Acme Inc.">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="tagline">Tagline</label>
                                        <input type="text" class="form-input" name="tagline" id="tagline" autocomplete="off" placeholder="Elevating ideas with every launch.">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="admin_email">Admin Email</label>
                                        <input type="email" class="form-input" name="admin_email" id="admin_email" autocomplete="email" placeholder="admin@yourdomain.com">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="timezone">Timezone</label>
                                        <select class="form-select" id="timezone" name="timezone">
                                            <option value="" disabled selected>Select a timezone</option>
                                            <option value="America/New_York">Eastern Time (ET)</option>
                                            <option value="America/Chicago">Central Time (CT)</option>
                                            <option value="America/Denver">Mountain Time (MT)</option>
                                            <option value="America/Los_Angeles">Pacific Time (PT)</option>
                                            <option value="UTC">UTC</option>
                                        </select>
                                    </div>
                                </div>
                            </details>

                            <details class="settings-accordion" open>
                                <summary>Brand assets</summary>
                                <div class="settings-accordion__body">
                                    <div class="form-group">
                                        <label class="form-label" for="logoFile">Site Logo</label>
                                        <div class="settings-file-input">
                                            <input type="file" class="settings-file-input__field" id="logoFile" name="logo" accept="image/*">
                                            <button type="button" class="a11y-btn a11y-btn--secondary settings-file-trigger" data-input-target="logoFile">
                                                <i class="fas fa-upload" aria-hidden="true"></i>
                                                <span>Select logo</span>
                                            </button>
                                            <span class="settings-file-name" id="logoFileName" data-default="No file selected" data-remove="Marked for removal">No file selected</span>
                                            <img id="logoPreview" class="settings-file-preview" src="" alt="Logo preview" hidden>
                                        </div>
                                        <div class="form-option">
                                            <label class="form-checkbox">
                                                <input type="checkbox" id="clearLogo" name="clear_logo" value="1">
                                                <span>Remove current logo</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="faviconFile">Site Favicon</label>
                                        <div class="settings-file-input">
                                            <input type="file" class="settings-file-input__field" id="faviconFile" name="favicon" accept="image/png,image/jpeg,image/gif,image/webp,image/x-icon,image/vnd.microsoft.icon,image/svg+xml,.ico">
                                            <button type="button" class="a11y-btn a11y-btn--secondary settings-file-trigger" data-input-target="faviconFile">
                                                <i class="fas fa-upload" aria-hidden="true"></i>
                                                <span>Select favicon</span>
                                            </button>
                                            <span class="settings-file-name" id="faviconFileName" data-default="No file selected" data-remove="Marked for removal">No file selected</span>
                                            <img id="faviconPreview" class="settings-file-preview" src="" alt="Favicon preview" hidden>
                                        </div>
                                        <div class="form-option">
                                            <label class="form-checkbox">
                                                <input type="checkbox" id="clearFavicon" name="clear_favicon" value="1">
                                                <span>Remove current favicon</span>
                                            </label>
                                        </div>
                                        <div class="form-help">Upload a square image (PNG, ICO, or SVG) at least 32×32 pixels.</div>
                                    </div>
                                </div>
                            </details>
                        </article>
                    </section>

                    <section id="settings-tab-seo" class="settings-tab-panel" role="tabpanel" aria-labelledby="settings-tab-seo-tab" hidden>
                        <article class="a11y-detail-card">
                            <h2>SEO &amp; Social</h2>
                            <p class="settings-card-description">Connect your brand channels to power sharing and automation.</p>

                            <details class="settings-accordion" open>
                                <summary>Search optimization</summary>
                                <div class="settings-accordion__body">
                                    <div class="form-group">
                                        <label class="form-label" for="googleSearchConsole">Google Search Console Verification Code</label>
                                        <input type="text" class="form-input" id="googleSearchConsole" name="googleSearchConsole" placeholder="google-site-verification=...">
                                        <div class="form-help">Meta tag verification code from Google Search Console.</div>
                                    </div>
                                    <div class="settings-toggle-group" role="group" aria-label="Search visibility preferences">
                                        <label class="settings-toggle">
                                            <input type="checkbox" id="generateSitemap" name="generateSitemap" checked>
                                            <div>
                                                <span class="settings-toggle__title">Auto-generate XML sitemap</span>
                                                <p class="settings-toggle__description">Keep search engines in sync with your latest pages.</p>
                                            </div>
                                        </label>
                                        <label class="settings-toggle">
                                            <input type="checkbox" id="allowIndexing" name="allowIndexing" checked>
                                            <div>
                                                <span class="settings-toggle__title">Allow search indexing</span>
                                                <p class="settings-toggle__description">Let search engines discover and list your site.</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </details>

                            <details class="settings-accordion">
                                <summary>Social profiles</summary>
                                <div class="settings-accordion__body">
                                    <div class="settings-social-grid">
                                        <div class="form-group">
                                            <label class="form-label" for="facebookLink">Facebook</label>
                                            <input type="url" class="form-input" id="facebookLink" name="facebook" placeholder="https://facebook.com/yourpage">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="twitterLink">Twitter</label>
                                            <input type="url" class="form-input" id="twitterLink" name="twitter" placeholder="https://twitter.com/youraccount">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="instagramLink">Instagram</label>
                                            <input type="url" class="form-input" id="instagramLink" name="instagram" placeholder="https://instagram.com/youraccount">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="linkedinLink">LinkedIn</label>
                                            <input type="url" class="form-input" id="linkedinLink" name="linkedin" placeholder="https://linkedin.com/company/yourcompany">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="youtubeLink">YouTube</label>
                                            <input type="url" class="form-input" id="youtubeLink" name="youtube" placeholder="https://youtube.com/c/yourchannel">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="tiktokLink">TikTok</label>
                                            <input type="url" class="form-input" id="tiktokLink" name="tiktok" placeholder="https://tiktok.com/@youraccount">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="pinterestLink">Pinterest</label>
                                            <input type="url" class="form-input" id="pinterestLink" name="pinterest" placeholder="https://pinterest.com/yourprofile">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="snapchatLink">Snapchat</label>
                                            <input type="url" class="form-input" id="snapchatLink" name="snapchat" placeholder="https://snapchat.com/add/yourusername">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="redditLink">Reddit</label>
                                            <input type="url" class="form-input" id="redditLink" name="reddit" placeholder="https://reddit.com/u/yourusername">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="threadsLink">Threads</label>
                                            <input type="url" class="form-input" id="threadsLink" name="threads" placeholder="https://threads.net/@yourusername">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="mastodonLink">Mastodon</label>
                                            <input type="url" class="form-input" id="mastodonLink" name="mastodon" placeholder="https://mastodon.social/@yourusername">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="githubLink">GitHub</label>
                                            <input type="url" class="form-input" id="githubLink" name="github" placeholder="https://github.com/yourusername">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="dribbbleLink">Dribbble</label>
                                            <input type="url" class="form-input" id="dribbbleLink" name="dribbble" placeholder="https://dribbble.com/yourprofile">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="twitchLink">Twitch</label>
                                            <input type="url" class="form-input" id="twitchLink" name="twitch" placeholder="https://twitch.tv/yourchannel">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="whatsappLink">WhatsApp</label>
                                            <input type="url" class="form-input" id="whatsappLink" name="whatsapp" placeholder="https://wa.me/yourphonenumber">
                                        </div>
                                    </div>
                                </div>
                            </details>

                            <details class="settings-accordion" open>
                                <summary>Share defaults</summary>
                                <div class="settings-accordion__body">
                                    <div class="form-group">
                                        <label class="form-label" for="ogTitle">Default Share Title</label>
                                        <input type="text" class="form-input" id="ogTitle" name="ogTitle" placeholder="My Awesome Website">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="ogDescription">Default Share Description</label>
                                        <textarea class="form-textarea" id="ogDescription" name="ogDescription" rows="4" placeholder="Give people a compelling reason to click."></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="ogImageFile">Default Share Image</label>
                                        <div class="settings-file-input">
                                            <input type="file" id="ogImageFile" name="ogImage" class="settings-file-input__field" accept="image/*">
                                            <button type="button" class="a11y-btn a11y-btn--secondary settings-file-trigger" data-input-target="ogImageFile">
                                                <i class="fas fa-upload" aria-hidden="true"></i>
                                                <span>Select image</span>
                                            </button>
                                            <span class="settings-file-name" id="ogImageFileName" data-default="No file selected" data-remove="Marked for removal">No file selected</span>
                                            <img id="ogImagePreview" class="settings-file-preview" src="" alt="Open graph image preview" hidden>
                                        </div>
                                        <div class="form-option">
                                            <label class="form-checkbox">
                                                <input type="checkbox" id="clearOgImage" name="clear_og_image" value="1">
                                                <span>Remove current image</span>
                                            </label>
                                        </div>
                                        <div class="form-help">Upload a 1200 × 630px image for social sharing cards.</div>
                                    </div>

                                    <div class="social-preview" id="socialSharePreview">
                                        <div class="social-preview-header">
                                            <h3 class="social-preview-heading">Live Share Preview</h3>
                                            <p class="social-preview-subheading">See how your default Open Graph content appears on social platforms.</p>
                                        </div>
                                        <div class="social-preview-card" role="presentation">
                                            <div class="social-preview-media">
                                                <img id="socialPreviewImage" alt="Social share image preview" hidden>
                                                <div class="social-preview-media__fallback" id="socialPreviewImageFallback">
                                                    <span class="social-preview-media__icon"><i class="fas fa-image" aria-hidden="true"></i></span>
                                                    <span class="social-preview-media__text">1200 × 630</span>
                                                </div>
                                            </div>
                                            <div class="social-preview-body">
                                                <span class="social-preview-domain" id="socialPreviewDomain"></span>
                                                <h4 class="social-preview-title" id="socialPreviewTitle">My Awesome Website</h4>
                                                <p class="social-preview-description" id="socialPreviewDescription">Give people a compelling reason to click.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        </article>
                    </section>

                    <section id="settings-tab-analytics" class="settings-tab-panel" role="tabpanel" aria-labelledby="settings-tab-analytics-tab" hidden>
                        <article class="a11y-detail-card">
                            <h2>Analytics &amp; Tracking</h2>
                            <p class="settings-card-description">Centralize your analytics IDs and tracking pixels for quick management.</p>

                            <details class="settings-accordion" open>
                                <summary>Analytics providers</summary>
                                <div class="settings-accordion__body">
                                    <div class="form-group">
                                        <label class="form-label" for="googleAnalytics">Google Analytics ID</label>
                                        <input type="text" class="form-input" id="googleAnalytics" name="googleAnalytics" placeholder="G-XXXXXXX-XX or UA-XXXXXXXX-X">
                                        <div class="form-help">Enter your Google Analytics measurement or property ID. Place a service account JSON at <code>CMS/data/google-analytics-service-account.json</code> (or use <code>GOOGLE_APPLICATION_CREDENTIALS</code>) so the dashboard can pull live data. For GA4 measurement IDs, add a property mapping in <code>CMS/data/google-analytics-streams.json</code> if needed.</div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="facebookPixel">Facebook Pixel ID</label>
                                        <input type="text" class="form-input" id="facebookPixel" name="facebookPixel" placeholder="1234567890123456">
                                        <div class="form-help">Your Facebook Pixel ID for conversion tracking.</div>
                                    </div>
                                </div>
                            </details>
                        </article>
                    </section>

                    <section id="settings-tab-payments" class="settings-tab-panel" role="tabpanel" aria-labelledby="settings-tab-payments-tab" hidden>
                        <article class="a11y-detail-card">
                            <h2>Payment Gateways</h2>
                            <p class="settings-card-description">Connect Stripe to accept payments securely across your experiences.</p>

                            <details class="settings-accordion" open>
                                <summary>Stripe</summary>
                                <div class="settings-accordion__body">
                                    <div class="settings-toggle-group" role="group" aria-label="Stripe settings">
                                        <label class="settings-toggle">
                                            <input type="checkbox" id="stripeEnabled" name="stripe_enabled">
                                            <div>
                                                <span class="settings-toggle__title">Enable Stripe payments</span>
                                                <p class="settings-toggle__description">Activate Stripe to process checkouts and on-site transactions.</p>
                                            </div>
                                        </label>
                                    </div>

                                    <div id="stripeConfigFields" class="stripe-config">
                                        <div class="form-group">
                                            <label class="form-label" for="stripeMode">Stripe Mode</label>
                                            <select class="form-select" id="stripeMode" name="stripe_mode">
                                                <option value="test">Test</option>
                                                <option value="live">Live</option>
                                            </select>
                                            <div class="form-help">Use Test while configuring your integration, then switch to Live when ready.</div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="stripePublishableKey">Publishable Key</label>
                                            <input type="text" class="form-input" id="stripePublishableKey" name="stripe_publishable_key" autocomplete="off" spellcheck="false">
                                            <div class="form-help">Required when Stripe is enabled. Starts with <code>pk_test_</code> or <code>pk_live_</code>.</div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="stripeSecretKey">Secret Key</label>
                                            <input type="text" class="form-input" id="stripeSecretKey" name="stripe_secret_key" autocomplete="off" spellcheck="false">
                                            <div class="form-help">Required when Stripe is enabled. Starts with <code>sk_test_</code> or <code>sk_live_</code>.</div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="stripeWebhookSecret">Webhook Signing Secret</label>
                                            <input type="text" class="form-input" id="stripeWebhookSecret" name="stripe_webhook_secret" autocomplete="off" spellcheck="false">
                                            <div class="form-help">Optional but recommended when using webhooks. Starts with <code>whsec_</code>.</div>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        </article>
                    </section>
                    <!-- ── API Keys panel ─────────────────────────────── -->
                    <section id="settings-tab-apikeys" class="settings-tab-panel" role="tabpanel" aria-labelledby="settings-tab-apikeys-tab" hidden>
                        <article class="a11y-detail-card api-keys-card">
                            <div class="api-keys-card-header">
                                <div>
                                    <h2>API Keys</h2>
                                    <p class="settings-card-description">
                                        Issue API keys so external apps can consume your content via the REST API
                                        at <code class="api-keys-base-url">/api/v1/</code>
                                        without needing a browser session.
                                        Each key can be scoped to <strong>read</strong>, <strong>write</strong>, or <strong>delete</strong>.
                                    </p>
                                </div>
                                <button type="button" class="a11y-btn a11y-btn--primary" id="createApiKeyBtn">
                                    <i class="fas fa-plus" aria-hidden="true"></i>
                                    <span>New API Key</span>
                                </button>
                            </div>

                            <div id="apiKeysLoading" class="form-help" style="display:none;" role="status" aria-live="polite">Loading…</div>
                            <div id="apiKeysError"   class="form-help api-keys-error" style="display:none;" role="alert"></div>

                            <table class="api-keys-table" id="apiKeysTable" style="display:none;" aria-label="API keys">
                                <thead>
                                    <tr>
                                        <th scope="col">Name</th>
                                        <th scope="col">Key hint</th>
                                        <th scope="col">Permissions</th>
                                        <th scope="col">Last used</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="apiKeysBody"></tbody>
                            </table>

                            <p id="apiKeysEmpty" class="form-help api-keys-empty" style="display:none;">
                                No API keys yet. Click <strong>New API Key</strong> to create one.
                            </p>

                            <details class="settings-accordion api-keys-docs" style="margin-top:1.5rem;">
                                <summary>Quick-start guide</summary>
                                <div class="settings-accordion__body api-keys-docs-body">
                                    <p>Pass your key in any GET or write request:</p>
                                    <pre class="api-keys-code">curl -H "Authorization: Bearer spk_your_key" \
  https://yourdomain.com/api/v1/posts</pre>
                                    <p><strong>Resources</strong> (replace <code>{id}</code> with the record ID):</p>
                                    <ul class="api-keys-resource-list">
                                        <li><code>GET  /api/v1/posts</code> — list published posts (public)</li>
                                        <li><code>GET  /api/v1/posts/{id}</code> — single post</li>
                                        <li><code>POST /api/v1/posts</code> — create post (write key)</li>
                                        <li><code>PUT  /api/v1/posts/{id}</code> — update post (write key)</li>
                                        <li><code>DELETE /api/v1/posts/{id}</code> — delete post (delete key)</li>
                                        <li><code>GET  /api/v1/pages[/{id}]</code> — pages (same pattern)</li>
                                        <li><code>GET  /api/v1/media[/{id}]</code> — media (read key required)</li>
                                        <li><code>GET  /api/v1/events[/{id}]</code> — events (public)</li>
                                        <li><code>GET  /api/v1/locations[/{id}]</code> — map locations (public)</li>
                                    </ul>
                                    <p>Pagination: <code>?page=1&amp;per_page=20</code> &nbsp;·&nbsp; Filter posts: <code>?status=published&amp;category=news&amp;search=term</code></p>
                                </div>
                            </details>
                        </article>
                    </section>

                </div>
            </section>

            <div class="settings-actions-footer">
                <div class="settings-actions-footer__content">
                    <span class="settings-actions-footer__helper">Review your changes, then save when you&apos;re ready.</span>
                    <button type="submit" class="a11y-btn a11y-btn--primary settings-actions-footer__button" data-save-settings>
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <span>Save Settings</span>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- ── Create API Key modal ─────────────────────────────────────────── -->
    <div class="modal" id="apiKeyModal" role="dialog" aria-modal="true" aria-labelledby="apiKeyModalTitle">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="apiKeyModalTitle">New API Key</h2>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="apiKeyName">Key name <span class="required-mark" aria-hidden="true">*</span></label>
                    <input type="text" class="form-input" id="apiKeyName" placeholder="e.g. Mobile app, Analytics dashboard" autocomplete="off">
                    <p class="form-help">A human-readable label so you know which app is using this key.</p>
                </div>
                <fieldset class="form-group api-keys-perms-fieldset">
                    <legend class="form-label">Permissions</legend>
                    <div class="form-check">
                        <input type="checkbox" id="apiKeyPermRead"   value="read"   checked>
                        <label for="apiKeyPermRead">  <strong>Read</strong> — access all content, including drafts and unpublished items</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" id="apiKeyPermWrite"  value="write">
                        <label for="apiKeyPermWrite"> <strong>Write</strong> — create and update posts, pages, and other content</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" id="apiKeyPermDelete" value="delete">
                        <label for="apiKeyPermDelete"><strong>Delete</strong> — permanently remove content records</label>
                    </div>
                </fieldset>
                <p id="apiKeyModalError" class="form-help api-keys-error" style="display:none;" role="alert"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="apiKeyModalCancel">Cancel</button>
                <button type="button" class="btn btn-primary"   id="apiKeyModalSave">Create Key</button>
            </div>
        </div>
    </div>

    <!-- ── Reveal new key modal ──────────────────────────────────────────── -->
    <div class="modal" id="apiKeyRevealModal" role="dialog" aria-modal="true" aria-labelledby="apiKeyRevealTitle">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="apiKeyRevealTitle">API Key Created</h2>
            </div>
            <div class="modal-body">
                <div class="api-key-reveal-warning" role="alert">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    <strong>Copy this key now.</strong> For security, the full key is shown only once and cannot be retrieved later.
                </div>
                <div class="api-key-reveal-box">
                    <code id="apiKeyRevealValue" class="api-key-reveal-value"></code>
                    <button type="button" class="btn btn-secondary btn-sm" id="apiKeyRevealCopy">
                        <i class="fas fa-copy btn-icon" aria-hidden="true"></i>
                        <span class="btn-label">Copy</span>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="apiKeyRevealClose">Done — I've saved the key</button>
            </div>
        </div>
    </div>
</div>
