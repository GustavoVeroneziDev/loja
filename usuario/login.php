<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
garantirTabelaUsuario();

if (adminLogado()) {
    header('Location: ' . URL_BASE . '/admin/index.php');
    exit;
}
if (clienteLogado()) {
    header('Location: ' . URL_BASE . '/usuario/minha-conta.php');
    exit;
}

$erroLogin = null;
$erroCadastro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM Usuario WHERE Email = :email");
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario['Senha'])) {
        $_SESSION['usuario_id'] = $usuario['IDUsuario'];
        $_SESSION['usuario_nome'] = $usuario['Nome'];
        $_SESSION['usuario_tipo'] = $usuario['TipoUsuario'];
        header('Location: ' . URL_BASE . ($usuario['TipoUsuario'] === 'admin' ? '/admin/index.php' : '/usuario/minha-conta.php'));
        exit;
    }
    $erroLogin = 'E-mail ou senha inválidos.';
}

$modoInicial = 'login';
require __DIR__ . '/../geral/header.php';
require __DIR__ . '/_form-auth.php';
require __DIR__ . '/../geral/footer.php';
