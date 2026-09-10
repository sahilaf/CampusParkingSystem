<?php
// footer.php — closes the page shell opened by header.php.
?>
</main><!-- /#main-content -->

<footer class="site-footer" role="contentinfo">
    <div class="site-footer-inner">
        <div class="flex items-center gap-sm">
            <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="CampusPark" style="width:24px;height:24px;border-radius:6px;object-fit:cover;" aria-hidden="true" />
            <span class="footer-copy">CampusPark &copy; <?= date('Y') ?></span>
        </div>
        <ul class="footer-links" role="list">
            <li><a href="<?= BASE_URL ?>/public/privacy-policy.php">Privacy Policy</a></li>
            <li><a href="<?= BASE_URL ?>/public/terms-of-service.php">Terms of Service</a></li>
            <li><a href="#">Support</a></li>
        </ul>
    </div>
</footer>

<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= file_exists(__DIR__ . '/../assets/js/main.js') ? filemtime(__DIR__ . '/../assets/js/main.js') : time() ?>"></script>
</body>
</html>
