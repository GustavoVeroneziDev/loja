<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';

garantirTabelaConfiguracaoLoja();
garantirConfiguracaoLojaPadrao();
garantirTabelaCategoria();

$nomeLoja = obterConfiguracaoLoja('nome_loja', 'Minha Loja');
$corPrimaria = obterConfiguracaoLoja('cor_primaria', '#7c3aed');
$corSecundaria = obterConfiguracaoLoja('cor_secundaria', '#d4af37');
$logoUrl = obterConfiguracaoLoja('logo_url', URL_BASE . '/geral/img/logo-placeholder.svg');
$categoriasNav = array_filter(obterCategoriasArvore(), fn($c) => empty($c['FKCategoriaPai']));
?>
<!doctype html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($nomeLoja) ?></title>
    <link rel="icon" href="<?= htmlspecialchars(urlAsset($logoUrl)) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= URL_BASE ?>/geral/marca.css" rel="stylesheet">
    <style>
        :root {
            --cor-primaria: <?= htmlspecialchars($corPrimaria) ?>;
            --cor-secundaria: <?= htmlspecialchars($corSecundaria) ?>;
        }
    </style>
    <link href="<?= URL_BASE ?>/geral/estilo.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-marca navbar-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= URL_BASE ?>/index.php">
            <img src="<?= htmlspecialchars(urlAsset($logoUrl)) ?>" alt="<?= htmlspecialchars($nomeLoja) ?>" class="logo-loja">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navLoja">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navLoja">
            <ul class="navbar-nav me-auto">
                <?php foreach ($categoriasNav as $categoria): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= URL_BASE ?>/categoria.php?id=<?= urlencode($categoria['IDCategoria']) ?>">
                            <?= htmlspecialchars($categoria['Nome']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <ul class="navbar-nav align-items-lg-center gap-lg-2">
                <li class="nav-item">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" disabled title="Carrinho chega na próxima etapa">
                        <i class="bi bi-cart"></i> Carrinho
                    </button>
                </li>
                <?php if (clienteLogado()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= URL_BASE ?>/usuario/minha-conta.php"><i class="bi bi-person-circle"></i> Minha conta</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= URL_BASE ?>/usuario/login.php"><i class="bi bi-person"></i> Entrar</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="container py-4">
