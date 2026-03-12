<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/functions.php';

require_login();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_nome = $_SESSION['user_nome'] ?? 'Usuário';
$is_admin = is_admin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? html_escape($page_title) : 'Gestão de Terceiros'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/css/style.css">
    <style>
        body {
            margin: 0 !important;
            padding: 0 !important;
        }
        .bg-royal-blue {
            background-color: #4169E1 !important;
        }
        .navbar.sticky-top {
            margin-top: 0 !important;
            padding-top: 0 !important;
            top: 0 !important;
            position: sticky;
            z-index: 1030;
            min-height: 70px;
        }
        .logo-navbar {
            height: 25px;
            width: auto;
            margin-top: 2px;
            margin-right: 15px;
        }
        .navbar .navbar-brand,
        .navbar .nav-link,
        .navbar .navbar-text,
        .navbar .btn {
            display: flex;
            align-items: center;
            height: 100%;
        }

        /* --- REGRAS PARA MELHORAR AS CORES DO TEXTO --- */
        .bg-royal-blue .navbar-brand,
        .bg-royal-blue .navbar-text {
            color: #ffffff;
        }
        .bg-royal-blue .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.85);
        }
        .bg-royal-blue .navbar-nav .nav-link.active,
        .bg-royal-blue .navbar-nav .nav-link:hover {
            color: #ffffff;
        }
        .bg-royal-blue .navbar-nav .nav-link.active {
            font-weight: 500;
        }

        /* --- ESTILOS MODERNOS PARA MENU MOBILE --- */
        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: rgba(65, 105, 225, 0.98);
                backdrop-filter: blur(10px);
                border-radius: 0 0 20px 20px;
                margin-top: 15px;
                padding: 20px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.1);
                animation: slideDown 0.3s ease-out;
            }

            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .navbar-nav {
                gap: 8px;
                margin-bottom: 15px;
            }

            .navbar-nav .nav-item {
                margin: 0;
            }

            .navbar-nav .nav-link {
                padding: 12px 20px !important;
                border-radius: 12px;
                transition: all 0.3s ease;
                font-weight: 500;
                position: relative;
                overflow: hidden;
            }

            .navbar-nav .nav-link:hover {
                background: rgba(255, 255, 255, 0.15);
                transform: translateX(8px);
                color: #ffffff !important;
            }

            .navbar-nav .nav-link.active {
                background: rgba(255, 255, 255, 0.2);
                color: #ffffff !important;
                box-shadow: 0 4px 15px rgba(255, 255, 255, 0.1);
            }

            .navbar-nav .nav-link::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                height: 100%;
                width: 4px;
                background: #ffffff;
                transform: scaleY(0);
                transition: transform 0.3s ease;
                border-radius: 0 4px 4px 0;
            }

            .navbar-nav .nav-link:hover::before,
            .navbar-nav .nav-link.active::before {
                transform: scaleY(1);
            }

            /* Estilos específicos para o dropdown do menu de administração */
            .navbar-nav .dropdown-toggle::after {
                margin-left: 8px;
                transition: transform 0.3s ease;
            }

            .navbar-nav .dropdown.show .dropdown-toggle::after {
                transform: rotate(180deg);
            }

            .dropdown-menu {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(65, 105, 225, 0.2);
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
                margin-top: 8px;
                padding: 8px;
                animation: dropdownSlide 0.3s ease-out;
            }

            @keyframes dropdownSlide {
                from {
                    opacity: 0;
                    transform: translateY(-10px) scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }

            .dropdown-item {
                border-radius: 8px;
                padding: 10px 16px;
                transition: all 0.2s ease;
                color: #4169E1 !important;
                font-weight: 500;
                position: relative;
                overflow: hidden;
            }

            .dropdown-item:hover {
                background: rgba(65, 105, 225, 0.1);
                transform: translateX(4px);
                color: #4169E1 !important;
            }

            .dropdown-item.active {
                background: rgba(65, 105, 225, 0.15);
                color: #4169E1 !important;
                font-weight: 600;
            }

            .dropdown-item::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                height: 100%;
                width: 3px;
                background: #4169E1;
                transform: scaleY(0);
                transition: transform 0.3s ease;
                border-radius: 0 3px 3px 0;
            }

            .dropdown-item:hover::before,
            .dropdown-item.active::before {
                transform: scaleY(1);
            }

            /* Estilo minimalista para o ícone de logout no mobile */
            .btn-outline-light.d-lg-none {
                border: none;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 50%;
                width: 44px;
                height: 44px;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s ease;
                backdrop-filter: blur(10px);
                margin-top: 10px;
                align-self: center;
            }

            .btn-outline-light.d-lg-none:hover {
                background: rgba(255, 255, 255, 0.2);
                transform: scale(1.1);
                box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
            }

            .btn-outline-light.d-lg-none i {
                font-size: 18px;
                color: #ffffff;
            }

            /* Separador visual entre menu e logout */
            .d-lg-flex {
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                padding-top: 15px;
                margin-top: 10px;
                justify-content: center;
            }

            /* Ocultar elementos desnecessários no mobile */
            .navbar-text {
                display: none !important;
            }

            .btn-outline-light.d-none.d-lg-block {
                border-radius: 12px;
                padding: 12px 24px;
                font-weight: 600;
                transition: all 0.3s ease;
                border: 2px solid rgba(255, 255, 255, 0.3);
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
            }

            .btn-outline-light.d-none.d-lg-block:hover {
                background: rgba(255, 255, 255, 0.2);
                border-color: rgba(255, 255, 255, 0.5);
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            }
        }

        /* --- MELHORIAS NO BOTÃO TOGGLER --- */
        .navbar-toggler {
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 8px 12px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .navbar-toggler:hover {
            border-color: rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .navbar-toggler:focus {
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 1%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='m4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-royal-blue sticky-top">
    <div class="container-fluid h-100 d-flex align-items-center">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo APP_URL; ?>/dashboard.php">
            <img src="https://www.ctdi.com/wp-content/uploads/2020/12/ctdi-flat-logo-white-1024x223.png" alt="CTDI Logo" class="logo-navbar">
            <span class="d-none d-sm-inline">Gestão de Terceiros</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse h-100" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 h-100 align-items-center ms-lg-5">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'monitoramento.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/monitoramento.php">Monitoramento</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'terceiros.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/terceiros.php">Cadastrar Terceiro</a>
                </li>
                <?php if ($is_admin): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'logs_atividades.php' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/logs_atividades.php">Logs de Atividades</a>
                </li>
                <?php endif; ?>
                <?php if ($is_admin): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? 'active' : ''; ?>" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Administração
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/usuarios.php">Gerenciar Usuários</a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/empresas.php">Gerenciar Empresas</a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/filiais.php">Gerenciar Filiais</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
            
            <div class="d-lg-flex align-items-center">
                 <span class="navbar-text me-lg-3 mb-2 mb-lg-0 d-none d-lg-block">
                    Olá, <?php echo html_escape($user_nome); ?>!
                </span>
                <a href="<?php echo APP_URL; ?>/logout.php" class="btn btn-outline-light d-none d-lg-block">Sair</a>
                <a href="<?php echo APP_URL; ?>/logout.php" class="btn btn-outline-light d-lg-none"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <?php
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . html_escape($_SESSION['success_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . html_escape($_SESSION['error_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        unset($_SESSION['error_message']);
    }
    ?>
