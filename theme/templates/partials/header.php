<?php
$siteName = $siteName ?? 'My Site';
$logo = $logo ?? ($themeBase ? $themeBase . '/images/logo.png' : '');
$mainMenu = $mainMenu ?? [];
?>

    <!-- Header -->
    <header id="header-area" class="site-header">
        <div class="header-inner container">
            <!-- Brand/Logo -->
            <a class="logo inline-flex items-center gap-3 text-lg font-semibold text-white" href="<?php echo $scriptBase; ?>/">
                <img src="<?php echo htmlspecialchars($logo); ?>" alt="<?php echo htmlspecialchars($siteName); ?>">
                <span class="sr-only"><?php echo htmlspecialchars($siteName); ?></span>
            </a>

            <!-- Mobile Toggle Button -->
            <button class="nav-toggle" type="button" id="menuToggle" aria-controls="main-nav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fa-solid fa-bars"></i>
            </button>

            <!-- Navigation -->
            <nav class="main-nav" id="main-nav" role="navigation">
                <ul>
                    <?php renderMenu($mainMenu); ?>
                </ul>

                <div class="nav-actions">
                    <form class="search-box" action="<?php echo $scriptBase; ?>/search" method="get" role="search">
                        <input class="search-input" type="search" name="q" placeholder="Search..." aria-label="Search" />
                    </form>
                    <a href="<?php echo $scriptBase; ?>/contact-us" class="cta-btn">Contact Us</a>
                </div>
            </nav>
        </div>
    </header>
