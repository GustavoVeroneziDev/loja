<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
garantirTabelaUsuario();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();
garantirTabelaFavorito();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirLoginCliente();
    if (($_POST['action'] ?? '') === 'alternar') {
        alternarFavorito($_POST['produto_id'] ?? '');
    }
    $voltarPara = $_POST['voltar_para'] ?? (URL_BASE . '/usuario/favoritos.php');
    header('Location: ' . $voltarPara);
    exit;
}

exigirLoginCliente();
$favoritos = obterFavoritos();
$produtos = $favoritos;
$voltarParaCategoria = URL_BASE . '/usuario/favoritos.php';

require __DIR__ . '/../geral/header.php';
?>
<h1 class="h3 mb-4 titulo-estilizado">Meus favoritos</h1>

<?php if (!$favoritos): ?>
    <div class="card p-5 text-center text-secundario">
        <p class="mb-3">Você ainda não favoritou nada.</p>
        <a href="<?= URL_BASE ?>/index.php" class="btn btn-marca rounded-pill">Ver produtos</a>
    </div>
<?php else: ?>
    <?php require __DIR__ . '/../geral/_grade-produtos.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/../geral/footer.php'; ?>
