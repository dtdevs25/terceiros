<?php
/**
 * Placeholder para listagem
 */
require_once __DIR__ . '/../../config/config.php';
$page_title = 'Em breve';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="placeholder-page fade-in py-5 text-center">
    <div class="card p-5">
        <h1>🚧 Em Construção</h1>
        <p class="lead mt-3">Esta funcionalidade será implementada em breve.</p>
        <a href="<?php echo SITE_URL; ?>/public/dashboard.php" class="btn btn-outline mt-4">Voltar ao Dashboard</a>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
