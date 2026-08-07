<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
garantirTabelaCliente();

if (clienteLogado()) {
    header('Location: ' . URL_BASE . '/usuario/minha-conta.php');
    exit;
}

global $pdo;

$erro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($nome === '' || $email === '' || strlen($senha) < 8) {
        $erro = 'Preencha nome, e-mail e uma senha com pelo menos 8 caracteres.';
    } else {
        $stmt = $pdo->prepare("SELECT IDCliente FROM Cliente WHERE Email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetchColumn()) {
            $erro = 'Já existe uma conta com esse e-mail.';
        } else {
            $idCliente = gerarUuid();
            $stmt = $pdo->prepare("INSERT INTO Cliente (IDCliente, Nome, Email, Senha, Telefone) VALUES (:id, :nome, :email, :senha, :telefone)");
            $stmt->execute([
                'id' => $idCliente,
                'nome' => $nome,
                'email' => $email,
                'senha' => password_hash($senha, PASSWORD_DEFAULT),
                'telefone' => $telefone !== '' ? $telefone : null,
            ]);
            $_SESSION['cliente_id'] = $idCliente;
            $_SESSION['cliente_nome'] = $nome;
            header('Location: ' . URL_BASE . '/usuario/minha-conta.php');
            exit;
        }
    }
}

require __DIR__ . '/../geral/header.php';
?>
<div class="row">
    <div class="col-md-5 mx-auto">
        <div class="card p-4">
            <h1 class="h4 mb-3">Criar conta</h1>
            <?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Telefone</label>
                    <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Senha</label>
                    <input type="password" name="senha" class="form-control" minlength="8" required>
                </div>
                <button type="submit" class="btn btn-marca rounded-pill w-100">Criar conta</button>
            </form>
            <p class="text-secundario small mt-3 mb-0">Já tem conta? <a href="<?= URL_BASE ?>/usuario/login.php" class="link-marca">Entrar</a></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../geral/footer.php'; ?>
