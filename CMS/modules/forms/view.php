<?php
// File: modules/forms/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_login();

$formsFile = __DIR__ . '/../../data/forms.json';
$forms = read_json_file($formsFile);
if (!is_array($forms)) {
    $forms = [];
}

$forms = array_values(array_filter($forms, static function ($item) {
    return is_array($item);
}));

$submissionsFile = __DIR__ . '/../../data/form_submissions.json';
$submissions = read_json_file($submissionsFile);
if (!is_array($submissions)) {
    $submissions = [];
}

$submissions = array_values(array_filter($submissions, static function ($item) {
    return is_array($item);
}));

$extractTimestamp = static function (array $entry): int {
    $candidates = ['submitted_at', 'created_at', 'timestamp'];
    foreach ($candidates as $key) {
        if (empty($entry[$key])) {
            continue;
        }
        $value = $entry[$key];
        if (is_numeric($value)) {
            $value = (float) $value;
            if ($value > 0) {
                return $value < 1_000_000_000_000 ? (int) round($value) : (int) round($value / 1000);
            }
            continue;
        }
        $time = strtotime((string) $value);
        if ($time !== false) {
            return $time;
        }
    }
    return 0;
};

$totalForms = count($forms);
$totalSubmissions = count($submissions);
$recentSubmissions = 0;
$activeForms = [];
$latestSubmission = 0;
$thirtyDaysAgo = time() - (30 * 24 * 60 * 60);

$siteSettings = get_site_settings();
$defaultFromName = isset($siteSettings['site_name']) && $siteSettings['site_name'] !== ''
    ? (string) $siteSettings['site_name']
    : 'Website';
$defaultFromEmail = isset($siteSettings['admin_email']) ? (string) $siteSettings['admin_email'] : '';
$defaultSubject = 'Thanks for contacting ' . $defaultFromName;
$defaultTitle = 'Thank you for reaching out';

foreach ($submissions as $submission) {
    if (isset($submission['form_id'])) {
        $activeForms[(int) $submission['form_id']] = true;
    }
    $timestamp = $extractTimestamp($submission);
    if ($timestamp > 0) {
        if ($timestamp > $latestSubmission) {
            $latestSubmission = $timestamp;
        }
        if ($timestamp >= $thirtyDaysAgo) {
            $recentSubmissions++;
        }
    }
}

$activeFormsCount = count($activeForms);
$lastSubmissionLabel = $latestSubmission > 0
    ? date('M j, Y g:i A', $latestSubmission)
    : 'No submissions yet';
?>
<div class="content-section" id="forms">
    <div class="forms-dashboard a11y-dashboard"
         data-total-forms="<?php echo (int) $totalForms; ?>"
         data-total-submissions="<?php echo (int) $totalSubmissions; ?>"
         data-recent-submissions="<?php echo (int) $recentSubmissions; ?>"
         data-active-forms="<?php echo (int) $activeFormsCount; ?>"
         data-last-submission="<?php echo htmlspecialchars($lastSubmissionLabel, ENT_QUOTES); ?>">
        <header class="a11y-hero forms-hero">
            <div class="a11y-hero-content">
                <div>
                    <span class="hero-eyebrow forms-hero-eyebrow">Submission Health</span>
                    <h2 class="a11y-hero-title">Form Builder &amp; Intake</h2>
                    <p class="a11y-hero-subtitle">Design conversion-ready forms, monitor submissions, and manage intake without leaving the dashboard.</p>
                </div>
                <div class="a11y-hero-actions">
                    <button type="button" class="a11y-btn a11y-btn--primary" id="newFormBtn">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <span>New form</span>
                    </button>
                    <span class="a11y-hero-meta forms-last-submission">
                        <i class="fas fa-inbox" aria-hidden="true"></i>
                        Last submission <span id="formsLastSubmission"><?php echo htmlspecialchars($lastSubmissionLabel); ?></span>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid forms-overview">
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="formsStatForms"><?php echo (int) $totalForms; ?></div>
                    <div class="a11y-overview-label">Published forms</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="formsStatActive"><?php echo (int) $activeFormsCount; ?></div>
                    <div class="a11y-overview-label">Collecting responses</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="formsStatSubmissions"><?php echo (int) $totalSubmissions; ?></div>
                    <div class="a11y-overview-label">Total submissions</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="formsStatRecent"><?php echo (int) $recentSubmissions; ?></div>
                    <div class="a11y-overview-label">Last 30 days</div>
                </div>
            </div>
        </header>

        <div class="forms-main-grid forms-main-grid--single">
            <div class="forms-main-grid__column forms-main-grid__column--library">
                <section class="a11y-detail-card forms-table-card">
                    <header class="forms-card-header">
                        <div>
                            <h3>Forms library</h3>
                            <p>Click any form to review submissions, edit the layout, or remove outdated capture points.</p>
                        </div>
                    </header>
                    <div class="forms-library" aria-live="polite">
                        <div class="a11y-pages-grid forms-library-grid" id="formsLibrary" role="list" aria-busy="false"></div>
                        <div class="empty-state forms-library-empty" id="formsLibraryEmptyState" hidden aria-labelledby="formsLibraryEmptyTitle" aria-describedby="formsLibraryEmptyDescription">
                            <div class="empty-state__icon" aria-hidden="true">
                                <i class="fa-solid fa-clipboard-list"></i>
                            </div>
                            <div class="empty-state__content">
                                <h3 class="empty-state__title" id="formsLibraryEmptyTitle">Build your first form</h3>
                                <p class="empty-state__description" id="formsLibraryEmptyDescription">Create a form to start collecting submissions.</p>
                            </div>
                            <button type="button" class="a11y-btn a11y-btn--primary empty-state__cta" id="formsEmptyCta">
                                <i class="fas fa-plus" aria-hidden="true"></i>
                                <span>New form</span>
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="a11y-page-detail forms-drawer" id="formBuilderDrawer" hidden role="dialog" aria-modal="true" aria-labelledby="formBuilderTitle" aria-describedby="formBuilderDescription">
            <div class="a11y-detail-content">
                <button type="button" class="a11y-detail-close" id="closeFormBuilder" aria-label="Close form builder">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
                <header class="a11y-detail-modal-header forms-drawer-header">
                    <div class="forms-drawer-header-content">
                        <span class="forms-drawer-subtitle">Form builder</span>
                        <h2 id="formBuilderTitle">Add form</h2>
                        <p class="forms-drawer-description" id="formBuilderDescription">Drag inputs from the palette to build your ideal flow, then fine-tune settings on the right.</p>
                    </div>
                    <div class="editor-save-state" data-save-state data-state="saved" role="status" aria-live="polite" aria-atomic="true" tabindex="0">
                        <span class="editor-save-state__dot" aria-hidden="true"></span>
                        <span class="editor-save-state__text" data-save-state-text>Saved</span>
                    </div>
                </header>
                <form id="formBuilderForm" class="forms-builder-form"
                      data-default-from-name="<?php echo htmlspecialchars($defaultFromName, ENT_QUOTES); ?>"
                      data-default-from-email="<?php echo htmlspecialchars($defaultFromEmail, ENT_QUOTES); ?>"
                      data-default-subject="<?php echo htmlspecialchars($defaultSubject, ENT_QUOTES); ?>"
                      data-default-title="<?php echo htmlspecialchars($defaultTitle, ENT_QUOTES); ?>">
                    <input type="hidden" name="id" id="formId">
                    <div class="form-group">
                        <label class="form-label" for="formName">Form name</label>
                        <input type="text" class="form-input" id="formName" name="name" required aria-describedby="formNameHint">
                        <p class="form-hint" id="formNameHint">Use a descriptive name so teammates can quickly identify the form.</p>
                    </div>
                    <div class="form-alert" id="formBuilderAlert" role="alert" aria-live="assertive" style="display:none;"></div>
                    <div class="forms-builder-quickstart" aria-label="Form builder quick start">
                        <div class="forms-builder-quickstart__content">
                            <h3>Quick start templates</h3>
                            <p>Start with a ready-made layout and tweak it to match your intake needs.</p>
                        </div>
                        <div class="forms-builder-quickstart__actions" role="group" aria-label="Form templates">
                            <button type="button" class="a11y-btn a11y-btn--secondary forms-template-btn" data-template="contact">
                                <i class="fa-solid fa-message" aria-hidden="true"></i>
                                <span>Contact form</span>
                            </button>
                            <button type="button" class="a11y-btn a11y-btn--secondary forms-template-btn" data-template="newsletter">
                                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                <span>Newsletter signup</span>
                            </button>
                            <button type="button" class="a11y-btn a11y-btn--secondary forms-template-btn" data-template="rsvp">
                                <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                                <span>Event RSVP</span>
                            </button>
                        </div>
                    </div>
                    <p class="builder-tip">Drag inputs from the palette or press Enter on a field type to add it instantly. Template buttons can create a full form in one click.</p>
                    <div class="builder-container">
                        <div id="fieldPalette" aria-label="Form fields palette">
                            <div class="palette-heading">Field types</div>
                            <div class="palette-item" data-type="text" role="button" tabindex="0">Text input</div>
                            <div class="palette-item" data-type="email" role="button" tabindex="0">Email</div>
                            <div class="palette-item" data-type="password" role="button" tabindex="0">Password</div>
                            <div class="palette-item" data-type="number" role="button" tabindex="0">Number</div>
                            <div class="palette-item" data-type="date" role="button" tabindex="0">Date</div>
                            <div class="palette-item" data-type="textarea" role="button" tabindex="0">Textarea</div>
                            <div class="palette-item" data-type="select" role="button" tabindex="0">Select</div>
                            <div class="palette-item" data-type="checkbox" role="button" tabindex="0">Checkbox</div>
                            <div class="palette-item" data-type="radio" role="button" tabindex="0">Radio</div>
                            <div class="palette-item" data-type="file" role="button" tabindex="0">File upload</div>
                            <div class="palette-item" data-type="recaptcha" role="button" tabindex="0">reCAPTCHA</div>
                            <div class="palette-item" data-type="submit" role="button" tabindex="0">Submit button</div>
                        </div>
                        <div class="builder-columns">
                            <ul id="formPreview" class="field-list" aria-label="Form preview" data-placeholder="Drop fields here"></ul>
                            <div id="fieldSettings" class="field-settings">
                                <div class="field-settings-empty">
                                    <h4>Field settings</h4>
                                    <p>Select a field in the preview to customize labels, names, and validation.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <section class="forms-confirmation-settings" aria-labelledby="confirmationEmailHeading">
                        <div class="forms-confirmation-header">
                            <span class="forms-confirmation-eyebrow">Follow-up</span>
                            <h3 class="forms-confirmation-title" id="confirmationEmailHeading">Confirmation email</h3>
                            <p class="forms-confirmation-description">Send a branded confirmation email after someone submits this form. The message pulls in your site logo, tagline, and social links automatically.</p>
                        </div>
                        <div class="forms-confirmation-body">
                            <div class="form-group forms-confirmation-toggle">
                                <div class="settings-toggle-group" role="group" aria-label="Confirmation email preference">
                                    <label class="settings-toggle">
                                        <input type="checkbox" id="confirmationEmailEnabled">
                                        <div>
                                            <span class="settings-toggle__title">Enable confirmation email</span>
                                            <p class="settings-toggle__description">Turn this on to automatically email people after they submit the form.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div id="confirmationEmailDetails" class="forms-confirmation-details" hidden>
                                <div class="forms-confirmation-grid">
                                    <div class="form-group">
                                        <label class="form-label" for="confirmationEmailField">Recipient email field</label>
                                        <select class="form-input" id="confirmationEmailField">
                                            <option value="">Add an email field to enable confirmation emails</option>
                                        </select>
                                        <p class="form-hint" id="confirmationEmailFieldHint">Choose which form field holds the visitor's email address.</p>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="confirmationEmailFromName">From name</label>
                                        <input type="text" class="form-input" id="confirmationEmailFromName" placeholder="<?php echo htmlspecialchars($defaultFromName, ENT_QUOTES); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="confirmationEmailFromEmail">From email</label>
                                        <input type="email" class="form-input" id="confirmationEmailFromEmail" placeholder="<?php echo htmlspecialchars($defaultFromEmail, ENT_QUOTES); ?>">
                                        <p class="form-hint">This address appears as the sender. Use a mailbox that can receive replies.</p>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="confirmationEmailSubject">Subject line</label>
                                        <input type="text" class="form-input" id="confirmationEmailSubject" placeholder="<?php echo htmlspecialchars($defaultSubject, ENT_QUOTES); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="confirmationEmailTitle">Email title</label>
                                        <input type="text" class="form-input" id="confirmationEmailTitle" placeholder="<?php echo htmlspecialchars($defaultTitle, ENT_QUOTES); ?>">
                                    </div>
                                    <div class="form-group forms-confirmation-description-field">
                                        <label class="form-label" for="confirmationEmailDescription">Intro message</label>
                                        <textarea class="form-input" id="confirmationEmailDescription" rows="4" placeholder="Thank you for reaching out—here's what happens next."></textarea>
                                        <p class="form-hint">Use this space to confirm next steps or share helpful resources. Line breaks are preserved.</p>
                                    </div>
                                </div>
                                <div class="forms-confirmation-actions">
                                    <button type="button" class="a11y-btn a11y-btn--secondary" id="previewConfirmationEmail">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        <span>Preview confirmation email</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                    <div class="form-actions forms-builder-actions">
                        <button type="submit" class="a11y-btn a11y-btn--primary">Save form</button>
                        <button type="button" class="a11y-btn a11y-btn--ghost" id="cancelFormEdit">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal forms-confirmation-preview-modal" id="confirmationEmailPreviewModal" role="dialog" aria-modal="true" aria-labelledby="confirmationPreviewTitle" aria-describedby="confirmationPreviewDescription" aria-hidden="true">
        <div class="modal-content">
            <button type="button" class="modal-close" id="closeConfirmationPreview" aria-label="Close confirmation email preview">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <header class="forms-confirmation-preview-modal__header">
                <span class="forms-confirmation-preview-modal__eyebrow">Confirmation email</span>
                <h2 class="forms-confirmation-preview-modal__title" id="confirmationPreviewTitle">Preview</h2>
                <p class="forms-confirmation-preview-modal__meta" id="confirmationPreviewDescription">This is how your confirmation message will appear in inboxes.</p>
                <dl class="forms-confirmation-preview-modal__details">
                    <div class="forms-confirmation-preview-modal__detail">
                        <dt>Subject</dt>
                        <dd id="confirmationPreviewSubject">—</dd>
                    </div>
                    <div class="forms-confirmation-preview-modal__detail">
                        <dt>From</dt>
                        <dd id="confirmationPreviewSender">—</dd>
                    </div>
                </dl>
            </header>
            <div class="forms-confirmation-preview-modal__body">
                <iframe id="confirmationEmailPreviewFrame" title="Confirmation email preview" srcdoc="<!DOCTYPE html><html><head><meta charset=&quot;UTF-8&quot;></head><body style='margin:0;background-color:#f8fafc;'></body></html>"></iframe>
            </div>
        </div>
    </div>
    <div class="modal forms-submission-modal" id="submissionDetailModal" role="dialog" aria-modal="true" aria-labelledby="submissionModalTitle" aria-describedby="submissionModalDescription" aria-hidden="true">
        <div class="modal-content">
            <button type="button" class="modal-close" id="submissionModalClose" aria-label="Close submission details">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <header class="modal-header forms-submission-modal__header">
                <span class="forms-submission-modal__eyebrow" id="submissionModalEyebrow">Submission details</span>
                <h2 class="forms-submission-modal__title" id="submissionModalTitle">Submission details</h2>
                <p class="forms-submission-modal__meta" id="submissionModalDescription"></p>
            </header>
            <div class="modal-body forms-submission-modal__body" id="submissionModalBody">
                <div class="forms-submission-modal__empty">Select a form submission to view the collected data.</div>
            </div>
        </div>
    </div>
    <div class="modal forms-submissions-modal" id="formSubmissionsModal" role="dialog" aria-modal="true" aria-labelledby="formSubmissionsTitle" aria-describedby="formSubmissionsDescription" aria-hidden="true">
        <div class="modal-content">
            <button type="button" class="modal-close" id="closeSubmissionsModal" aria-label="Close submission activity">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <header class="modal-header forms-submissions-modal__header">
                <span class="forms-submissions-modal__eyebrow">Submission activity</span>
                <h2 id="formSubmissionsTitle">Submission activity</h2>
                <p id="formSubmissionsDescription" class="forms-submissions-modal__description">Review and export submissions for your selected form.</p>
            </header>
            <section class="a11y-detail-card forms-submissions-card" id="formSubmissionsCard">
                <header class="forms-card-header forms-submissions-header">
                    <div>
                        <h3 class="forms-submissions-card__title">Submissions</h3>
                        <p id="selectedFormName" class="form-submissions-label">Select a form to view submissions</p>
                    </div>
                    <div class="forms-submissions-meta">
                        <span class="forms-submissions-count" id="formSubmissionsCount">—</span>
                        <form class="forms-submissions-export" id="exportSubmissionsForm" method="get" action="modules/forms/export_submissions.php">
                            <input type="hidden" name="form_id" id="exportFormId" value="">
                            <button type="submit" class="a11y-btn a11y-btn--ghost" id="exportSubmissionsBtn" disabled aria-disabled="true">
                                <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i>
                                <span>Export CSV</span>
                            </button>
                        </form>
                    </div>
                </header>
                <div class="forms-submissions-container">
                    <div class="forms-submissions-list" id="formSubmissionsList" role="list" aria-live="polite">
                        <div class="forms-submissions-empty">Select a form to view submissions.</div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
