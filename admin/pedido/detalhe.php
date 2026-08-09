<?php
session_start();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../config/funcoes.php';
exigirLoginAdmin();
garantirTabelaPedido();
garantirTabelaItemPedido();
garantirTabelaHistoricoStatusPedido();
garantirTabelaUsuario();

global $pdo;

$idPedido = (int) ($_GET['id'] ?? 0);
$pedido = obterPedidoPorId($idPedido);
if (!$pedido) {
    header('Location: ' . URL_BASE . '/admin/pedido/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sucesso = null;

    if ($action === 'mudar_status') {
        $novoStatus = $_POST['status'] ?? '';
        $statusValidos = ['aguardando_pagamento', 'pago', 'preparando', 'enviado', 'entregue', 'cancelado'];
        if (in_array($novoStatus, $statusValidos, true)) {
            $sucesso = mudarStatusPedido($idPedido, $novoStatus);
        }
    }

    if ($action === 'atualizar_rastreio') {
        $codigo = trim($_POST['codigo_rastreio'] ?? '');
        $stmt = $pdo->prepare("UPDATE Pedido SET CodigoRastreio = :codigo WHERE IDPedido = :id");
        $stmt->execute(['codigo' => $codigo !== '' ? $codigo : null, 'id' => $idPedido]);
        $sucesso = true;
    }

    header('Location: ' . URL_BASE . '/admin/pedido/detalhe.php?id=' . $idPedido . ($sucesso ? '&ok=1' : '&erro=1'));
    exit;
}

$sucesso = isset($_GET['ok']) ? 'Operação realizada com sucesso.' : null;
$erro = isset($_GET['erro']) ? 'Não foi possível concluir a ação.' : null;

$pedido = obterPedidoPorId($idPedido);
$itens = obterItensPedido($idPedido);
$historico = obterHistoricoPedido($idPedido);
$info = statusPedidoInfo($pedido['Status']);
$statusOpcoes = ['aguardando_pagamento', 'pago', 'preparando', 'enviado', 'entregue', 'cancelado'];

require __DIR__ . '/../_topo.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <h1 class="h4 mb-0">Pedido #<?= str_pad($pedido['IDPedido'], 5, '0', STR_PAD_LEFT) ?></h1>
    <a href="<?= URL_BASE ?>/admin/pedido/index.php" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if ($sucesso): ?><script>document.addEventListener('DOMContentLoaded', function () { mostrarToastSucesso(<?= json_encode($sucesso) ?>); });</script><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card p-4 mb-4">
            <h2 class="h5 mb-3">Cliente</h2>
            <p class="mb-0"><?= htmlspecialchars($pedido['NomeCliente']) ?><br><span class="text-secundario small"><?= htmlspecialchars($pedido['EmailCliente']) ?></span></p>
        </div>

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
                <span class="text-secundario">Frete</span>
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

    <div class="col-lg-5">
        <div class="card p-4 mb-4">
            <h2 class="h5 mb-3">Status</h2>
            <p class="mb-3"><span class="<?= $info['badge'] ?> px-2 py-1"><i class="bi <?= $info['icone'] ?>"></i> <?= $info['label'] ?></span></p>
            <form method="post" class="d-flex gap-2 mb-4">
                <input type="hidden" name="action" value="mudar_status">
                <select name="status" class="form-select">
                    <?php foreach ($statusOpcoes as $opcao): ?>
                        <option value="<?= $opcao ?>" <?= $pedido['Status'] === $opcao ? 'selected' : '' ?>><?= htmlspecialchars(statusPedidoInfo($opcao)['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-marca rounded-pill text-nowrap">Mudar</button>
            </form>

            <form method="post" class="d-flex gap-2">
                <input type="hidden" name="action" value="atualizar_rastreio">
                <input type="text" name="codigo_rastreio" class="form-control" placeholder="Código de rastreio" value="<?= htmlspecialchars($pedido['CodigoRastreio'] ?? '') ?>">
                <button type="submit" class="btn btn-outline-secondary rounded-pill text-nowrap">Salvar</button>
            </form>
        </div>

        <div class="card p-4">
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
<?php require __DIR__ . '/../_rodape.php'; ?>
