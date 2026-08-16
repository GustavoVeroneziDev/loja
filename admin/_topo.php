<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
require_once __DIR__ . '/../config/marca.php';

$nomeLoja = NOME_LOJA;
$rotaAtual = $_SERVER['SCRIPT_NAME'] ?? '';
?>
<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel Admin — <?= htmlspecialchars($nomeLoja) ?></title>
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
<?php if (adminLogado()): ?>
<div class="admin-shell">
    <aside class="admin-sidebar d-none d-lg-flex flex-column" id="adminSidebar">
        <div class="admin-sidebar-topo">
            <a class="admin-sidebar-marca" href="<?= URL_BASE ?>/admin/index.php">
                <img src="<?= htmlspecialchars(urlAsset(LOGO_URL)) ?>" alt="<?= htmlspecialchars($nomeLoja) ?>" class="logo-admin">
                <span class="admin-sidebar-marca-texto fw-semibold"><?= htmlspecialchars($nomeLoja) ?></span>
            </a>
            <button type="button" class="admin-sidebar-toggle" id="botaoAlternarSidebar" aria-label="Recolher menu">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>
        <nav class="admin-sidebar-nav flex-grow-1">
            <a class="admin-sidebar-link <?= str_ends_with($rotaAtual, '/admin/index.php') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/index.php" title="Dashboard">
                <i class="bi bi-speedometer2"></i> <span class="admin-sidebar-link-texto">Dashboard</span>
            </a>
            <div class="admin-sidebar-grupo">
                <span class="admin-sidebar-titulo-grupo">Catálogo</span>
                <a class="admin-sidebar-link <?= str_ends_with($rotaAtual, '/admin/categoria.php') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/categoria.php" title="Categorias">
                    <i class="bi bi-tags"></i> <span class="admin-sidebar-link-texto">Categorias</span>
                </a>
                <a class="admin-sidebar-link <?= str_contains($rotaAtual, '/admin/produto/') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/produto/index.php" title="Produtos">
                    <i class="bi bi-box-seam"></i> <span class="admin-sidebar-link-texto">Produtos</span>
                </a>
                <a class="admin-sidebar-link <?= str_ends_with($rotaAtual, '/admin/estoque.php') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/estoque.php" title="Estoque">
                    <i class="bi bi-boxes"></i> <span class="admin-sidebar-link-texto">Estoque</span>
                </a>
            </div>
            <div class="admin-sidebar-grupo">
                <span class="admin-sidebar-titulo-grupo">Vendas</span>
                <a class="admin-sidebar-link <?= str_contains($rotaAtual, '/admin/pedido/') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/pedido/index.php" title="Pedidos">
                    <i class="bi bi-bag-check"></i> <span class="admin-sidebar-link-texto">Pedidos</span>
                </a>
                <a class="admin-sidebar-link <?= str_contains($rotaAtual, '/admin/entregas/') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/entregas/index.php" title="Entregas">
                    <i class="bi bi-truck"></i> <span class="admin-sidebar-link-texto">Entregas</span>
                </a>
                <a class="admin-sidebar-link <?= str_ends_with($rotaAtual, '/admin/cupom.php') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/cupom.php" title="Cupons">
                    <i class="bi bi-ticket-perforated"></i> <span class="admin-sidebar-link-texto">Cupons</span>
                </a>
            </div>
        </nav>
        <div class="admin-sidebar-rodape">
            <a class="admin-sidebar-link <?= str_ends_with($rotaAtual, '/admin/simulacao.php') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/simulacao.php" title="Simulação">
                <i class="bi bi-flask"></i> <span class="admin-sidebar-link-texto">Simulação</span>
            </a>
            <a class="admin-sidebar-link" href="<?= URL_BASE ?>/admin/logout.php" title="Sair">
                <i class="bi bi-box-arrow-right"></i> <span class="admin-sidebar-link-texto">Sair</span>
            </a>
        </div>
    </aside>
    <script>
        // Aplica o estado salvo (recolhida ou não) antes do resto da página desenhar, senão dava um
        // "pisca" toda hora: sidebar abre expandida por uma fração de segundo e só depois encolhe.
        if (localStorage.getItem('adminSidebarRecolhida') === '1') {
            document.getElementById('adminSidebar').classList.add('recolhida');
        }
    </script>

    <nav class="navbar navbar-expand-lg navbar-marca navbar-loja navbar-dark d-lg-none">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= URL_BASE ?>/admin/index.php">
                <img src="<?= htmlspecialchars(urlAsset(LOGO_URL)) ?>" alt="<?= htmlspecialchars($nomeLoja) ?>" class="logo-admin">
                <span class="fw-semibold"><?= htmlspecialchars($nomeLoja) ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navAdmin">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navAdmin">
                <ul class="navbar-nav me-auto nav-categorias-loja">
                    <li class="nav-item">
                        <a class="nav-link <?= str_ends_with($rotaAtual, '/admin/index.php') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= str_ends_with($rotaAtual, '/admin/categoria.php') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/categoria.php">
                            <i class="bi bi-tags"></i> Categorias
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= str_contains($rotaAtual, '/admin/produto/') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/produto/index.php">
                            <i class="bi bi-box-seam"></i> Produtos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= str_ends_with($rotaAtual, '/admin/estoque.php') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/estoque.php">
                            <i class="bi bi-boxes"></i> Estoque
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= str_contains($rotaAtual, '/admin/pedido/') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/pedido/index.php">
                            <i class="bi bi-bag-check"></i> Pedidos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= str_contains($rotaAtual, '/admin/entregas/') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/entregas/index.php">
                            <i class="bi bi-truck"></i> Entregas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= str_ends_with($rotaAtual, '/admin/cupom.php') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/cupom.php">
                            <i class="bi bi-ticket-perforated"></i> Cupons
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= str_ends_with($rotaAtual, '/admin/simulacao.php') ? 'active' : '' ?>" href="<?= URL_BASE ?>/admin/simulacao.php">
                            <i class="bi bi-flask"></i> Simulação
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav align-items-lg-center gap-lg-2 nav-conta-loja">
                    <li class="nav-item">
                        <a href="<?= URL_BASE ?>/admin/logout.php" class="btn btn-sm btn-outline-secondary rounded-pill">
                            <i class="bi bi-box-arrow-right"></i> Sair
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="admin-conteudo">
<?php endif; ?>
<main class="container-fluid py-4 main-conteudo">
