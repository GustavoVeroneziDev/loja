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

// Pra saudação/menu de usuário — nome completo não cabe em navbar.
function primeiroNome($nomeCompleto) {
    return trim(explode(' ', trim($nomeCompleto))[0]);
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

// Cliente e admin são a mesma entidade (Usuario) com TipoUsuario diferente — login único,
// o painel admin não é "outro sistema", é só o que abre quando TipoUsuario = 'admin'.
function garantirTabelaUsuario() {
    static $jaVerificado = false;
    if ($jaVerificado) {
        return;
    }
    global $pdo;

    $usuarioJaExiste = (bool) $pdo->query("SHOW TABLES LIKE 'Usuario'")->fetchColumn();
    $clienteAntigoExiste = !$usuarioJaExiste && (bool) $pdo->query("SHOW TABLES LIKE 'Cliente'")->fetchColumn();
    if ($clienteAntigoExiste) {
        // RENAME TABLE só renomeia a tabela — a coluna IDCliente continua com o nome antigo,
        // precisa renomear ela também pra bater com o resto do código (IDUsuario).
        $pdo->exec("RENAME TABLE Cliente TO Usuario");
        $pdo->exec("ALTER TABLE Usuario CHANGE IDCliente IDUsuario CHAR(36)");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS Usuario (
        IDUsuario CHAR(36) PRIMARY KEY,
        Nome VARCHAR(150) NOT NULL,
        Email VARCHAR(190) NOT NULL UNIQUE,
        Senha VARCHAR(255) NOT NULL,
        Telefone VARCHAR(20) NULL,
        TipoUsuario ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
        TokenRecuperacao VARCHAR(64) NULL,
        DataExpiracaoToken DATETIME NULL,
        MomentoCadastro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // TipoUsuario é coluna nova pra quem já tinha Cliente antes desta versão — default reproduz o
    // comportamento antigo (toda linha existente era cliente comum, nunca admin).
    $colunaTipoExiste = (bool) $pdo->query("SHOW COLUMNS FROM Usuario LIKE 'TipoUsuario'")->fetchColumn();
    if (!$colunaTipoExiste) {
        $pdo->exec("ALTER TABLE Usuario ADD COLUMN TipoUsuario ENUM('cliente','admin') NOT NULL DEFAULT 'cliente' AFTER Telefone");
    }

    // Migra quem tava na tabela Admin separada (versão anterior) pra dentro de Usuario, e descarta a tabela velha.
    $tabelaAdminExiste = (bool) $pdo->query("SHOW TABLES LIKE 'Admin'")->fetchColumn();
    if ($tabelaAdminExiste) {
        $admins = $pdo->query("SELECT * FROM Admin")->fetchAll();
        foreach ($admins as $admin) {
            $stmt = $pdo->prepare("SELECT IDUsuario FROM Usuario WHERE Email = :email");
            $stmt->execute(['email' => $admin['Email']]);
            if ($stmt->fetchColumn()) {
                $pdo->prepare("UPDATE Usuario SET TipoUsuario = 'admin' WHERE Email = :email")->execute(['email' => $admin['Email']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO Usuario (IDUsuario, Nome, Email, Senha, TipoUsuario) VALUES (:id, :nome, :email, :senha, 'admin')");
                $stmt->execute([
                    'id' => $admin['IDAdmin'],
                    'nome' => $admin['Nome'],
                    'email' => $admin['Email'],
                    'senha' => $admin['Senha'],
                ]);
            }
        }
        $pdo->exec("DROP TABLE Admin");
    }
    $jaVerificado = true;
}

function garantirTabelaCategoria() {
    static $jaVerificado = false;
    if ($jaVerificado) {
        return;
    }
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS Categoria (
        IDCategoria CHAR(36) PRIMARY KEY,
        Nome VARCHAR(150) NOT NULL,
        FKCategoriaPai CHAR(36) NULL,
        Ordem INT NOT NULL DEFAULT 0,
        FOREIGN KEY (FKCategoriaPai) REFERENCES Categoria(IDCategoria) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $jaVerificado = true;
}

function garantirTabelaProduto() {
    static $jaVerificado = false;
    if ($jaVerificado) {
        return;
    }
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

    // Nome dos até 2 eixos de variação do produto (ex: "Cor", "Tamanho") — fica aqui e não em
    // VariacaoProduto porque tem que ser o mesmo rótulo pra toda variação do mesmo produto.
    $temNomeAtributo1 = (bool) $pdo->query("SHOW COLUMNS FROM Produto LIKE 'NomeAtributo1'")->fetchColumn();
    if (!$temNomeAtributo1) {
        $pdo->exec("ALTER TABLE Produto ADD COLUMN NomeAtributo1 VARCHAR(50) NULL AFTER Descricao");
    }
    $temNomeAtributo2 = (bool) $pdo->query("SHOW COLUMNS FROM Produto LIKE 'NomeAtributo2'")->fetchColumn();
    if (!$temNomeAtributo2) {
        $pdo->exec("ALTER TABLE Produto ADD COLUMN NomeAtributo2 VARCHAR(50) NULL AFTER NomeAtributo1");
    }

    $jaVerificado = true;
}

function garantirTabelaVariacaoProduto() {
    static $jaVerificado = false;
    if ($jaVerificado) {
        return;
    }
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS VariacaoProduto (
        IDVariacao CHAR(36) PRIMARY KEY,
        FKProduto CHAR(36) NOT NULL,
        SKU VARCHAR(60) NOT NULL UNIQUE,
        Preco DECIMAL(10,2) NOT NULL,
        Estoque INT NOT NULL DEFAULT 0,
        FOREIGN KEY (FKProduto) REFERENCES Produto(IDProduto) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Valor de cada eixo pra essa variação específica (ex: "Azul", "40") — combinação livre, não
    // precisa existir toda combinação possível (pode ter Azul só no 40, sem ter Preto no 40).
    $temValorAtributo1 = (bool) $pdo->query("SHOW COLUMNS FROM VariacaoProduto LIKE 'ValorAtributo1'")->fetchColumn();
    if (!$temValorAtributo1) {
        $pdo->exec("ALTER TABLE VariacaoProduto ADD COLUMN ValorAtributo1 VARCHAR(100) NULL AFTER FKProduto");
    }
    $temValorAtributo2 = (bool) $pdo->query("SHOW COLUMNS FROM VariacaoProduto LIKE 'ValorAtributo2'")->fetchColumn();
    if (!$temValorAtributo2) {
        $pdo->exec("ALTER TABLE VariacaoProduto ADD COLUMN ValorAtributo2 VARCHAR(100) NULL AFTER ValorAtributo1");
    }

    // Coluna antiga (Atributo, texto livre único) — migra o valor pra ValorAtributo1 e marca o
    // produto com um rótulo genérico "Opção" (o admin renomeia pra "Cor"/"Tamanho"/etc depois).
    $temColunaAntiga = (bool) $pdo->query("SHOW COLUMNS FROM VariacaoProduto LIKE 'Atributo'")->fetchColumn();
    if ($temColunaAntiga) {
        $pdo->exec("UPDATE Produto p SET NomeAtributo1 = 'Opção'
            WHERE NomeAtributo1 IS NULL
              AND EXISTS (SELECT 1 FROM VariacaoProduto v WHERE v.FKProduto = p.IDProduto AND v.Atributo IS NOT NULL)");
        $pdo->exec("UPDATE VariacaoProduto SET ValorAtributo1 = Atributo WHERE Atributo IS NOT NULL AND ValorAtributo1 IS NULL");
        $pdo->exec("ALTER TABLE VariacaoProduto DROP COLUMN Atributo");
    }

    $jaVerificado = true;
}

// Texto compacto pra mostrar a variação escolhida (ex: "Azul · 40") — usado onde não dá pra
// mostrar os 2 seletores lado a lado (carrinho, resumo de pedido).
function descricaoVariacao($variacao) {
    $partes = array_filter([$variacao['ValorAtributo1'] ?? null, $variacao['ValorAtributo2'] ?? null], fn($v) => $v !== null && $v !== '');
    return $partes ? implode(' · ', $partes) : null;
}

function garantirTabelaImagemProduto() {
    static $jaVerificado = false;
    if ($jaVerificado) {
        return;
    }
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS ImagemProduto (
        IDImagem CHAR(36) PRIMARY KEY,
        FKProduto CHAR(36) NOT NULL,
        Url VARCHAR(255) NOT NULL,
        Ordem INT NOT NULL DEFAULT 0,
        FOREIGN KEY (FKProduto) REFERENCES Produto(IDProduto) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $jaVerificado = true;
}

// ---------------------------------------------------------------------
// Autenticação — uma sessão só (Usuario), o TipoUsuario decide o que a pessoa vê
// ---------------------------------------------------------------------

function clienteLogado() {
    return !empty($_SESSION['usuario_id']) && ($_SESSION['usuario_tipo'] ?? '') === 'cliente';
}

function adminLogado() {
    return !empty($_SESSION['usuario_id']) && ($_SESSION['usuario_tipo'] ?? '') === 'admin';
}

// Admin logado nunca vê tela de cliente — manda direto pro painel em vez de pra tela de login.
function exigirLoginCliente() {
    if (adminLogado()) {
        header('Location: ' . URL_BASE . '/admin/index.php');
        exit;
    }
    if (!clienteLogado()) {
        header('Location: ' . URL_BASE . '/usuario/login.php');
        exit;
    }
}

function exigirLoginAdmin() {
    if (!adminLogado()) {
        header('Location: ' . URL_BASE . '/usuario/login.php');
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
// TotalVariacoes/IDVariacaoUnica/EstoqueVariacaoUnica existem pra grade de produto poder
// adicionar direto ao carrinho quando só há 1 variação — se houver mais de uma, a grade manda
// pra página do produto em vez de adivinhar qual variação o cliente quer.
function obterProdutosAtivos($idCategoria = null, $busca = null) {
    global $pdo;
    $sql = "SELECT p.*,
                   (SELECT MIN(Preco) FROM VariacaoProduto WHERE FKProduto = p.IDProduto) AS PrecoMinimo,
                   (SELECT Url FROM ImagemProduto WHERE FKProduto = p.IDProduto ORDER BY Ordem LIMIT 1) AS ImagemCapa,
                   (SELECT COUNT(*) FROM VariacaoProduto WHERE FKProduto = p.IDProduto) AS TotalVariacoes,
                   (SELECT IDVariacao FROM VariacaoProduto WHERE FKProduto = p.IDProduto ORDER BY Preco LIMIT 1) AS IDVariacaoUnica,
                   (SELECT Estoque FROM VariacaoProduto WHERE FKProduto = p.IDProduto ORDER BY Preco LIMIT 1) AS EstoqueVariacaoUnica
            FROM Produto p
            WHERE p.Ativo = 1";
    $params = [];
    if ($idCategoria !== null) {
        $sql .= " AND p.FKCategoria = :idCategoria";
        $params['idCategoria'] = $idCategoria;
    }
    if ($busca !== null && $busca !== '') {
        $sql .= " AND (p.Nome LIKE :busca OR p.Descricao LIKE :busca)";
        $params['busca'] = '%' . $busca . '%';
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

    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'webp', 'ico', 'svg'];
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

// ---------------------------------------------------------------------
// Carrinho — visitante fica na sessão ($_SESSION['carrinho']), cliente logado persiste em
// ItemCarrinho. Ao logar, mescla o que tava na sessão pro banco (nunca perde escolha de visitante).
// ---------------------------------------------------------------------

function garantirTabelaItemCarrinho() {
    static $jaVerificado = false;
    if ($jaVerificado) {
        return;
    }
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS ItemCarrinho (
        IDItemCarrinho CHAR(36) PRIMARY KEY,
        FKUsuario CHAR(36) NOT NULL,
        FKVariacao CHAR(36) NOT NULL,
        Quantidade INT NOT NULL DEFAULT 1,
        MomentoAdicionado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_usuario_variacao (FKUsuario, FKVariacao),
        FOREIGN KEY (FKUsuario) REFERENCES Usuario(IDUsuario) ON DELETE CASCADE,
        FOREIGN KEY (FKVariacao) REFERENCES VariacaoProduto(IDVariacao) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $jaVerificado = true;
}

function adicionarItemCarrinho($idVariacao, $quantidade = 1) {
    $quantidade = max(1, (int) $quantidade);

    if (clienteLogado()) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT Quantidade FROM ItemCarrinho WHERE FKUsuario = :u AND FKVariacao = :v");
        $stmt->execute(['u' => $_SESSION['usuario_id'], 'v' => $idVariacao]);
        $atual = $stmt->fetchColumn();
        if ($atual !== false) {
            $pdo->prepare("UPDATE ItemCarrinho SET Quantidade = :q WHERE FKUsuario = :u AND FKVariacao = :v")
                ->execute(['q' => $atual + $quantidade, 'u' => $_SESSION['usuario_id'], 'v' => $idVariacao]);
        } else {
            $pdo->prepare("INSERT INTO ItemCarrinho (IDItemCarrinho, FKUsuario, FKVariacao, Quantidade) VALUES (:id, :u, :v, :q)")
                ->execute(['id' => gerarUuid(), 'u' => $_SESSION['usuario_id'], 'v' => $idVariacao, 'q' => $quantidade]);
        }
    } else {
        $_SESSION['carrinho'][$idVariacao] = ($_SESSION['carrinho'][$idVariacao] ?? 0) + $quantidade;
    }
}

// $quantidade <= 0 remove o item.
function atualizarQuantidadeCarrinho($idVariacao, $quantidade) {
    $quantidade = (int) $quantidade;

    if (clienteLogado()) {
        global $pdo;
        if ($quantidade <= 0) {
            $pdo->prepare("DELETE FROM ItemCarrinho WHERE FKUsuario = :u AND FKVariacao = :v")
                ->execute(['u' => $_SESSION['usuario_id'], 'v' => $idVariacao]);
        } else {
            $pdo->prepare("UPDATE ItemCarrinho SET Quantidade = :q WHERE FKUsuario = :u AND FKVariacao = :v")
                ->execute(['q' => $quantidade, 'u' => $_SESSION['usuario_id'], 'v' => $idVariacao]);
        }
    } elseif ($quantidade <= 0) {
        unset($_SESSION['carrinho'][$idVariacao]);
    } else {
        $_SESSION['carrinho'][$idVariacao] = $quantidade;
    }
}

function limparCarrinho() {
    if (clienteLogado()) {
        global $pdo;
        $pdo->prepare("DELETE FROM ItemCarrinho WHERE FKUsuario = :u")->execute(['u' => $_SESSION['usuario_id']]);
    } else {
        unset($_SESSION['carrinho']);
    }
}

// Devolve os itens do carrinho já com dado de produto/variação/imagem, prontos pra exibir.
function obterCarrinho() {
    global $pdo;

    if (clienteLogado()) {
        $stmt = $pdo->prepare("SELECT FKVariacao, Quantidade FROM ItemCarrinho WHERE FKUsuario = :u");
        $stmt->execute(['u' => $_SESSION['usuario_id']]);
        $bruto = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } else {
        $bruto = $_SESSION['carrinho'] ?? [];
    }

    $itens = [];
    foreach ($bruto as $idVariacao => $quantidade) {
        $stmt = $pdo->prepare("SELECT v.*, p.IDProduto, p.Nome AS NomeProduto,
                (SELECT Url FROM ImagemProduto WHERE FKProduto = p.IDProduto ORDER BY Ordem LIMIT 1) AS ImagemCapa
            FROM VariacaoProduto v
            JOIN Produto p ON p.IDProduto = v.FKProduto
            WHERE v.IDVariacao = :id");
        $stmt->execute(['id' => $idVariacao]);
        $variacao = $stmt->fetch();
        if (!$variacao) {
            continue; // variação foi excluída pelo admin depois de ter sido adicionada ao carrinho
        }
        $itens[] = [
            'IDVariacao' => $idVariacao,
            'Quantidade' => (int) $quantidade,
            'variacao' => $variacao,
            'subtotal' => $variacao['Preco'] * $quantidade,
        ];
    }
    return $itens;
}

function contarItensCarrinho() {
    if (clienteLogado()) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(Quantidade), 0) FROM ItemCarrinho WHERE FKUsuario = :u");
        $stmt->execute(['u' => $_SESSION['usuario_id']]);
        return (int) $stmt->fetchColumn();
    }
    return array_sum($_SESSION['carrinho'] ?? []);
}

// Chamado logo depois do login/cadastro dar certo — o que o visitante já tinha escolhido não pode se perder.
function mesclarCarrinhoVisitante() {
    if (empty($_SESSION['carrinho'])) {
        return;
    }
    foreach ($_SESSION['carrinho'] as $idVariacao => $quantidade) {
        adicionarItemCarrinho($idVariacao, $quantidade);
    }
    unset($_SESSION['carrinho']);
}

// ---------------------------------------------------------------------
// Favoritos — exige conta (não é por sessão de visitante, é lista salva de verdade)
// ---------------------------------------------------------------------

function garantirTabelaFavorito() {
    static $jaVerificado = false;
    if ($jaVerificado) {
        return;
    }
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS Favorito (
        IDFavorito CHAR(36) PRIMARY KEY,
        FKUsuario CHAR(36) NOT NULL,
        FKProduto CHAR(36) NOT NULL,
        MomentoAdicionado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_usuario_produto (FKUsuario, FKProduto),
        FOREIGN KEY (FKUsuario) REFERENCES Usuario(IDUsuario) ON DELETE CASCADE,
        FOREIGN KEY (FKProduto) REFERENCES Produto(IDProduto) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $jaVerificado = true;
}

function alternarFavorito($idProduto) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT IDFavorito FROM Favorito WHERE FKUsuario = :u AND FKProduto = :p");
    $stmt->execute(['u' => $_SESSION['usuario_id'], 'p' => $idProduto]);
    $existente = $stmt->fetchColumn();
    if ($existente) {
        $pdo->prepare("DELETE FROM Favorito WHERE IDFavorito = :id")->execute(['id' => $existente]);
    } else {
        $pdo->prepare("INSERT INTO Favorito (IDFavorito, FKUsuario, FKProduto) VALUES (:id, :u, :p)")
            ->execute(['id' => gerarUuid(), 'u' => $_SESSION['usuario_id'], 'p' => $idProduto]);
    }
}

function ehFavorito($idProduto) {
    if (!clienteLogado()) {
        return false;
    }
    global $pdo;
    $stmt = $pdo->prepare("SELECT 1 FROM Favorito WHERE FKUsuario = :u AND FKProduto = :p");
    $stmt->execute(['u' => $_SESSION['usuario_id'], 'p' => $idProduto]);
    return (bool) $stmt->fetchColumn();
}

function obterFavoritos() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT p.*, f.MomentoAdicionado,
            (SELECT MIN(Preco) FROM VariacaoProduto WHERE FKProduto = p.IDProduto) AS PrecoMinimo,
            (SELECT Url FROM ImagemProduto WHERE FKProduto = p.IDProduto ORDER BY Ordem LIMIT 1) AS ImagemCapa,
            (SELECT COUNT(*) FROM VariacaoProduto WHERE FKProduto = p.IDProduto) AS TotalVariacoes,
            (SELECT IDVariacao FROM VariacaoProduto WHERE FKProduto = p.IDProduto ORDER BY Preco LIMIT 1) AS IDVariacaoUnica,
            (SELECT Estoque FROM VariacaoProduto WHERE FKProduto = p.IDProduto ORDER BY Preco LIMIT 1) AS EstoqueVariacaoUnica
        FROM Favorito f
        JOIN Produto p ON p.IDProduto = f.FKProduto
        WHERE f.FKUsuario = :u
        ORDER BY f.MomentoAdicionado DESC");
    $stmt->execute(['u' => $_SESSION['usuario_id']]);
    return $stmt->fetchAll();
}
