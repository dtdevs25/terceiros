<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo html_escape($page_title ?? 'Sistema'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger"><?php echo html_escape($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?php echo html_escape($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
<?php endif; ?>