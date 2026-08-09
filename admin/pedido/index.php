<?php
session_start();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../config/funcoes.php';
exigirLoginAdmin();
garantirTabelaPedido();
garantirTabelaUsuario();

global $pdo;

$filtro = $_GET['filtro'] ?? '';
$sql = "SELECT p.*, u.Nome AS NomeCliente FROM Pedido p JOIN Usuario u ON u.IDUsuario = p.FKUsuario";
$params = [];
// Filtro inválido cai em "sem filtro" — nunca passa um status inexistente direto pro WHERE.
$statusValidos = ['aguardando_pagamento', 'pago', 'preparando', 'enviado', 'entregue', 'cancelado'];
if (in_array($filtro, $statusValidos, true)) {
    $sql .= " WHERE p.Status = :status";
    $params['status'] = $filtro;
}
$sql .= " ORDER BY p.IDPedido DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

require __DIR__ . '/../_topo.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Pedidos</h1>
</div>

<?php if ($filtro !== ''): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <span><i class="bi bi-funnel"></i> Mostrando só pedidos "<?= htmlspecialchars(statusPedidoInfo($filtro)['label']) ?>".</span>
        <a href="<?= URL_BASE ?>/admin/pedido/index.php" class="btn btn-sm btn-outline-secondary rounded-pill">Limpar filtro</a>
    </div>
<?php endif; ?>

<div class="card">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Cliente</th>
                <th>Data</th>
                <th>Total</th>
                <th>Status</th>
                <th style="width: 100px; min-width: 100px; max-width: 100px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pedidos as $pedido): $info = statusPedidoInfo($pedido['Status']); ?>
                <tr>
                    <td class="fw-semibold">#<?= str_pad($pedido['IDPedido'], 5, '0', STR_PAD_LEFT) ?></td>
                    <td><?= htmlspecialchars($pedido['NomeCliente']) ?></td>
                    <td class="text-secundario"><?= date('d/m/Y H:i', strtotime($pedido['MomentoCriacao'])) ?></td>
                    <td><?= formatarPreco($pedido['ValorTotal']) ?></td>
                    <td><span class="<?= $info['badge'] ?> px-2 py-1"><i class="bi <?= $info['icone'] ?>"></i> <?= $info['label'] ?></span></td>
                    <td style="width: 100px; min-width: 100px; max-width: 100px;">
                        <a href="<?= URL_BASE ?>/admin/pedido/detalhe.php?id=<?= (int) $pedido['IDPedido'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pedidos): ?>
                <tr><td colspan="6" class="text-secundario text-center py-4">Nenhum pedido <?= $filtro !== '' ? 'com esse filtro' : 'ainda' ?>.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../_rodape.php'; ?>
