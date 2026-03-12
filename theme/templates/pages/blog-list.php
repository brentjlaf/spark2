<?php
// File: blog-list.php
// Template: blog-list
// Variables provided by index.php: $settings, $menus, $page, $scriptBase, $themeBase, $blogPosts
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

$publishedPosts = array_values(array_filter($blogPosts ?? [], 'sparkcms_is_blog_post_live'));
usort($publishedPosts, function ($a, $b) {
    $aDate = $a['publishDate'] ?? $a['createdAt'] ?? '';
    $bDate = $b['publishDate'] ?? $b['createdAt'] ?? '';
    $aTime = strtotime($aDate) ?: 0;
    $bTime = strtotime($bDate) ?: 0;
    return $bTime <=> $aTime;
});
$heroIntro = trim($page['meta_description'] ?? '');
if ($heroIntro === '') {
    $heroIntro = 'Explore stories, tutorials, and product updates from the SparkCMS team.';
}
?>
<?php $bodyClass = 'page-template min-h-screen flex flex-col'; ?>
<?php include __DIR__ . '/../partials/head.php'; ?>

    <!-- Default Page -->
    <div id="app" class="min-h-screen bg-slate-50 flex flex-col">

        <?php include __DIR__ . '/../partials/header.php'; ?>

        <!-- Main Content -->
        <main id="main-area" class="flex-1">
            <section class="border-b border-slate-200 bg-white py-14">
                <div class="mx-auto w-full max-w-6xl px-4 text-center space-y-4">
                    <span class="uppercase text-primary-600 font-semibold tracking-[0.3em] text-xs">Insights</span>
                    <h1 class="text-4xl md:text-5xl font-semibold tracking-tight"><?php echo htmlspecialchars($page['title'] ?? 'Latest Posts'); ?></h1>
                    <?php if ($heroIntro !== ''): ?>
                    <p class="text-lg text-slate-600 max-w-3xl mx-auto">
                        <?php echo htmlspecialchars($heroIntro); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="section">
                <div class="mx-auto w-full max-w-6xl px-4">
                    <?php if ($publishedPosts): ?>
                    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($publishedPosts as $post):
                            $detailUrl = sparkcms_resolve_blog_detail_url($scriptBase, '/blogs', $post['slug'] ?? '');
                            $dateValue = sparkcms_format_blog_date($post['publishDate'] ?? $post['createdAt'] ?? '');
                            $excerpt = trim(strip_tags($post['excerpt'] ?? ''));
                            if ($excerpt === '') {
                                $excerpt = trim(strip_tags($post['content'] ?? ''));
                            }
                            $excerptTruncated = false;
                            if ($excerpt !== '') {
                                if (function_exists('mb_strlen')) {
                                    $excerptLength = mb_strlen($excerpt);
                                } else {
                                    $excerptLength = strlen($excerpt);
                                }
                                if ($excerptLength > 150) {
                                    if (function_exists('mb_substr')) {
                                        $excerpt = mb_substr($excerpt, 0, 150);
                                    } else {
                                        $excerpt = substr($excerpt, 0, 150);
                                    }
                                    $excerptTruncated = true;
                                }
                            }
                        ?>
                        <div>
                            <article class="flex h-full flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                    <?php if (!empty($post['category'])): ?>
                                    <span class="uppercase text-primary-600 font-semibold text-xs"><?php echo htmlspecialchars($post['category']); ?></span>
                                    <?php endif; ?>
                                    <h3 class="text-xl font-semibold text-slate-900">
                                        <a class="text-slate-900 hover:text-primary-600 transition" href="<?php echo htmlspecialchars($detailUrl); ?>">
                                            <?php echo htmlspecialchars($post['title'] ?? 'Untitled Post'); ?>
                                        </a>
                                    </h3>
                                    <?php if ($dateValue !== '' || !empty($post['author'])): ?>
                                    <div class="text-sm text-slate-500 flex flex-wrap gap-3">
                                        <?php if (!empty($post['author'])): ?>
                                        <span class="inline-flex items-center gap-2"><i class="fas fa-user"></i><?php echo htmlspecialchars($post['author']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($dateValue !== ''): ?>
                                        <span class="inline-flex items-center gap-2"><i class="far fa-calendar-alt"></i><?php echo htmlspecialchars($dateValue); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($excerpt !== ''): ?>
                                    <p class="text-slate-600 flex-1"><?php echo htmlspecialchars($excerpt); ?><?php if ($excerptTruncated): ?>…<?php endif; ?></p>
                                    <?php else: ?>
                                    <p class="text-slate-600 flex-1">Read the full article to learn more.</p>
                                    <?php endif; ?>
                                    <a class="mt-2 inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700" href="<?php echo htmlspecialchars($detailUrl); ?>">
                                        Read More
                                        <span aria-hidden="true">→</span>
                                    </a>
                            </article>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12 space-y-4">
                        <p class="text-lg text-slate-600">We haven't published any posts yet. Please check back soon for new updates.</p>
                        <a class="btn-primary" href="<?php echo $scriptBase; ?>/">Return to homepage</a>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if (trim($page['content'] ?? '') !== ''): ?>
            <section class="section border-t border-slate-200">
                <div class="mx-auto w-full max-w-6xl px-4">
                    <div class="drop-area"></div>
                </div>
            </section>
            <?php endif; ?>
        </main>

        <?php include __DIR__ . '/../partials/footer.php'; ?>
