<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
exigirLoginCliente();
garantirTabelaUsuario();
garantirTabelaPedido();
garantirTabelaItemPedido();
garantirTabelaHistoricoStatusPedido();

$idPedido = (int) ($_GET['id'] ?? 0);
$pedido = obterPedidoPorId($idPedido);

// Confere que o pedido é mesmo do usuário logado — sem isso, dava pra ver pedido de qualquer
// cliente só trocando o ?id= na URL.
if (!$pedido || $pedido['FKUsuario'] !== $_SESSION['usuario_id']) {
    header('Location: ' . URL_BASE . '/usuario/pedidos.php');
    exit;
}

$itens = obterItensPedido($idPedido);
$historico = obterHistoricoPedido($idPedido);
$info = statusPedidoInfo($pedido['Status']);
$novo = isset($_GET['novo']);

require __DIR__ . '/../geral/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <h1 class="h3 mb-0 titulo-estilizado">Pedido #<?= str_pad($pedido['IDPedido'], 5, '0', STR_PAD_LEFT) ?></h1>
    <a href="<?= URL_BASE ?>/usuario/pedidos.php" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="bi bi-arrow-left"></i> Meus pedidos</a>
</div>

<?php if ($novo): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle"></i> Pedido criado com sucesso! O pagamento é configurado na próxima etapa.</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-7">
        <div class="card p-4 mb-4">
            <h2 class="h5 mb-3">Itens</h2>
            <?php foreach ($itens as $item): ?>
                <div class="d-flex justify-content-between gap-2 mb-2">
                    <span><?= (int) $item['Quantidade'] ?>x <?= htmlspecialchars($item['NomeProduto']) ?><?= $item['DescricaoVariacao'] ? ' (' . htmlspecialchars($item['DescricaoVariacao']) . ')' : '' ?></span>
                    <span class="text-nowrap"><?= formatarPreco($item['PrecoUnitario'] * $item['Quantidade']) ?></span>
                </div>
            <?php endforeach; ?>
            <hr>
            <div class="d-flex justify-content-between mb-1">
                <span class="text-secundario">Subtotal</span>
                <span><?= formatarPreco($pedido['ValorSubtotal']) ?></span>
            </div>
            <?php if ($pedido['ValorDesconto'] > 0): ?>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-secundario">Desconto</span>
                    <span class="text-sucesso">-<?= formatarPreco($pedido['ValorDesconto']) ?></span>
                </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-secundario">
                    Frete
                    <?php if ($pedido['FreteTransportadora']): ?>
                        <span class="d-block text-secundario small"><?= htmlspecialchars($pedido['FreteTransportadora']) ?> — <?= htmlspecialchars($pedido['FreteServico']) ?><?= $pedido['FretePrazoDias'] ? ' · ' . (int) $pedido['FretePrazoDias'] . ' dia(s) útil(eis)' : '' ?></span>
                    <?php endif; ?>
                </span>
                <span><?= $pedido['ValorFrete'] > 0 ? formatarPreco($pedido['ValorFrete']) : 'Grátis' ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="fw-semibold">Total</span>
                <span class="fw-semibold h5 mb-0"><?= formatarPreco($pedido['ValorTotal']) ?></span>
            </div>
        </div>

        <div class="card p-4">
            <h2 class="h5 mb-3">Endereço de entrega</h2>
            <p class="mb-0">
                <?= htmlspecialchars($pedido['EnderecoLogradouro']) ?>, <?= htmlspecialchars($pedido['EnderecoNumero']) ?>
                <?php if ($pedido['EnderecoComplemento']): ?> — <?= htmlspecialchars($pedido['EnderecoComplemento']) ?><?php endif; ?><br>
                <?php if ($pedido['EnderecoBairro']): ?><?= htmlspecialchars($pedido['EnderecoBairro']) ?> — <?php endif; ?>
                <?= htmlspecialchars($pedido['EnderecoCidade']) ?>/<?= htmlspecialchars($pedido['EnderecoUF']) ?><br>
                CEP <?= htmlspecialchars($pedido['EnderecoCep']) ?>
            </p>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card p-4">
            <h2 class="h5 mb-3">Status</h2>
            <p class="mb-3"><span class="<?= $info['badge'] ?> px-2 py-1"><i class="bi <?= $info['icone'] ?>"></i> <?= $info['label'] ?></span></p>
            <?php if ($pedido['CodigoRastreio']): ?>
                <p class="mb-3"><i class="bi bi-truck"></i> Rastreio: <strong><?= htmlspecialchars($pedido['CodigoRastreio']) ?></strong></p>
            <?php endif; ?>
            <h3 class="h6 text-secundario mb-2">Histórico</h3>
            <ul class="list-unstyled mb-0">
                <?php foreach (array_reverse($historico) as $h): $infoH = statusPedidoInfo($h['StatusNovo']); ?>
                    <li class="d-flex justify-content-between gap-2 py-2 border-bottom small">
                        <span><i class="bi <?= $infoH['icone'] ?>"></i> <?= $infoH['label'] ?></span>
                        <span class="text-secundario text-nowrap"><?= date('d/m/Y H:i', strtotime($h['MomentoMudanca'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../geral/footer.php'; ?>
