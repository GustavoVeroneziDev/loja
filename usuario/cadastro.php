<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
garantirTabelaUsuario();
garantirTabelaCategoria();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaItemCarrinho();

$voltarPara = caminhoSeguro($_GET['voltar_para'] ?? $_POST['voltar_para'] ?? null);

if (adminLogado()) {
    header('Location: ' . ($voltarPara ?? (URL_BASE . '/admin/index.php')));
    exit;
}
if (clienteLogado()) {
    header('Location: ' . ($voltarPara ?? (URL_BASE . '/usuario/minha-conta.php')));
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
    $aceitouTermos = isset($_POST['aceite_termos']);

    // Telefone é opcional, mas se veio preenchido precisa ter DDD + número de verdade (10 ou 11
    // dígitos) — reformata a partir dos dígitos em vez de confiar na máscara do JS.
    $telefoneNormalizado = $telefone !== '' ? normalizarTelefone($telefone) : null;

    if ($nome === '' || $email === '' || strlen($senha) < 4) {
        $erroCadastro = 'Preencha nome, e-mail e uma senha com pelo menos 4 caracteres.';
    } elseif ($telefone !== '' && $telefoneNormalizado === null) {
        $erroCadastro = 'Telefone inválido — informe DDD + número.';
    } elseif (!$aceitouTermos) {
        $erroCadastro = 'Você precisa ler e concordar com os termos de uso pra criar uma conta.';
    } else {
        $stmt = $pdo->prepare("SELECT IDUsuario FROM Usuario WHERE Email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetchColumn()) {
            $erroCadastro = 'Já existe uma conta com esse e-mail.';
        } else {
            $idUsuario = gerarUuid();
            // Cadastro público sempre cria cliente — virar admin só acontece manual, dentro do painel.
            $stmt = $pdo->prepare("INSERT INTO Usuario (IDUsuario, Nome, Email, Senha, Telefone, TipoUsuario, MomentoAceiteTermos) VALUES (:id, :nome, :email, :senha, :telefone, 'cliente', NOW())");
            $stmt->execute([
                'id' => $idUsuario,
                'nome' => $nome,
                'email' => $email,
                'senha' => password_hash($senha, PASSWORD_DEFAULT),
                'telefone' => $telefoneNormalizado,
            ]);
            $_SESSION['usuario_id'] = $idUsuario;
            $_SESSION['usuario_nome'] = $nome;
            $_SESSION['usuario_tipo'] = 'cliente';
            if (isset($_POST['lembrar'])) {
                ativarLoginLembrado($idUsuario);
            }
            mesclarCarrinhoVisitante();
            header('Location: ' . ($voltarPara ?? (URL_BASE . '/usuario/minha-conta.php')));
            exit;
        }
    }
}

$modoInicial = 'cadastro';
require __DIR__ . '/../geral/header.php';
require __DIR__ . '/_form-auth.php';
require __DIR__ . '/../geral/footer.php';
