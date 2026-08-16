<?php
session_start();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../config/funcoes.php';
exigirLoginAdmin();
garantirTabelaPedido();
garantirTabelaUsuario();

global $pdo;

$filtro = $_GET['filtro'] ?? '';
$mostrarSimulacao = isset($_GET['simulacao']);
$sql = "SELECT p.*, u.Nome AS NomeCliente FROM Pedido p JOIN Usuario u ON u.IDUsuario = p.FKUsuario";
$condicoes = [];
$params = [];
// Pedido de simulação (Admin > Simulação) fica fora por padrão — não é pedido de cliente de
// verdade, só polui a lista real. Só aparece com ?simulacao=1 explícito.
if (!$mostrarSimulacao) {
    $condicoes[] = "p.Simulacao = 0";
}
// Filtro inválido cai em "sem filtro" — nunca passa um status inexistente direto pro WHERE.
$statusValidos = ['aguardando_pagamento', 'pago', 'preparando', 'enviado', 'entregue', 'cancelado'];
if (in_array($filtro, $statusValidos, true)) {
    $condicoes[] = "p.Status = :status";
    $params['status'] = $filtro;
}
if ($condicoes) {
    $sql .= " WHERE " . implode(' AND ', $condicoes);
}
$sql .= " ORDER BY p.IDPedido DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

require __DIR__ . '/../_topo.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Pedidos</h1>
    <a href="<?= URL_BASE ?>/admin/pedido/index.php<?= $mostrarSimulacao ? '' : '?simulacao=1' ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
        <i class="bi bi-flask"></i> <?= $mostrarSimulacao ? 'Esconder' : 'Mostrar' ?> pedidos de simulação
    </a>
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
                <th class="d-none d-md-table-cell">Cliente</th>
                <th class="d-none d-md-table-cell">Data</th>
                <th>Total</th>
                <th>Status</th>
                <th class="d-none d-md-table-cell" style="width: 100px; min-width: 100px; max-width: 100px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pedidos as $pedido): $info = statusPedidoInfo($pedido['Status']); ?>
                <tr class="linha-expandivel" data-alvo-expandir="detalhesPedido<?= $pedido['IDPedido'] ?>">
                    <td class="fw-semibold">
                        #<?= str_pad($pedido['IDPedido'], 5, '0', STR_PAD_LEFT) ?>
                        <?php if ($pedido['Simulacao']): ?><span class="badge-destaque px-2 py-1 small"><i class="bi bi-flask"></i> teste</span><?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell"><?= htmlspecialchars($pedido['NomeCliente']) ?></td>
                    <td class="d-none d-md-table-cell text-secundario"><?= date('d/m/Y H:i', strtotime($pedido['MomentoCriacao'])) ?></td>
                    <td><?= formatarPreco($pedido['ValorTotal']) ?></td>
                    <td>
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <span class="<?= $info['badge'] ?> px-2 py-1"><i class="bi <?= $info['icone'] ?>"></i> <?= $info['label'] ?></span>
                            <i class="bi bi-chevron-down icone-expandir d-md-none text-secundario"></i>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell" style="width: 100px; min-width: 100px; max-width: 100px;">
                        <a href="<?= URL_BASE ?>/admin/pedido/detalhe.php?id=<?= (int) $pedido['IDPedido'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <tr id="detalhesPedido<?= $pedido['IDPedido'] ?>" class="d-none d-md-none">
                    <td colspan="4" class="bg-light-subtle">
                        <div class="d-flex justify-content-between small mb-2">
                            <span class="text-secundario">Cliente</span>
                            <span><?= htmlspecialchars($pedido['NomeCliente']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between small mb-3">
                            <span class="text-secundario">Data</span>
                            <span><?= date('d/m/Y H:i', strtotime($pedido['MomentoCriacao'])) ?></span>
                        </div>
                        <a href="<?= URL_BASE ?>/admin/pedido/detalhe.php?id=<?= (int) $pedido['IDPedido'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
                            <i class="bi bi-eye"></i> Ver pedido
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
