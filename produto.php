<?php
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/funcoes.php';
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();

$idProduto = $_GET['id'] ?? '';
$produto = obterProdutoPorId($idProduto);

if (!$produto || !$produto['Ativo']) {
    header('Location: ' . URL_BASE . '/index.php');
    exit;
}

$variacoes = obterVariacoesPorProduto($idProduto);
$imagens = obterImagensPorProduto($idProduto);

require __DIR__ . '/geral/header.php';
?>
<div class="row g-4">
    <div class="col-md-6">
        <?php if ($imagens): ?>
            <div id="carrosselProduto" class="carousel slide">
                <div class="carousel-inner rounded">
                    <?php foreach ($imagens as $i => $imagem): ?>
                        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                            <img src="<?= htmlspecialchars(urlAsset($imagem['Url'])) ?>" class="d-block w-100" style="aspect-ratio: 1; object-fit: cover;" alt="<?= htmlspecialchars($produto['Nome']) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($imagens) > 1): ?>
                    <button class="carousel-control-prev" type="button" data-bs-target="#carrosselProduto" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carrosselProduto" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <img src="<?= htmlspecialchars(urlAsset('/geral/img/logo-placeholder.svg')) ?>" class="w-100 rounded" style="aspect-ratio: 1; object-fit: contain; background: #1a1d27;" alt="<?= htmlspecialchars($produto['Nome']) ?>">
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <h1 class="h3"><?= htmlspecialchars($produto['Nome']) ?></h1>
        <p class="text-secundario"><?= nl2br(htmlspecialchars($produto['Descricao'] ?? '')) ?></p>

        <?php if (count($variacoes) > 1): ?>
            <div class="mb-3">
                <label class="form-label d-block">Opção</label>
                <div class="btn-group flex-wrap" role="group">
                    <?php foreach ($variacoes as $i => $variacao): ?>
                        <input type="radio" class="btn-check" name="variacao" id="variacao<?= $variacao['IDVariacao'] ?>"
                               data-preco="<?= $variacao['Preco'] ?>" data-estoque="<?= (int) $variacao['Estoque'] ?>"
                               <?= $i === 0 ? 'checked' : '' ?> onchange="atualizarVariacaoSelecionada(this)">
                        <label class="btn btn-outline-secondary" for="variacao<?= $variacao['IDVariacao'] ?>">
                            <?= htmlspecialchars($variacao['Atributo'] ?? 'Padrão') ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <p class="h3 text-marca" id="precoSelecionado"><?= formatarPreco($variacoes[0]['Preco'] ?? 0) ?></p>
        <p class="text-secundario small" id="estoqueSelecionado">
            <?= ($variacoes[0]['Estoque'] ?? 0) > 0 ? (int) $variacoes[0]['Estoque'] . ' em estoque' : 'Fora de estoque' ?>
        </p>

        <button type="button" class="btn btn-marca rounded-pill btn-lg" disabled title="Carrinho chega na próxima etapa">
            <i class="bi bi-cart-plus"></i> Adicionar ao carrinho — em breve
        </button>
    </div>
</div>

<script>
function atualizarVariacaoSelecionada(input) {
    const preco = parseFloat(input.dataset.preco).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    const estoque = parseInt(input.dataset.estoque, 10);
    document.getElementById('precoSelecionado').textContent = preco;
    document.getElementById('estoqueSelecionado').textContent = estoque > 0 ? estoque + ' em estoque' : 'Fora de estoque';
}
</script>
<?php require __DIR__ . '/geral/footer.php'; ?>
