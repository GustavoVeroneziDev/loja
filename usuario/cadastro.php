<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
garantirTabelaUsuario();
garantirTabelaCategoria();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaItemCarrinho();

if (adminLogado()) {
    header('Location: ' . URL_BASE . '/admin/index.php');
    exit;
}
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

    if ($nome === '' || $email === '' || strlen($senha) < 4) {
        $erroCadastro = 'Preencha nome, e-mail e uma senha com pelo menos 4 caracteres.';
    } else {
        $stmt = $pdo->prepare("SELECT IDUsuario FROM Usuario WHERE Email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetchColumn()) {
            $erroCadastro = 'Já existe uma conta com esse e-mail.';
        } else {
            $idUsuario = gerarUuid();
            // Cadastro público sempre cria cliente — virar admin só acontece manual, dentro do painel.
            $stmt = $pdo->prepare("INSERT INTO Usuario (IDUsuario, Nome, Email, Senha, Telefone, TipoUsuario) VALUES (:id, :nome, :email, :senha, :telefone, 'cliente')");
            $stmt->execute([
                'id' => $idUsuario,
                'nome' => $nome,
                'email' => $email,
                'senha' => password_hash($senha, PASSWORD_DEFAULT),
                'telefone' => $telefone !== '' ? $telefone : null,
            ]);
            $_SESSION['usuario_id'] = $idUsuario;
            $_SESSION['usuario_nome'] = $nome;
            $_SESSION['usuario_tipo'] = 'cliente';
            mesclarCarrinhoVisitante();
            header('Location: ' . URL_BASE . '/usuario/minha-conta.php');
            exit;
        }
    }
}

$modoInicial = 'cadastro';
require __DIR__ . '/../geral/header.php';
require __DIR__ . '/_form-auth.php';
require __DIR__ . '/../geral/footer.php';
