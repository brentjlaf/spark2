<?php
// File: blog-detail.php
// Template: blog-detail
// Variables provided by index.php: $settings, $menus, $page, $scriptBase, $themeBase, $blogPost
$siteName = $settings['site_name'] ?? 'My Site';
if (!empty($settings['logo'])) {
    $logo = $scriptBase . '/CMS/' . ltrim($settings['logo'], '/');
} else {
    $logo = $themeBase . '/images/logo.png';
}
$mainMenu = $menus[0]['items'] ?? [];
$footerMenu = $menus[1]['items'] ?? [];
$social = $settings['social'] ?? [];

require_once __DIR__ . '/includes/menu_helpers.php';
require_once __DIR__ . '/includes/blog_helpers.php';

$activePost = $blogPost ?? null;
if (!$activePost) {
    $activePost = [
        'title' => $page['title'] ?? 'Blog Post',
        'content' => '<p>This blog post could not be loaded.</p>',
    ];
}
$detailTitle = $activePost['title'] ?? ($page['title'] ?? 'Blog Post');
$detailCategory = trim((string) ($activePost['category'] ?? ''));
$detailAuthor = trim((string) ($activePost['author'] ?? ''));
$detailDate = sparkcms_format_blog_date($activePost['publishDate'] ?? $activePost['createdAt'] ?? '');
$detailContent = $activePost['content'] ?? '';
if ($detailContent === '') {
    $detailContent = '<p>This article does not have any published content yet.</p>';
}
$tagSource = $activePost['tags'] ?? '';
if (is_array($tagSource)) {
    $detailTags = array_filter(array_map('trim', $tagSource));
} else {
    $tagString = trim((string) $tagSource);
    $detailTags = $tagString === '' ? [] : array_filter(array_map('trim', explode(',', $tagString)));
}
$backUrl = rtrim($scriptBase, '/') . '/blogs';
?>
<?php $bodyClass = 'page-template min-h-screen flex flex-col'; ?>
<?php include __DIR__ . '/../partials/head.php'; ?>

    <!-- Default Page -->
    <div id="app" class="min-h-screen bg-slate-50 flex flex-col">

        <?php include __DIR__ . '/../partials/header.php'; ?>

        <!-- Main Content -->
        <main id="main-area" class="flex-1">
            <section class="border-b border-slate-200 bg-white py-14">
                <div class="mx-auto w-full max-w-6xl px-4 space-y-4">
                    <a class="text-primary-600 text-sm font-semibold inline-flex items-center gap-2" href="<?php echo htmlspecialchars($backUrl); ?>">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to all posts
                    </a>
                    <?php if ($detailCategory !== ''): ?>
                    <span class="inline-block uppercase text-primary-600 font-semibold text-xs"><?php echo htmlspecialchars($detailCategory); ?></span>
                    <?php endif; ?>
                    <h1 class="text-4xl md:text-5xl font-semibold tracking-tight"><?php echo htmlspecialchars($detailTitle); ?></h1>
                    <?php if ($detailAuthor !== '' || $detailDate !== ''): ?>
                    <div class="text-slate-500 mt-3 flex flex-wrap gap-4 text-sm">
                        <?php if ($detailAuthor !== ''): ?>
                        <span class="inline-flex items-center gap-2"><i class="fas fa-user"></i><?php echo htmlspecialchars($detailAuthor); ?></span>
                        <?php endif; ?>
                        <?php if ($detailDate !== ''): ?>
                        <span class="inline-flex items-center gap-2"><i class="far fa-calendar-alt"></i><?php echo htmlspecialchars($detailDate); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="section">
                <div class="mx-auto w-full max-w-6xl px-4">
                    <article class="mx-auto max-w-3xl space-y-6">
                        <div class="mw-rich-text blog-detail-content text-slate-600">
                            <?php echo $detailContent; ?>
                        </div>
                        <?php if ($detailTags): ?>
                        <div class="mt-8 space-y-3">
                            <span class="uppercase text-slate-500 text-xs font-semibold">Tags</span>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($detailTags as $tag): ?>
                                <span class="inline-flex items-center rounded-full border border-primary-200 bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700">#<?php echo htmlspecialchars($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="mt-8">
                            <a class="btn-outline" href="<?php echo htmlspecialchars($backUrl); ?>">
                                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                                Back to all posts
                            </a>
                        </div>
                    </article>
                </div>
            </section>
        </main>

        <?php include __DIR__ . '/../partials/footer.php'; ?>
