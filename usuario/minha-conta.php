<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
exigirLoginCliente();
garantirTabelaCliente();

global $pdo;
$stmt = $pdo->prepare("SELECT * FROM Cliente WHERE IDCliente = :id");
$stmt->execute(['id' => $_SESSION['cliente_id']]);
$cliente = $stmt->fetch();

require __DIR__ . '/../geral/header.php';
?>
<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card p-4">
            <h1 class="h4 mb-3">Minha conta</h1>
            <p class="mb-1"><strong>Nome:</strong> <?= htmlspecialchars($cliente['Nome']) ?></p>
            <p class="mb-1"><strong>E-mail:</strong> <?= htmlspecialchars($cliente['Email']) ?></p>
            <p class="mb-3"><strong>Telefone:</strong> <?= htmlspecialchars($cliente['Telefone'] ?? 'não informado') ?></p>
            <hr>
            <p class="text-secundario small mb-1"><i class="bi bi-clock-history"></i> Histórico de pedidos — em breve.</p>
            <p class="text-secundario small mb-3"><i class="bi bi-geo-alt"></i> Endereços salvos — em breve.</p>
            <a href="<?= URL_BASE ?>/usuario/logout.php" class="btn btn-outline-secondary rounded-pill">Sair</a>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../geral/footer.php'; ?>
