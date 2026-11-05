        </div>
        <footer class="login-footer">&copy; <?= date('Y') ?> Guagua National Colleges, Inc.</footer>
    </section>
</main>
<script src="/assets/js/auth-password.js" defer></script>
<?php if (isset($authScript)): ?><script src="<?= $e($authScript) ?>" defer></script><?php endif; ?>
</body>
</html>
