</div> <!-- Fecha o container principal iniciado no header.php -->

<footer class="mt-auto py-3 bg-light border-top">
    <div class="container text-center">
        <span class="text-muted">Gestão de Terceiros &copy; <?php echo date("Y"); ?></span>
    </div>
</footer>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS (se necessário) -->
<?php if (defined('APP_URL')) : // Garante que APP_URL está definida ?>
<script src="<?php echo APP_URL; ?>/js/script.js"></script>
<?php endif; ?>

</body>
</html>

