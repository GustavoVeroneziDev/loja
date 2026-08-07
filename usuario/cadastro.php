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

$erroLogin = null;
$erroCadastro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($nome === '' || $email === '' || strlen($senha) < 8) {
        $erroCadastro = 'Preencha nome, e-mail e uma senha com pelo menos 8 caracteres.';
    } else {
        $stmt = $pdo->prepare("SELECT IDCliente FROM Cliente WHERE Email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetchColumn()) {
            $erroCadastro = 'Já existe uma conta com esse e-mail.';
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

$modoInicial = 'cadastro';
require __DIR__ . '/../geral/header.php';
require __DIR__ . '/_form-auth.php';
require __DIR__ . '/../geral/footer.php';
