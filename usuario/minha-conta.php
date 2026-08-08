<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
exigirLoginCliente();
garantirTabelaUsuario();

global $pdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'atualizar_perfil') {
        $nome = trim($_POST['nome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');

        if ($nome === '') {
            header('Location: ' . URL_BASE . '/usuario/minha-conta.php?erro=perfil');
            exit;
        }

        $stmt = $pdo->prepare("UPDATE Usuario SET Nome = :nome, Telefone = :telefone WHERE IDUsuario = :id");
        $stmt->execute([
            'nome' => $nome,
            'telefone' => $telefone !== '' ? $telefone : null,
            'id' => $_SESSION['usuario_id'],
        ]);
        $_SESSION['usuario_nome'] = $nome;
        header('Location: ' . URL_BASE . '/usuario/minha-conta.php?ok=perfil');
        exit;
    }

    if ($action === 'atualizar_senha') {
        $senhaAtual = $_POST['senha_atual'] ?? '';
        $novaSenha = $_POST['nova_senha'] ?? '';
        $confirmarSenha = $_POST['confirmar_senha'] ?? '';

        $stmt = $pdo->prepare("SELECT Senha FROM Usuario WHERE IDUsuario = :id");
        $stmt->execute(['id' => $_SESSION['usuario_id']]);
        $senhaAtualHash = $stmt->fetchColumn();

        if (!password_verify($senhaAtual, $senhaAtualHash)) {
            header('Location: ' . URL_BASE . '/usuario/minha-conta.php?erro=senha_atual');
            exit;
        }
        if (strlen($novaSenha) < 4) {
            header('Location: ' . URL_BASE . '/usuario/minha-conta.php?erro=senha_curta');
            exit;
        }
        if ($novaSenha !== $confirmarSenha) {
            header('Location: ' . URL_BASE . '/usuario/minha-conta.php?erro=senha_confere');
            exit;
        }

        $stmt = $pdo->prepare("UPDATE Usuario SET Senha = :senha WHERE IDUsuario = :id");
        $stmt->execute([
            'senha' => password_hash($novaSenha, PASSWORD_DEFAULT),
            'id' => $_SESSION['usuario_id'],
        ]);
        header('Location: ' . URL_BASE . '/usuario/minha-conta.php?ok=senha');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM Usuario WHERE IDUsuario = :id");
$stmt->execute(['id' => $_SESSION['usuario_id']]);
$usuario = $stmt->fetch();

$sucessoPerfil = ($_GET['ok'] ?? '') === 'perfil' ? 'Perfil atualizado com sucesso.' : null;
$sucessoSenha = ($_GET['ok'] ?? '') === 'senha' ? 'Senha atualizada com sucesso.' : null;
$erroPerfil = ($_GET['erro'] ?? '') === 'perfil' ? 'Preencha o nome.' : null;
$errosSenhaMap = [
    'senha_atual' => 'Senha atual incorreta.',
    'senha_curta' => 'A nova senha precisa ter pelo menos 4 caracteres.',
    'senha_confere' => 'As senhas não conferem.',
];
$erroSenha = $errosSenhaMap[$_GET['erro'] ?? ''] ?? null;

require __DIR__ . '/../geral/header.php';
?>
<h1 class="h4 mb-4">Minha conta</h1>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card p-4 h-100">
            <h2 class="h5 mb-3">Perfil</h2>
            <?php if ($sucessoPerfil): ?><div class="alert alert-success"><?= $sucessoPerfil ?></div><?php endif; ?>
            <?php if ($erroPerfil): ?><div class="alert alert-danger"><?= $erroPerfil ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="atualizar_perfil">
                <div class="mb-3">
                    <label class="form-label">E-mail de acesso</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($usuario['Email']) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nome completo</label>
                    <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($usuario['Nome']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Telefone</label>
                    <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($usuario['Telefone'] ?? '') ?>" placeholder="não informado">
                </div>
                <button type="submit" class="btn btn-marca rounded-pill">Salvar alterações</button>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-4 h-100">
            <h2 class="h5 mb-3">Segurança</h2>
            <?php if ($sucessoSenha): ?><div class="alert alert-success"><?= $sucessoSenha ?></div><?php endif; ?>
            <?php if ($erroSenha): ?><div class="alert alert-danger"><?= $erroSenha ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="atualizar_senha">
                <div class="mb-3">
                    <label class="form-label">Senha atual</label>
                    <div class="tem-toggle-senha">
                        <input type="password" name="senha_atual" id="senhaAtual" class="form-control" placeholder="Digite sua senha atual" required>
                        <button type="button" class="btn-toggle-senha" data-alvo="senhaAtual" aria-label="Mostrar senha">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nova senha</label>
                    <div class="tem-toggle-senha">
                        <input type="password" name="nova_senha" id="novaSenhaConta" class="form-control" placeholder="Mín. 4 caracteres" minlength="4" required>
                        <button type="button" class="btn-toggle-senha" data-alvo="novaSenhaConta" aria-label="Mostrar senha">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmar nova senha</label>
                    <div class="tem-toggle-senha">
                        <input type="password" name="confirmar_senha" id="confirmarSenhaConta" class="form-control" placeholder="Repita a nova senha" minlength="4" required>
                        <button type="button" class="btn-toggle-senha" data-alvo="confirmarSenhaConta" aria-label="Mostrar senha">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-marca rounded-pill">Atualizar senha</button>
            </form>
        </div>
    </div>
</div>
<div class="text-secundario small mt-4">
    <i class="bi bi-clock-history"></i> Histórico de pedidos — em breve.
    &nbsp;&nbsp;<i class="bi bi-geo-alt"></i> Endereços salvos — em breve.
</div>
<?php require __DIR__ . '/../geral/footer.php'; ?>
