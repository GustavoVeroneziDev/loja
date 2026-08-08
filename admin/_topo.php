<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';

garantirTabelaConfiguracaoLoja();
garantirConfiguracaoLojaPadrao();
$nomeLoja = obterConfiguracaoLoja('nome_loja', 'Minha Loja');
$logoUrl = obterConfiguracaoLoja('logo_url', '/geral/img/Logo-texto.svg');
$faviconUrl = obterConfiguracaoLoja('favicon_url', $logoUrl);
?>
<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel Admin — <?= htmlspecialchars($nomeLoja) ?></title>
    <link rel="icon" href="<?= htmlspecialchars(urlAsset($faviconUrl)) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= URL_BASE ?>/geral/marca.css" rel="stylesheet">
    <link href="<?= URL_BASE ?>/geral/estilo.css" rel="stylesheet">
</head>
<body>
<?php if (adminLogado()): ?>
<nav class="navbar navbar-expand-lg navbar-marca navbar-light">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= URL_BASE ?>/admin/index.php">
            <span class="badge-admin px-2 py-1">Admin</span> <?= htmlspecialchars($nomeLoja) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navAdmin">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navAdmin">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="<?= URL_BASE ?>/admin/index.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= URL_BASE ?>/admin/categoria.php">Categorias</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= URL_BASE ?>/admin/produto/index.php">Produtos</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= URL_BASE ?>/admin/configuracoes.php">Configurações</a></li>
            </ul>
            <a href="<?= URL_BASE ?>/admin/logout.php" class="btn btn-sm btn-outline-secondary rounded-pill">
                <i class="bi bi-box-arrow-right"></i> Sair
            </a>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container py-4 main-conteudo">
