    <!-- Footer -->
    <footer id="footer-area" class="site-footer">
        <div class="footer-main container">
            <div class="footer-brand space-y-4">
                <a href="<?php echo $scriptBase; ?>/" class="inline-flex items-center gap-3 text-lg font-semibold text-white">
                    <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" class="h-10 w-auto">
                    <span class="sr-only"><?php echo htmlspecialchars($siteName); ?></span>
                </a>
                <p class="text-sm">Your trusted partner for exceptional service and innovative solutions.</p>
                <div class="footer-social">
                    <?php if (!empty($social['facebook'])): ?>
                    <a href="<?php echo htmlspecialchars($social['facebook']); ?>" aria-label="Facebook" target="_blank">
                        <i class="fa-brands fa-facebook-f"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($social['twitter'])): ?>
                    <a href="<?php echo htmlspecialchars($social['twitter']); ?>" aria-label="Twitter" target="_blank">
                        <i class="fa-brands fa-x-twitter"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($social['instagram'])): ?>
                    <a href="<?php echo htmlspecialchars($social['instagram']); ?>" aria-label="Instagram" target="_blank">
                        <i class="fa-brands fa-instagram"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($social['linkedin'])): ?>
                    <a href="<?php echo htmlspecialchars($social['linkedin']); ?>" aria-label="LinkedIn" target="_blank">
                        <i class="fa-brands fa-linkedin-in"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($social['youtube'])): ?>
                    <a href="<?php echo htmlspecialchars($social['youtube']); ?>" aria-label="YouTube" target="_blank">
                        <i class="fa-brands fa-youtube"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <nav class="footer-menu">
                <h5>Quick Links</h5>
                <ul>
                    <?php renderFooterMenu($footerMenu); ?>
                </ul>
            </nav>
            <div class="footer-newsletter space-y-4">
                <h5>Stay Connected</h5>
                <p>Sign up to receive updates, news, and more from our team.</p>
                <form class="flex flex-col gap-3">
                    <input type="email" placeholder="Enter your email" class="input" aria-label="Email address">
                    <button type="button" class="btn-primary">Subscribe</button>
                </form>
            </div>
        </div>
        <div class="footer-copy">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?>. Built with SparkCMS.</p>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button id="back-to-top-btn" class="fixed bottom-6 right-6 hidden h-12 w-12 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg transition hover:bg-primary-700" style="z-index: 1000;" aria-label="Back to Top">
        <i class="fa-solid fa-chevron-up" aria-hidden="true"></i>
    </button>
</div>

<!-- Javascript -->
<script>window.cmsBase = <?php echo json_encode($scriptBase); ?>;</script>
<script src="<?php echo $themeBase; ?>/js/combined.js?v=mw3.2"></script>
<script>
    (function () {
        const toggle = document.getElementById('menuToggle');
        const nav = document.getElementById('main-nav');
        if (toggle && nav) {
            toggle.addEventListener('click', function () {
                const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', String(!isExpanded));
                nav.classList.toggle('active');
            });
        }

        const backToTopBtn = document.getElementById('back-to-top-btn');
        if (!backToTopBtn) {
            return;
        }

        window.addEventListener('scroll', function () {
            if (window.scrollY > 100) {
                backToTopBtn.style.display = 'flex';
            } else {
                backToTopBtn.style.display = 'none';
            }
        });

        backToTopBtn.addEventListener('click', function (e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    })();
</script>

    </body>
</html>
