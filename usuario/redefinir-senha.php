<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
garantirTabelaCliente();

global $pdo;

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$erro = null;
$sucesso = null;

$stmt = $pdo->prepare("SELECT IDCliente FROM Cliente WHERE TokenRecuperacao = :token AND DataExpiracaoToken > NOW()");
$stmt->execute(['token' => $token]);
$idCliente = $stmt->fetchColumn();

if (!$idCliente) {
    $erro = 'Link inválido ou expirado. Solicite a recuperação novamente.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha = $_POST['senha'] ?? '';
    if (strlen($senha) < 8) {
        $erro = 'A senha precisa ter pelo menos 8 caracteres.';
    } else {
        $stmt = $pdo->prepare("UPDATE Cliente SET Senha = :senha, TokenRecuperacao = NULL, DataExpiracaoToken = NULL WHERE IDCliente = :id");
        $stmt->execute(['senha' => password_hash($senha, PASSWORD_DEFAULT), 'id' => $idCliente]);
        $sucesso = true;
    }
}

require __DIR__ . '/../geral/header.php';
?>
<div class="row">
    <div class="col-md-5 mx-auto">
        <div class="card p-4">
            <h1 class="h4 mb-3">Redefinir senha</h1>
            <?php if ($sucesso): ?>
                <div class="alert alert-success">Senha redefinida com sucesso.</div>
                <a href="<?= URL_BASE ?>/usuario/login.php" class="btn btn-marca rounded-pill w-100">Ir para o login</a>
            <?php elseif ($erro): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="mb-3">
                        <label class="form-label">Nova senha</label>
                        <input type="password" name="senha" class="form-control" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-marca rounded-pill w-100">Salvar nova senha</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../geral/footer.php'; ?>
