<?php
/**
 * Global Footer Component (Simple & Clean)
 */
?>
</main> <!-- /.main-content -->

<footer class="site-footer">
    <div class="container footer-simple-inner">
        <div class="footer-simple-left">
            <a href="<?= BASE_URL ?>/index.php" class="brand-link" aria-label="CampusMarket Homepage">
                <img src="<?= BASE_URL ?>/assets/images/logo.svg" alt="CampusMarket Logo" style="height: 32px; width: auto;">
            </a>
            <span class="footer-tagline">Student-to-student campus marketplace</span>
        </div>

        <div class="footer-simple-right">
            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>
        </div>
    </div>
</footer>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
