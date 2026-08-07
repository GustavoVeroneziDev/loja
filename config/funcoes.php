<?php

function gerarUuid() {
    $dados = random_bytes(16);
    $dados[6] = chr(ord($dados[6]) & 0x0f | 0x40);
    $dados[8] = chr(ord($dados[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($dados), 4));
}

function formatarPreco($valor) {
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

// Imagem/upload/logo são salvos no banco como caminho relativo à raiz do projeto (sem URL_BASE),
// pra não ficarem presos ao nome da subpasta do deploy atual — URL_BASE só entra na hora de exibir.
function urlAsset($caminhoRelativo) {
    return URL_BASE . $caminhoRelativo;
}

// config/versao.php é gerado do zero a cada deploy (GitHub Actions), nunca commitado — em dev local
// ele não existe, por isso o fallback abaixo.
function obterVersaoSistema() {
    $arquivoVersao = __DIR__ . '/versao.php';
    if (!defined('VERSAO_SISTEMA') && file_exists($arquivoVersao)) {
        require $arquivoVersao;
    }
    return defined('VERSAO_SISTEMA') ? VERSAO_SISTEMA : '0.0.0-dev';
}

// ---------------------------------------------------------------------
// Auto-migração de schema
// ---------------------------------------------------------------------

function garantirTabelaAdmin() {
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS Admin (
        IDAdmin CHAR(36) PRIMARY KEY,
        Nome VARCHAR(150) NOT NULL,
        Email VARCHAR(190) NOT NULL UNIQUE,
        Senha VARCHAR(255) NOT NULL,
        MomentoCriacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function garantirTabelaCliente() {
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS Cliente (
        IDCliente CHAR(36) PRIMARY KEY,
        Nome VARCHAR(150) NOT NULL,
        Email VARCHAR(190) NOT NULL UNIQUE,
        Senha VARCHAR(255) NOT NULL,
        Telefone VARCHAR(20) NULL,
        TokenRecuperacao VARCHAR(64) NULL,
        DataExpiracaoToken DATETIME NULL,
        MomentoCadastro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function garantirTabelaCategoria() {
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS Categoria (
        IDCategoria CHAR(36) PRIMARY KEY,
        Nome VARCHAR(150) NOT NULL,
        FKCategoriaPai CHAR(36) NULL,
        Ordem INT NOT NULL DEFAULT 0,
        FOREIGN KEY (FKCategoriaPai) REFERENCES Categoria(IDCategoria) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function garantirTabelaProduto() {
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS Produto (
        IDProduto CHAR(36) PRIMARY KEY,
        Nome VARCHAR(200) NOT NULL,
        Descricao TEXT NULL,
        FKCategoria CHAR(36) NULL,
        Ativo TINYINT(1) NOT NULL DEFAULT 1,
        MomentoCriacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (FKCategoria) REFERENCES Categoria(IDCategoria) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function garantirTabelaVariacaoProduto() {
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS VariacaoProduto (
        IDVariacao CHAR(36) PRIMARY KEY,
        FKProduto CHAR(36) NOT NULL,
        Atributo VARCHAR(150) NULL,
        SKU VARCHAR(60) NOT NULL UNIQUE,
        Preco DECIMAL(10,2) NOT NULL,
        Estoque INT NOT NULL DEFAULT 0,
        FOREIGN KEY (FKProduto) REFERENCES Produto(IDProduto) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function garantirTabelaImagemProduto() {
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS ImagemProduto (
        IDImagem CHAR(36) PRIMARY KEY,
        FKProduto CHAR(36) NOT NULL,
        Url VARCHAR(255) NOT NULL,
        Ordem INT NOT NULL DEFAULT 0,
        FOREIGN KEY (FKProduto) REFERENCES Produto(IDProduto) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function garantirTabelaConfiguracaoLoja() {
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS ConfiguracaoLoja (
        Chave VARCHAR(100) PRIMARY KEY,
        Valor TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ---------------------------------------------------------------------
// ConfiguracaoLoja (a "pele" — nome, cores de marca, textos institucionais)
// ---------------------------------------------------------------------

function obterConfiguracaoLoja($chave, $default = null) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT Valor FROM ConfiguracaoLoja WHERE Chave = :chave");
    $stmt->execute(['chave' => $chave]);
    $valor = $stmt->fetchColumn();
    return $valor !== false ? $valor : $default;
}

function salvarConfiguracaoLoja($chave, $valor) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT Chave FROM ConfiguracaoLoja WHERE Chave = :chave");
    $stmt->execute(['chave' => $chave]);
    if ($stmt->fetchColumn() !== false) {
        $stmt = $pdo->prepare("UPDATE ConfiguracaoLoja SET Valor = :valor WHERE Chave = :chave");
    } else {
        $stmt = $pdo->prepare("INSERT INTO ConfiguracaoLoja (Chave, Valor) VALUES (:chave, :valor)");
    }
    $stmt->execute(['chave' => $chave, 'valor' => $valor]);
}

// Defaults só entram se a chave ainda não existir — nunca sobrescreve o que o cliente já customizou.
function garantirConfiguracaoLojaPadrao() {
    $padroes = [
        'nome_loja' => 'Minha Loja',
        'cor_primaria' => '#e08a3c',
        'cor_secundaria' => '#c08552',
        'logo_url' => '/geral/img/logo-placeholder.svg',
        'texto_sobre' => 'Conte aqui a história da sua loja.',
        'texto_politica_troca' => 'Descreva aqui a política de trocas e devoluções.',
        'texto_contato' => 'contato@minhaloja.com.br',
    ];
    foreach ($padroes as $chave => $valorPadrao) {
        if (obterConfiguracaoLoja($chave) === null) {
            salvarConfiguracaoLoja($chave, $valorPadrao);
        }
    }
}

// ---------------------------------------------------------------------
// Autenticação — sessões separadas pra cliente (loja) e admin (painel)
// ---------------------------------------------------------------------

function clienteLogado() {
    return !empty($_SESSION['cliente_id']);
}

function adminLogado() {
    return !empty($_SESSION['admin_id']);
}

function exigirLoginCliente() {
    if (!clienteLogado()) {
        header('Location: ' . URL_BASE . '/usuario/login.php');
        exit;
    }
}

function exigirLoginAdmin() {
    if (!adminLogado()) {
        header('Location: ' . URL_BASE . '/admin/login.php');
        exit;
    }
}

// ---------------------------------------------------------------------
// Categoria
// ---------------------------------------------------------------------

function obterCategorias() {
    global $pdo;
    return $pdo->query("SELECT * FROM Categoria ORDER BY Ordem, Nome")->fetchAll();
}

// Retorna categorias já indentadas por nível de hierarquia, prontas pra popular um <select>.
function obterCategoriasArvore() {
    $todas = obterCategorias();
    $porPai = [];
    foreach ($todas as $categoria) {
        $porPai[$categoria['FKCategoriaPai'] ?? ''][] = $categoria;
    }

    $resultado = [];
    $montar = function ($paiId, $nivel) use (&$montar, &$porPai, &$resultado) {
        foreach ($porPai[$paiId ?? ''] ?? [] as $categoria) {
            $categoria['Nivel'] = $nivel;
            $resultado[] = $categoria;
            $montar($categoria['IDCategoria'], $nivel + 1);
        }
    };
    $montar('', 0);
    return $resultado;
}

// ---------------------------------------------------------------------
// Produto / Variação / Imagem
// ---------------------------------------------------------------------

// Lista produtos ativos com preço mínimo entre variações e a imagem de menor Ordem.
function obterProdutosAtivos($idCategoria = null) {
    global $pdo;
    $sql = "SELECT p.*,
                   (SELECT MIN(Preco) FROM VariacaoProduto WHERE FKProduto = p.IDProduto) AS PrecoMinimo,
                   (SELECT Url FROM ImagemProduto WHERE FKProduto = p.IDProduto ORDER BY Ordem LIMIT 1) AS ImagemCapa
            FROM Produto p
            WHERE p.Ativo = 1";
    $params = [];
    if ($idCategoria !== null) {
        $sql .= " AND p.FKCategoria = :idCategoria";
        $params['idCategoria'] = $idCategoria;
    }
    $sql .= " ORDER BY p.MomentoCriacao DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function obterProdutoPorId($idProduto) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM Produto WHERE IDProduto = :id");
    $stmt->execute(['id' => $idProduto]);
    $produto = $stmt->fetch();
    return $produto ?: null;
}

function obterVariacoesPorProduto($idProduto) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM VariacaoProduto WHERE FKProduto = :id ORDER BY Preco");
    $stmt->execute(['id' => $idProduto]);
    return $stmt->fetchAll();
}

function obterImagensPorProduto($idProduto) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM ImagemProduto WHERE FKProduto = :id ORDER BY Ordem");
    $stmt->execute(['id' => $idProduto]);
    return $stmt->fetchAll();
}

// Move o upload pra geral/img/{subpasta}/{uuid}.ext e devolve a URL relativa, ou null se inválido.
function uploadImagem($arquivo, $subpasta) {
    if (empty($arquivo['tmp_name']) || $arquivo['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
    $tamanhoMaximo = 5 * 1024 * 1024;

    if ($arquivo['size'] > $tamanhoMaximo) {
        return null;
    }

    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, $extensoesPermitidas, true)) {
        return null;
    }

    $pastaDestino = __DIR__ . '/../geral/img/' . $subpasta;
    if (!is_dir($pastaDestino)) {
        mkdir($pastaDestino, 0755, true);
    }

    $nomeArquivo = gerarUuid() . '.' . $extensao;
    if (!move_uploaded_file($arquivo['tmp_name'], $pastaDestino . '/' . $nomeArquivo)) {
        return null;
    }

    return '/geral/img/' . $subpasta . '/' . $nomeArquivo;
}
