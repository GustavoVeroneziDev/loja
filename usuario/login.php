<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
garantirTabelaCliente();

if (clienteLogado()) {
    header('Location: ' . URL_BASE . '/usuario/minha-conta.php');
    exit;
}

$erro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM Cliente WHERE Email = :email");
    $stmt->execute(['email' => $email]);
    $cliente = $stmt->fetch();

    if ($cliente && password_verify($senha, $cliente['Senha'])) {
        $_SESSION['cliente_id'] = $cliente['IDCliente'];
        $_SESSION['cliente_nome'] = $cliente['Nome'];
        header('Location: ' . URL_BASE . '/usuario/minha-conta.php');
        exit;
    }
    $erro = 'E-mail ou senha inválidos.';
}

require __DIR__ . '/../geral/header.php';
?>
<div class="row">
    <div class="col-md-5 mx-auto">
        <div class="card p-4">
            <h1 class="h4 mb-3">Entrar</h1>
            <?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Senha</label>
                    <input type="password" name="senha" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-marca rounded-pill w-100">Entrar</button>
            </form>
            <p class="text-secundario small mt-3 mb-1"><a href="<?= URL_BASE ?>/usuario/recuperar-senha.php" class="link-marca">Esqueci minha senha</a></p>
            <p class="text-secundario small mb-0">Ainda não tem conta? <a href="<?= URL_BASE ?>/usuario/cadastro.php" class="link-marca">Criar conta</a></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../geral/footer.php'; ?>
