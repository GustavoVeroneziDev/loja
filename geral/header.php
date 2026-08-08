<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
require_once __DIR__ . '/../config/marca.php';

// Admin logado nunca vê a vitrine — a área dele é o painel, não o site de compra. Exceção: link
// de pré-visualização (?preview=1), que o admin usa de propósito pra ver um produto em rascunho.
if (adminLogado() && !isset($_GET['preview'])) {
    header('Location: ' . URL_BASE . '/admin/index.php');
    exit;
}

garantirTabelaCategoria();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();
garantirTabelaUsuario();
garantirTabelaItemCarrinho();
garantirTabelaFavorito();

$nomeLoja = NOME_LOJA;
$categoriasNav = array_filter(obterCategoriasArvore(), fn($c) => empty($c['FKCategoriaPai']));
$totalCarrinho = contarItensCarrinho();
?>
<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($nomeLoja) ?></title>
    <link rel="icon" href="<?= htmlspecialchars(urlAsset(FAVICON_URL)) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= URL_BASE ?>/geral/estrutura.css" rel="stylesheet">
    <link href="<?= URL_BASE ?>/geral/estilo.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-marca navbar-loja navbar-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= URL_BASE ?>/index.php">
            <img src="<?= htmlspecialchars(urlAsset(LOGO_URL)) ?>" alt="<?= htmlspecialchars($nomeLoja) ?>" class="logo-loja">
            <span class="titulo-estilizado wordmark-loja"><?= htmlspecialchars($nomeLoja) ?></span>
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
                    <a class="btn btn-sm btn-outline-secondary rounded-pill position-relative" href="<?= URL_BASE ?>/carrinho.php">
                        <i class="bi bi-cart"></i> Carrinho
                        <?php if ($totalCarrinho > 0): ?>
                            <span class="badge-carrinho"><?= $totalCarrinho ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php if (clienteLogado()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars(primeiroNome($_SESSION['usuario_nome'])) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= URL_BASE ?>/usuario/minha-conta.php"><i class="bi bi-person"></i> Minha conta</a></li>
                            <li><a class="dropdown-item" href="<?= URL_BASE ?>/usuario/favoritos.php"><i class="bi bi-heart"></i> Favoritos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item dropdown-item-perigo" href="<?= URL_BASE ?>/usuario/logout.php"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
                        </ul>
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
<main class="container py-4 main-conteudo">
