<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
garantirTabelaCliente();

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
    $stmt = $pdo->prepare("SELECT * FROM Cliente WHERE Email = :email");
    $stmt->execute(['email' => $email]);
    $cliente = $stmt->fetch();

    if ($cliente && password_verify($senha, $cliente['Senha'])) {
        $_SESSION['cliente_id'] = $cliente['IDCliente'];
        $_SESSION['cliente_nome'] = $cliente['Nome'];
        header('Location: ' . URL_BASE . '/usuario/minha-conta.php');
        exit;
    }
    $erroLogin = 'E-mail ou senha inválidos.';
}

$modoInicial = 'login';
require __DIR__ . '/../geral/header.php';
require __DIR__ . '/_form-auth.php';
require __DIR__ . '/../geral/footer.php';
