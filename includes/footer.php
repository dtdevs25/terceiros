<?php
// footer.php
// Garante que o script PHP não tenha saída desnecessária antes do HTML
?>

</div> <!-- Fecha o container principal iniciado no header.php -->

<footer class="mt-auto py-3 bg-light border-top">
    <div class="container text-center">
        <span class="text-muted" style="font-size: 0.85rem;">Gestão de Terceiros © <?php echo date('Y'); ?> CTDI do Brasil Ltda. Todos os direitos reservados.</span>
    </div>
</footer>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Custom JS (se necessário) -->
<?php if (defined('APP_URL')): ?>
    <script src="<?php echo rtrim(APP_URL, '/'); ?>/js/script.js"></script>
<?php else: ?>
    <script>console.warn('APP_URL não está definida. Verifique o arquivo config.php.');</script>
<?php endif; ?>

</body>
</html>