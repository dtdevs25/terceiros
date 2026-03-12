<?php
/**
 * Layout Principal - Topo (Header)
 * Sistema de Gerenciamento de Funcionários
 */

// Garantir que a sessão está iniciada e o config carregado
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}

// Verificar se o usuário está logado
verificarLogin();

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_hierarquia = $_SESSION['hierarquia'] ?? 'visualizador';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo gerarTokenCSRF(); ?>">
    <title><?php echo isset($page_title) ? $page_title : SITE_NAME; ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/responsive.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.ico">
    
    <!-- Lucide Icons (opcional, se usar no projeto) -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="layout">
        <!-- Header / Navbar Principal -->
        <header class="header">
            <a href="<?php echo SITE_URL; ?>/public/dashboard.php" class="logo">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-2"><path d="M2 21a8 8 0 0 1 13.21-6.08"/><path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M19 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M17 13a5.5 5.5 0 0 1 5 5v3"/></svg>
                <span>TERCEIROS</span>
            </a>

            <div class="user-menu">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($usuario_nome, 0, 1)); ?>
                    </div>
                    <span><?php echo htmlspecialchars($usuario_nome); ?></span>
                </div>
                <a href="<?php echo SITE_URL; ?>/public/index.php?route=auth&action=logout" class="logout-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    <span>Sair</span>
                </a>
            </div>
        </header>

        <!-- Sidebar / Menu Lateral -->
        <aside class="sidebar" id="sidebar">
            <button class="sidebar-toggle" id="sidebarToggle">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left"><path d="m15 18-6-6 6-6"/></svg>
            </button>

            <ul class="sidebar-menu">
                <li>
                    <a href="<?php echo SITE_URL; ?>/public/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg></span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/public/funcionarios/" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'funcionarios') !== false ? 'active' : ''; ?>">
                        <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                        <span class="text">Funcionários</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/public/empresas/" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'empresas') !== false ? 'active' : ''; ?>">
                        <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building-2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9h0"/><path d="M9 12h0"/><path d="M9 15h0"/><path d="M13 15h0"/></svg></span>
                        <span class="text">Empresas</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/public/postos/" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'postos') !== false ? 'active' : ''; ?>">
                        <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg></span>
                        <span class="text">Postos</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/public/treinamentos/" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'treinamentos') !== false ? 'active' : ''; ?>">
                        <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-graduation-cap"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></span>
                        <span class="text">Treinamentos</span>
                    </a>
                </li>
                <?php if ($usuario_hierarquia === 'gerente' || $usuario_hierarquia === 'administrador'): ?>
                <li>
                    <a href="<?php echo SITE_URL; ?>/public/relatorios/" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'relatorios') !== false ? 'active' : ''; ?>">
                        <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
                        <span class="text">Relatórios</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </aside>

        <!-- Conteúdo Principal -->
        <main class="content">
