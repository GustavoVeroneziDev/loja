<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
exigirLoginCliente();
garantirTabelaUsuario();
garantirTabelaPedido();

$pedidos = obterPedidosPorUsuario($_SESSION['usuario_id']);

require __DIR__ . '/../geral/header.php';
?>
<h1 class="h3 mb-4 titulo-estilizado">Meus pedidos</h1>

<?php if (!$pedidos): ?>
    <div class="card p-5 text-center text-secundario">
        <p class="mb-3">Você ainda não fez nenhum pedido.</p>
        <a href="<?= URL_BASE ?>/index.php" class="btn btn-marca rounded-pill">Ver produtos</a>
    </div>
<?php else: ?>
    <div class="d-flex flex-column gap-3">
        <?php foreach ($pedidos as $pedido): $info = statusPedidoInfo($pedido['Status']); ?>
            <a href="<?= URL_BASE ?>/usuario/pedido.php?id=<?= (int) $pedido['IDPedido'] ?>" class="card p-3 text-reset text-decoration-none">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <span class="fw-semibold">Pedido #<?= str_pad($pedido['IDPedido'], 5, '0', STR_PAD_LEFT) ?></span>
                        <div class="text-secundario small"><?= date('d/m/Y \à\s H:i', strtotime($pedido['MomentoCriacao'])) ?></div>
                    </div>
                    <span class="<?= $info['badge'] ?> px-2 py-1"><i class="bi <?= $info['icone'] ?>"></i> <?= $info['label'] ?></span>
                    <span class="fw-semibold"><?= formatarPreco($pedido['ValorTotal']) ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../geral/footer.php'; ?>
