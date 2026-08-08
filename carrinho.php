<?php
session_start();
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/funcoes.php';
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();
garantirTabelaUsuario();
garantirTabelaItemCarrinho();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $idVariacao = $_POST['variacao_id'] ?? '';
    $sucesso = null;

    if ($action === 'adicionar') {
        global $pdo;
        $stmt = $pdo->prepare("SELECT Estoque FROM VariacaoProduto WHERE IDVariacao = :id");
        $stmt->execute(['id' => $idVariacao]);
        $estoque = $stmt->fetchColumn();

        $quantidade = max(1, (int) ($_POST['quantidade'] ?? 1));
        if ($estoque !== false && (int) $estoque > 0) {
            adicionarItemCarrinho($idVariacao, min($quantidade, (int) $estoque));
            $sucesso = true;
        }
    }

    if ($action === 'atualizar') {
        atualizarQuantidadeCarrinho($idVariacao, (int) ($_POST['quantidade'] ?? 0));
        $sucesso = true;
    }

    if ($action === 'remover') {
        atualizarQuantidadeCarrinho($idVariacao, 0);
        $sucesso = true;
    }

    header('Location: ' . URL_BASE . '/carrinho.php' . ($sucesso ? '?ok=1' : '?erro=1'));
    exit;
}

$sucesso = isset($_GET['ok']) ? 'Carrinho atualizado.' : null;
$erro = isset($_GET['erro']) ? 'Não foi possível adicionar — confere o estoque disponível.' : null;

$itens = obterCarrinho();
$total = array_sum(array_column($itens, 'subtotal'));

require __DIR__ . '/geral/header.php';
?>
<div class="row">
    <div class="col-lg-8">
        <h1 class="h4 mb-4">Seu carrinho</h1>
        <?php if ($sucesso): ?><div class="alert alert-success"><?= $sucesso ?></div><?php endif; ?>
        <?php if ($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>

        <?php if (!$itens): ?>
            <div class="card p-5 text-center text-secundario">
                <p class="mb-3">Seu carrinho tá vazio.</p>
                <a href="<?= URL_BASE ?>/index.php" class="btn btn-marca rounded-pill">Ver produtos</a>
            </div>
        <?php else: ?>
            <?php foreach ($itens as $item): $v = $item['variacao']; ?>
                <div class="card p-3 mb-3">
                    <div class="row align-items-center g-3">
                        <div class="col-3 col-md-2">
                            <a href="<?= URL_BASE ?>/produto.php?id=<?= urlencode($v['IDProduto']) ?>">
                                <img src="<?= htmlspecialchars(urlAsset($v['ImagemCapa'] ?? '/geral/img/logo-placeholder.svg')) ?>" class="img-fluid rounded" style="aspect-ratio: 1; object-fit: cover;" alt="">
                            </a>
                        </div>
                        <div class="col-9 col-md-4">
                            <a href="<?= URL_BASE ?>/produto.php?id=<?= urlencode($v['IDProduto']) ?>" class="text-reset text-decoration-none fw-semibold"><?= htmlspecialchars($v['NomeProduto']) ?></a>
                            <?php if ($v['Atributo']): ?><div class="text-secundario small"><?= htmlspecialchars($v['Atributo']) ?></div><?php endif; ?>
                            <div class="text-secundario small"><?= formatarPreco($v['Preco']) ?> cada</div>
                            <?php if ($item['Quantidade'] > $v['Estoque']): ?>
                                <span class="badge-atencao px-2 py-1 small d-inline-block mt-1">só temos <?= (int) $v['Estoque'] ?> em estoque</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-6 col-md-3">
                            <form method="post" class="d-flex align-items-center gap-2">
                                <input type="hidden" name="action" value="atualizar">
                                <input type="hidden" name="variacao_id" value="<?= htmlspecialchars($item['IDVariacao']) ?>">
                                <input type="number" name="quantidade" value="<?= $item['Quantidade'] ?>" min="1" max="<?= (int) $v['Estoque'] ?>" class="form-control form-control-sm" style="width: 70px;">
                                <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill">Atualizar</button>
                            </form>
                        </div>
                        <div class="col-4 col-md-2 fw-semibold"><?= formatarPreco($item['subtotal']) ?></div>
                        <div class="col-2 col-md-1 text-end">
                            <form method="post" onsubmit="return confirm('Remover este item?');">
                                <input type="hidden" name="action" value="remover">
                                <input type="hidden" name="variacao_id" value="<?= htmlspecialchars($item['IDVariacao']) ?>">
                                <button type="submit" class="btn btn-sm btn-perigo rounded-pill" aria-label="Remover"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <div class="card p-4">
            <h2 class="h5 mb-3">Resumo</h2>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secundario">Total</span>
                <span class="fw-semibold h4 mb-0"><?= formatarPreco($total) ?></span>
            </div>
            <button type="button" class="btn btn-marca rounded-pill w-100 py-2" <?= $itens ? '' : 'disabled' ?> disabled title="Checkout chega na próxima etapa">
                Finalizar compra — em breve
            </button>
        </div>
    </div>
</div>
<?php require __DIR__ . '/geral/footer.php'; ?>
