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

// Valida e reformata telefone BR (fixo 10 dígitos ou celular 11, DDD incluso) direto dos
// dígitos — nunca confia só na máscara do JS, o servidor reformata de novo do zero. Retorna
// null se não tiver 10 nem 11 dígitos (chamador decide se telefone vazio é erro ou não).
function normalizarTelefone($telefone) {
    $digitos = preg_replace('/\D/', '', $telefone ?? '');
    if (strlen($digitos) === 11) {
        return '(' . substr($digitos, 0, 2) . ') ' . substr($digitos, 2, 5) . '-' . substr($digitos, 7);
    }
    if (strlen($digitos) === 10) {
        return '(' . substr($digitos, 0, 2) . ') ' . substr($digitos, 2, 4) . '-' . substr($digitos, 6);
    }
    return null;
}

// Dígito verificador de CPF — só confirma que o número "bate" matematicamente, não que existe de
// verdade na Receita Federal (isso exigiria uma consulta paga, não vale a pena por enquanto).
function cpfValido($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf ?? '');
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false; // 11 dígitos iguais passa na conta mas nunca é CPF de verdade
    }
    for ($posicao = 9; $posicao <= 10; $posicao++) {
        $soma = 0;
        for ($i = 0; $i < $posicao; $i++) {
            $soma += (int) $cpf[$i] * (($posicao + 1) - $i);
        }
        $digito = (10 * $soma) % 11;
        if ($digito >= 10) {
            $digito = 0;
        }
        if ((int) $cpf[$posicao] !== $digito) {
            return false;
        }
    }
    return true;
}

// Retorna formatado (XXX.XXX.XXX-XX) se válido, null se não — mesmo padrão de normalizarTelefone().
function normalizarCpf($cpf) {
    $digitos = preg_replace('/\D/', '', $cpf ?? '');
    if (!cpfValido($digitos)) {
        return null;
    }
    return substr($digitos, 0, 3) . '.' . substr($digitos, 3, 3) . '.' . substr($digitos, 6, 3) . '-' . substr($digitos, 9, 2);
}

// Só aceita caminho relativo do próprio site (começa com "/" e não "//", que o navegador trata
// como outro host) — evita open redirect via voltar_para. Devolve null se inválido/vazio, quem
// chama decide o destino padrão nesse caso.
function caminhoSeguro($caminho) {
    $caminho = $caminho ?? '';
    return ($caminho !== '' && $caminho[0] === '/' && ($caminho[1] ?? '') !== '/') ? $caminho : null;
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

    // CPF — pedido só na hora do checkout (nota fiscal, documento do pagador no Mercado Pago),
    // não no cadastro, por isso NULL aqui pra quem já tinha conta antes desta coluna existir.
    $temCpf = (bool) $pdo->query("SHOW COLUMNS FROM Usuario LIKE 'CPF'")->fetchColumn();
    if (!$temCpf) {
        $pdo->exec("ALTER TABLE Usuario ADD COLUMN CPF CHAR(11) NULL AFTER Telefone");
    }

    // Marca o cliente de teste reaproveitado pelo Admin > Simulação — nunca aparece em relatório/
    // lista de cliente de verdade, só existe pra admin testar o fluxo completo de compra sem medo.
    $temSimulacao = (bool) $pdo->query("SHOW COLUMNS FROM Usuario LIKE 'Simulacao'")->fetchColumn();
    if (!$temSimulacao) {
        $pdo->exec("ALTER TABLE Usuario ADD COLUMN Simulacao TINYINT(1) NOT NULL DEFAULT 0 AFTER TipoUsuario");
    }

    // Token de "manter conectado" — igual TokenRecuperacao/DataExpiracaoToken (mesmo padrão),
    // só que dura semanas em vez de 1 hora e vive num cookie à parte, não no fluxo de senha.
    $temTokenLembrar = (bool) $pdo->query("SHOW COLUMNS FROM Usuario LIKE 'TokenLembrar'")->fetchColumn();
    if (!$temTokenLembrar) {
        $pdo->exec("ALTER TABLE Usuario ADD COLUMN TokenLembrar VARCHAR(64) NULL AFTER DataExpiracaoToken");
        $pdo->exec("ALTER TABLE Usuario ADD COLUMN DataExpiracaoLembrar DATETIME NULL AFTER TokenLembrar");
    }

    // Registro de quando a pessoa aceitou os termos de uso no cadastro — prova de consentimento,
    // não só a checkbox marcada na hora (isso não fica guardado em lugar nenhum sozinho).
    $temMomentoAceite = (bool) $pdo->query("SHOW COLUMNS FROM Usuario LIKE 'MomentoAceiteTermos'")->fetchColumn();
    if (!$temMomentoAceite) {
        $pdo->exec("ALTER TABLE Usuario ADD COLUMN MomentoAceiteTermos DATETIME NULL AFTER MomentoCadastro");
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

    // Caixa de envio (peso/dimensão pra cotação de frete) — por produto, não por variação: cor ou
    // tamanho raramente muda a caixa que o item cabe, e escolher por produto é bem mais rápido do
    // que teria que repetir pra cada variação.
    $temFkCaixaEnvio = (bool) $pdo->query("SHOW COLUMNS FROM Produto LIKE 'FKCaixaEnvio'")->fetchColumn();
    if (!$temFkCaixaEnvio) {
        $pdo->exec("ALTER TABLE Produto ADD COLUMN FKCaixaEnvio CHAR(36) NULL AFTER FKCategoria");
    }

    $jaVerificado = true;
}

function garantirTabelaCaixaEnvio() {
    static $jaVerificado = false;
    if ($jaVerificado) {
        return;
    }
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS CaixaEnvio (
        IDCaixaEnvio CHAR(36) PRIMARY KEY,
        Nome VARCHAR(60) NOT NULL,
        Peso DECIMAL(6,3) NOT NULL,
        Altura DECIMAL(6,2) NOT NULL,
        Largura DECIMAL(6,2) NOT NULL,
        Comprimento DECIMAL(6,2) NOT NULL,
        MomentoCriacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // FKCaixaEnvio em Produto depende dessa tabela existir primeiro — só cria a FK depois que a
    // coluna já foi adicionada por garantirTabelaProduto() (ordem de chamada não é garantida).
    $temFk = (bool) $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Produto' AND COLUMN_NAME = 'FKCaixaEnvio'
          AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchColumn();
    $temColuna = (bool) $pdo->query("SHOW COLUMNS FROM Produto LIKE 'FKCaixaEnvio'")->fetchColumn();
    if ($temColuna && !$temFk) {
        $pdo->exec("ALTER TABLE Produto ADD FOREIGN KEY (FKCaixaEnvio) REFERENCES CaixaEnvio(IDCaixaEnvio) ON DELETE SET NULL");
    }

    $jaVerificado = true;
}

function obterCaixasEnvio() {
    global $pdo;
    return $pdo->query("SELECT * FROM CaixaEnvio ORDER BY Peso ASC")->fetchAll();
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

    // Limite pra avisar "acabando" — opcional por variação (item que gira rápido pode querer um
    // mínimo maior que item parado). NULL usa o padrão global (ESTOQUE_MINIMO_PADRAO).
    $temEstoqueMinimo = (bool) $pdo->query("SHOW COLUMNS FROM VariacaoProduto LIKE 'EstoqueMinimo'")->fetchColumn();
    if (!$temEstoqueMinimo) {
        $pdo->exec("ALTER TABLE VariacaoProduto ADD COLUMN EstoqueMinimo INT NULL AFTER Estoque");
    }

    $jaVerificado = true;
}

function garantirTabelaMovimentoEstoque() {
    static $jaVerificado = false;
    if ($jaVerificado) {
        return;
    }
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS MovimentoEstoque (
        IDMovimento CHAR(36) PRIMARY KEY,
        FKVariacao CHAR(36) NOT NULL,
        Tipo ENUM('entrada','saida','ajuste','venda','cancelamento') NOT NULL,
        Quantidade INT NOT NULL,
        EstoqueResultante INT NOT NULL,
        Motivo VARCHAR(200) NULL,
        FKUsuario CHAR(36) NULL,
        MomentoMovimento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (FKVariacao) REFERENCES VariacaoProduto(IDVariacao) ON DELETE CASCADE,
        FOREIGN KEY (FKUsuario) REFERENCES Usuario(IDUsuario) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $jaVerificado = true;
}

// Único jeito "manual" de mudar estoque que deixa rastro — sempre grava tipo/quantidade/motivo/
// quem/quando junto (MovimentoEstoque), pra nunca sobrar "por que esse número tá assim" sem
// resposta. Não abre transação própria — quem chama decide isso (ex: dentro de outra transação
// maior), já que PDO não permite transação aninhada. 'ajuste' recebe o valor final desejado (não
// um delta); os outros tipos recebem sempre um número positivo, o próprio tipo diz se soma ou
// subtrai. Devolve o novo estoque, ou null se o movimento deixaria estoque negativo.
function registrarMovimentoEstoque($idVariacao, $tipo, $quantidade, $motivo = null, $idUsuario = null) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT Estoque FROM VariacaoProduto WHERE IDVariacao = :id");
    $stmt->execute(['id' => $idVariacao]);
    $estoqueAtual = $stmt->fetchColumn();
    if ($estoqueAtual === false) {
        return null;
    }

    if ($tipo === 'ajuste') {
        $novoEstoque = max(0, (int) $quantidade);
        $delta = $novoEstoque - (int) $estoqueAtual;
    } else {
        $delta = in_array($tipo, ['saida', 'venda'], true) ? -abs((int) $quantidade) : abs((int) $quantidade);
        $novoEstoque = (int) $estoqueAtual + $delta;
    }

    if ($novoEstoque < 0) {
        return null;
    }
    if ($delta === 0) {
        return $novoEstoque; // nada mudou de verdade, não registra movimento vazio
    }

    $pdo->prepare("UPDATE VariacaoProduto SET Estoque = :estoque WHERE IDVariacao = :id")
        ->execute(['estoque' => $novoEstoque, 'id' => $idVariacao]);
    $pdo->prepare("INSERT INTO MovimentoEstoque (IDMovimento, FKVariacao, Tipo, Quantidade, EstoqueResultante, Motivo, FKUsuario) VALUES (:id, :variacao, :tipo, :qtd, :resultante, :motivo, :usuario)")
        ->execute([
            'id' => gerarUuid(),
            'variacao' => $idVariacao,
            'tipo' => $tipo,
            'qtd' => $delta,
            'resultante' => $novoEstoque,
            'motivo' => $motivo !== '' ? $motivo : null,
            'usuario' => $idUsuario,
        ]);
    return $novoEstoque;
}

function obterMovimentosEstoque($idVariacao, $limite = 20) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT m.*, u.Nome AS NomeUsuario FROM MovimentoEstoque m
        LEFT JOIN Usuario u ON u.IDUsuario = m.FKUsuario
        WHERE m.FKVariacao = :id ORDER BY m.MomentoMovimento DESC LIMIT " . (int) $limite);
    $stmt->execute(['id' => $idVariacao]);
    return $stmt->fetchAll();
}

// Últimos movimentos de TODAS as variações — feed rápido pra ver o que andou mudando sem abrir
// produto por produto.
function obterMovimentosEstoqueRecentes($limite = 20) {
    global $pdo;
    $stmt = $pdo->query("SELECT m.*, u.Nome AS NomeUsuario, p.Nome AS NomeProduto, v.ValorAtributo1, v.ValorAtributo2
        FROM MovimentoEstoque m
        JOIN VariacaoProduto v ON v.IDVariacao = m.FKVariacao
        JOIN Produto p ON p.IDProduto = v.FKProduto
        LEFT JOIN Usuario u ON u.IDUsuario = m.FKUsuario
        ORDER BY m.MomentoMovimento DESC LIMIT " . (int) $limite);
    return $stmt->fetchAll();
}

// Toda variação com nome de produto junto, pra tela de Estoque — mais baixa (relativa ao próprio
// mínimo) primeiro, é a que precisa de atenção primeiro.
function obterEstoqueDetalhado() {
    global $pdo;
    return $pdo->query("SELECT v.*, p.Nome AS NomeProduto,
            COALESCE(v.EstoqueMinimo, " . (int) ESTOQUE_MINIMO_PADRAO . ") AS EstoqueMinimoEfetivo
        FROM VariacaoProduto v
        JOIN Produto p ON p.IDProduto = v.FKProduto
        ORDER BY (v.Estoque <= COALESCE(v.EstoqueMinimo, " . (int) ESTOQUE_MINIMO_PADRAO . ")) DESC, v.Estoque ASC")->fetchAll();
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

    // ValorAtributo1/2 NULL = "qualquer valor daquele eixo" (curinga). Uma foto com só
    // ValorAtributo1='Vermelho' (eixo 2 em branco) aparece em Vermelho·P, Vermelho·M, Vermelho·G...
    // de uma vez só — evita subir a mesma foto uma vez por tamanho. Os dois em branco = mídia
    // "base", aparece sempre. Espelha as mesmas colunas de VariacaoProduto de propósito, pra
    // poder reaproveitar descricaoVariacao() direto numa linha de ImagemProduto também.
    $temValorAtributo1 = (bool) $pdo->query("SHOW COLUMNS FROM ImagemProduto LIKE 'ValorAtributo1'")->fetchColumn();
    if (!$temValorAtributo1) {
        $pdo->exec("ALTER TABLE ImagemProduto ADD COLUMN ValorAtributo1 VARCHAR(100) NULL AFTER FKProduto");
        $pdo->exec("ALTER TABLE ImagemProduto ADD COLUMN ValorAtributo2 VARCHAR(100) NULL AFTER ValorAtributo1");
    }

    // Coluna antiga (FKVariacao, vínculo com 1 variação exata) — migra pro par de valores da
    // própria variação referenciada (preserva o comportamento de quem já tinha foto vinculada)
    // e descarta a coluna/FK velha.
    $temFkVariacao = (bool) $pdo->query("SHOW COLUMNS FROM ImagemProduto LIKE 'FKVariacao'")->fetchColumn();
    if ($temFkVariacao) {
        $pdo->exec("UPDATE ImagemProduto i
            JOIN VariacaoProduto v ON v.IDVariacao = i.FKVariacao
            SET i.ValorAtributo1 = v.ValorAtributo1, i.ValorAtributo2 = v.ValorAtributo2
            WHERE i.FKVariacao IS NOT NULL");
        $nomeConstraint = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ImagemProduto'
              AND COLUMN_NAME = 'FKVariacao' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchColumn();
        if ($nomeConstraint) {
            $pdo->exec("ALTER TABLE ImagemProduto DROP FOREIGN KEY `$nomeConstraint`");
        }
        $pdo->exec("ALTER TABLE ImagemProduto DROP COLUMN FKVariacao");
    }

    $temTipoMidia = (bool) $pdo->query("SHOW COLUMNS FROM ImagemProduto LIKE 'TipoMidia'")->fetchColumn();
    if (!$temTipoMidia) {
        $pdo->exec("ALTER TABLE ImagemProduto ADD COLUMN TipoMidia ENUM('imagem','video') NOT NULL DEFAULT 'imagem' AFTER Url");
    }

    $jaVerificado = true;
}

// Uma foto/vídeo "combina" com uma variação quando, em cada eixo, ou o valor bate ou a mídia não
// travou aquele eixo (NULL = qualquer valor) — deixa 1 upload servir várias variações da mesma
// cor. Ordena a mais específica primeiro (mais eixo travado), mídia "base" (nenhum eixo) por
// último, pra virar a foto ativa do carrossel quando a variação muda.
function imagensParaVariacao($imagens, $variacao) {
    $combina = array_values(array_filter($imagens, function ($img) use ($variacao) {
        $eixo1Ok = $img['ValorAtributo1'] === null || $img['ValorAtributo1'] === $variacao['ValorAtributo1'];
        $eixo2Ok = $img['ValorAtributo2'] === null || $img['ValorAtributo2'] === $variacao['ValorAtributo2'];
        return $eixo1Ok && $eixo2Ok;
    }));
    usort($combina, fn($a, $b) =>
        (($b['ValorAtributo1'] !== null) + ($b['ValorAtributo2'] !== null))
        - (($a['ValorAtributo1'] !== null) + ($a['ValorAtributo2'] !== null))
    );
    return $combina;
}

// ---------------------------------------------------------------------
// Autenticação — uma sessão só (Usuario), o TipoUsuario decide o que a pessoa vê
// ---------------------------------------------------------------------

function clienteLogado() {
    _tentarLoginLembrado();
    return !empty($_SESSION['usuario_id']) && ($_SESSION['usuario_tipo'] ?? '') === 'cliente';
}

function adminLogado() {
    _tentarLoginLembrado();
    return !empty($_SESSION['usuario_id']) && ($_SESSION['usuario_tipo'] ?? '') === 'admin';
}

// Cookie de "manter conectado" sobrevive ao fim da sessão do navegador — se a sessão PHP não tem
// usuário mas existe um token válido, loga sozinho antes de qualquer checagem de acesso. Roda no
// máximo 1x por request (mesmo padrão de memoização dos garantirTabelaX()).
function _tentarLoginLembrado() {
    static $jaTentou = false;
    if ($jaTentou || !empty($_SESSION['usuario_id']) || empty($_COOKIE['lembrar_token'])) {
        $jaTentou = true;
        return;
    }
    $jaTentou = true;

    global $pdo;
    garantirTabelaUsuario(); // garante que a coluna já existe, não importa a ordem de chamada da página
    $stmt = $pdo->prepare("SELECT * FROM Usuario WHERE TokenLembrar = :token AND DataExpiracaoLembrar > NOW()");
    $stmt->execute(['token' => $_COOKIE['lembrar_token']]);
    $usuario = $stmt->fetch();
    if ($usuario) {
        $_SESSION['usuario_id'] = $usuario['IDUsuario'];
        $_SESSION['usuario_nome'] = $usuario['Nome'];
        $_SESSION['usuario_tipo'] = $usuario['TipoUsuario'];
    }
}

// Gera um novo token de "manter conectado", grava no banco (30 dias) e manda no cookie —
// chamado no login/cadastro só quando a pessoa marca a caixinha.
function ativarLoginLembrado($idUsuario) {
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE Usuario SET TokenLembrar = :token, DataExpiracaoLembrar = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE IDUsuario = :id")
        ->execute(['token' => $token, 'id' => $idUsuario]);
    setcookie('lembrar_token', $token, [
        'expires' => time() + 30 * 24 * 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Invalida o token no banco (não só o cookie) — senão o logout não logout de verdade, a próxima
// visita loga sozinho de novo pelo token antigo ainda válido.
function encerrarLoginLembrado() {
    if (!empty($_COOKIE['lembrar_token'])) {
        global $pdo;
        $pdo->prepare("UPDATE Usuario SET TokenLembrar = NULL, DataExpiracaoLembrar = NULL WHERE TokenLembrar = :token")
            ->execute(['token' => $_COOKIE['lembrar_token']]);
        setcookie('lembrar_token', '', ['expires' => time() - 3600, 'path' => '/']);
    }
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

// Retorna o próprio ID + todo descendente (filho, neto, ...), pra categoria-mãe poder somar
// produto de subcategoria sem precisar herdar categoria de verdade no banco.
function obterDescendentesCategoria($idCategoria) {
    $todas = obterCategorias();
    $porPai = [];
    foreach ($todas as $categoria) {
        $porPai[$categoria['FKCategoriaPai'] ?? ''][] = $categoria['IDCategoria'];
    }

    $ids = [$idCategoria];
    $fila = [$idCategoria];
    while ($fila) {
        $atual = array_shift($fila);
        foreach ($porPai[$atual] ?? [] as $filhoId) {
            $ids[] = $filhoId;
            $fila[] = $filhoId;
        }
    }
    return $ids;
}

// Produto de uma categoria, separado em "direto" (produto ligado exatamente a essa categoria)
// e 1 grupo por subcategoria DIRETA (cada grupo já traz produto de neto/mini-grupo dela junto,
// sem duplicar) — pra página de categoria poder mostrar subtítulo por subcategoria quando
// existir, e cair no grid simples de sempre quando a categoria não tem filho nenhum.
function obterProdutosAgrupadosPorCategoria($idCategoria) {
    $todas = obterCategorias();
    $filhosDiretos = array_values(array_filter($todas, fn($c) => $c['FKCategoriaPai'] === $idCategoria));

    $grupos = [];
    foreach ($filhosDiretos as $filho) {
        $produtosRamo = obterProdutosAtivos(obterDescendentesCategoria($filho['IDCategoria']));
        if ($produtosRamo) {
            $grupos[] = ['categoria' => $filho, 'produtos' => $produtosRamo];
        }
    }

    return [
        'diretos' => obterProdutosAtivos($idCategoria),
        'grupos' => $grupos,
    ];
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
                   (SELECT Estoque FROM VariacaoProduto WHERE FKProduto = p.IDProduto ORDER BY Preco LIMIT 1) AS EstoqueVariacaoUnica,
                   (SELECT Nome FROM Categoria WHERE IDCategoria = p.FKCategoria) AS NomeCategoria
            FROM Produto p
            WHERE p.Ativo = 1";
    $params = [];
    if ($idCategoria !== null) {
        // Aceita 1 ID ou uma lista (categoria-mãe passa ela + descendentes, pra somar os
        // produtos das subcategorias em vez de só mostrar quem tá exatamente nela).
        $idsCategoria = is_array($idCategoria) ? $idCategoria : [$idCategoria];
        $placeholders = [];
        foreach (array_values($idsCategoria) as $i => $id) {
            $chave = "idCategoria$i";
            $placeholders[] = ":$chave";
            $params[$chave] = $id;
        }
        $sql .= " AND p.FKCategoria IN (" . implode(',', $placeholders) . ")";
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

// Move o upload pra geral/img/{subpasta}/{uuid}.ext e devolve ['url' => ..., 'tipo' => 'imagem'|'video'],
// ou null se inválido. Vídeo tem limite de tamanho maior — arquivo de vídeo é naturalmente bem
// mais pesado que foto, 5MB não dá pra nada além de um clipe de poucos segundos.
function uploadImagem($arquivo, $subpasta) {
    if (empty($arquivo['tmp_name']) || $arquivo['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $extensoesImagem = ['jpg', 'jpeg', 'png', 'webp', 'ico', 'svg'];
    $extensoesComprimiveis = ['jpg', 'jpeg', 'png', 'webp'];
    $extensoesVideo = ['mp4', 'webm', 'mov'];
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    $vaiComprimir = in_array($extensao, $extensoesComprimiveis, true) && extension_loaded('gd');

    if (in_array($extensao, $extensoesImagem, true)) {
        $tipo = 'imagem';
        // Sem compressão o arquivo bruto É o arquivo final, por isso o teto baixo. Com
        // compressão o bruto só passa pelo GD e vira um JPEG bem menor depois — o teto alto aqui
        // é só pra barrar abuso, quem garante o tamanho final é a recompressão, não esse limite.
        $tamanhoMaximo = $vaiComprimir ? 20 * 1024 * 1024 : 5 * 1024 * 1024;
    } elseif (in_array($extensao, $extensoesVideo, true)) {
        $tipo = 'video';
        $tamanhoMaximo = 30 * 1024 * 1024;
    } else {
        return null;
    }

    if ($arquivo['size'] > $tamanhoMaximo) {
        return null;
    }

    $pastaDestino = __DIR__ . '/../geral/img/' . $subpasta;
    if (!is_dir($pastaDestino)) {
        mkdir($pastaDestino, 0755, true);
    }

    // Foto "colada" de outro site (Ctrl+C numa imagem, Ctrl+V no campo de upload) costuma virar
    // PNG sem compressão nenhuma pelo navegador — uma foto que seria ~200KB em JPEG vira vários
    // MB, deixando o carregamento da página bem lento. Redimensiona e recomprime nesse caso; se
    // o GD não tiver disponível no host, sobe o arquivo original mesmo assim (upload não pode
    // falhar por causa de otimização).
    if ($vaiComprimir) {
        $otimizada = _redimensionarEComprimir($arquivo['tmp_name']);
        if ($otimizada !== null) {
            $nomeArquivo = gerarUuid() . '.' . $otimizada['extensao'];
            if (file_put_contents($pastaDestino . '/' . $nomeArquivo, $otimizada['dados']) !== false) {
                return ['url' => '/geral/img/' . $subpasta . '/' . $nomeArquivo, 'tipo' => $tipo];
            }
        }
    }

    $nomeArquivo = gerarUuid() . '.' . $extensao;
    if (!move_uploaded_file($arquivo['tmp_name'], $pastaDestino . '/' . $nomeArquivo)) {
        return null;
    }

    return ['url' => '/geral/img/' . $subpasta . '/' . $nomeArquivo, 'tipo' => $tipo];
}

// Redimensiona (máximo 1600px no lado maior) e recomprime — PNG sem transparência de verdade
// (o caso comum de "colei uma foto copiada", que sempre vem como PNG mesmo sendo uma foto comum)
// vira JPEG, que comprime muito melhor foto/textura. PNG com transparência real continua PNG.
// Devolve null se não conseguir processar (arquivo corrompido, GD sem suporte a esse formato
// etc.) — nesse caso quem chamou sobe o arquivo original sem otimizar.
function _redimensionarEComprimir($caminhoOrigem) {
    $info = @getimagesize($caminhoOrigem);
    if ($info === false) {
        return null;
    }
    $tipoImagem = $info[2];

    $imagem = match ($tipoImagem) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($caminhoOrigem),
        IMAGETYPE_PNG => @imagecreatefrompng($caminhoOrigem),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($caminhoOrigem) : false,
        default => false,
    };
    if ($imagem === false) {
        return null;
    }

    // Corrige rotação de foto de celular ANTES de medir/redimensionar — o GD não preserva EXIF
    // ao resalvar, e girar depois da medição bagunçaria largura x altura.
    if ($tipoImagem === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($caminhoOrigem);
        $girada = match ($exif['Orientation'] ?? 1) {
            3 => imagerotate($imagem, 180, 0),
            6 => imagerotate($imagem, -90, 0),
            8 => imagerotate($imagem, 90, 0),
            default => null,
        };
        if ($girada !== null) {
            imagedestroy($imagem);
            $imagem = $girada;
        }
    }

    $largura = imagesx($imagem);
    $altura = imagesy($imagem);

    $maximo = 1600;
    if ($largura > $maximo || $altura > $maximo) {
        $escala = min($maximo / $largura, $maximo / $altura);
        $novaLargura = max(1, (int) round($largura * $escala));
        $novaAltura = max(1, (int) round($altura * $escala));
        $redimensionada = imagecreatetruecolor($novaLargura, $novaAltura);
        if ($tipoImagem === IMAGETYPE_PNG) {
            imagealphablending($redimensionada, false);
            imagesavealpha($redimensionada, true);
        }
        imagecopyresampled($redimensionada, $imagem, 0, 0, 0, 0, $novaLargura, $novaAltura, $largura, $altura);
        imagedestroy($imagem);
        $imagem = $redimensionada;
        $largura = $novaLargura;
        $altura = $novaAltura;
    }

    $temTransparenciaReal = $tipoImagem === IMAGETYPE_PNG && _pngTemTransparencia($imagem, $largura, $altura);

    ob_start();
    if ($temTransparenciaReal) {
        imagepng($imagem, null, 8);
        $extensaoFinal = 'png';
    } else {
        imagejpeg($imagem, null, 85);
        $extensaoFinal = 'jpg';
    }
    $dados = ob_get_clean();
    imagedestroy($imagem);

    return ($dados !== false && $dados !== '') ? ['dados' => $dados, 'extensao' => $extensaoFinal] : null;
}

// Amostra uma grade de pixels (não todos, custaria caro numa imagem grande) pra ver se o PNG tem
// canal alfa de verdade — PNG "de foto" (print de produto, screenshot) quase sempre é 100% opaco
// mesmo tendo canal alfa disponível no formato.
function _pngTemTransparencia($imagem, $largura, $altura) {
    $passoX = max(1, (int) ($largura / 40));
    $passoY = max(1, (int) ($altura / 40));
    for ($x = 0; $x < $largura; $x += $passoX) {
        for ($y = 0; $y < $altura; $y += $passoY) {
            $alfa = (imagecolorat($imagem, $x, $y) >> 24) & 0x7F;
            if ($alfa > 0) {
                return true;
            }
        }
    }
    return false;
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
        $stmt = $pdo->prepare("SELECT v.*, p.IDProduto, p.Nome AS NomeProduto
            FROM VariacaoProduto v
            JOIN Produto p ON p.IDProduto = v.FKProduto
            WHERE v.IDVariacao = :id");
        $stmt->execute(['id' => $idVariacao]);
        $variacao = $stmt->fetch();
        if (!$variacao) {
            continue; // variação foi excluída pelo admin depois de ter sido adicionada ao carrinho
        }
        $imagens = imagensParaVariacao(obterImagensPorProduto($variacao['IDProduto']), $variacao);
        $variacao['ImagemCapa'] = $imagens[0]['Url'] ?? null;
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

function garantirTabelaEndereco() {
    static $jaVerificado = false;
    if ($jaVerificado) {
        return;
    }
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS Endereco (
        IDEndereco CHAR(36) PRIMARY KEY,
        FKUsuario CHAR(36) NOT NULL,
        CEP VARCHAR(9) NOT NULL,
        Logradouro VARCHAR(200) NOT NULL,
        Numero VARCHAR(20) NOT NULL,
        Complemento VARCHAR(100) NULL,
        Bairro VARCHAR(100) NULL,
        Cidade VARCHAR(100) NOT NULL,
        UF CHAR(2) NOT NULL,
        Principal TINYINT(1) NOT NULL DEFAULT 0,
        MomentoCriacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (FKUsuario) REFERENCES Usuario(IDUsuario) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $jaVerificado = true;
}

// Lista fixa das 27 UFs — usada no form de endereço (aqui e no checkout), fallback manual quando
// o autopreenchimento por CEP falhar ou a pessoa preferir digitar direto.
function listaUfsBrasil() {
    return [
        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia',
        'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás',
        'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
        'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
        'SE' => 'Sergipe', 'TO' => 'Tocantins',
    ];
}

function obterEnderecosPorUsuario($idUsuario) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM Endereco WHERE FKUsuario = :u ORDER BY Principal DESC, MomentoCriacao DESC");
    $stmt->execute(['u' => $idUsuario]);
    return $stmt->fetchAll();
}

// Resolve o endereço de entrega — de um salvo (por ID) ou digitado na hora ($dados = $_POST).
// Compartilhada entre checkout.php (confirmar o pedido) e checkout_frete.php (cotação via AJAX) —
// as duas precisam da mesma lógica pra não divergir sobre o que conta como endereço válido.
function resolverEndereco($enderecos, $idSelecionado, $dados) {
    if ($idSelecionado !== '' && $idSelecionado !== 'novo') {
        foreach ($enderecos as $e) {
            if ($e['IDEndereco'] === $idSelecionado) {
                return ['cep' => $e['CEP'], 'logradouro' => $e['Logradouro'], 'numero' => $e['Numero'], 'complemento' => $e['Complemento'] ?? '', 'bairro' => $e['Bairro'] ?? '', 'cidade' => $e['Cidade'], 'uf' => $e['UF']];
            }
        }
        return null;
    }
    $cep = preg_replace('/\D/', '', $dados['cep'] ?? '');
    $logradouro = trim($dados['logradouro'] ?? '');
    $numero = trim($dados['numero'] ?? '');
    $cidade = trim($dados['cidade'] ?? '');
    $uf = strtoupper(trim($dados['uf'] ?? ''));
    if (strlen($cep) !== 8 || $logradouro === '' || $numero === '' || $cidade === '' || !array_key_exists($uf, listaUfsBrasil())) {
        return null;
    }
    return ['cep' => substr($cep, 0, 5) . '-' . substr($cep, 5), 'logradouro' => $logradouro, 'numero' => $numero, 'complemento' => trim($dados['complemento'] ?? ''), 'bairro' => trim($dados['bairro'] ?? ''), 'cidade' => $cidade, 'uf' => $uf];
}

// Reserva pra quando obterOpcoesFrete() não consegue cotar de verdade (Melhor Envio desconectado,
// API fora do ar, produto sem CaixaEnvio definida) — fixo, grátis acima de um valor (config/marca.php).
// Checkout nunca trava por causa de uma integração externa fora do ar.
function calcularFrete($cep, $subtotal) {
    if ($subtotal >= FRETE_GRATIS_ACIMA_DE) {
        return 0.0;
    }
    return FRETE_VALOR_PADRAO;
}

// ---------------------------------------------------------------------
// Melhor Envio — cotação de frete real via API v2, autenticada com token direto (gerado no painel
// deles, sem OAuth). Escopo só de cotação (não compra etiqueta, não gera rastreio automático).
// ---------------------------------------------------------------------

function melhorEnvioUrlBase() {
    return MELHOR_ENVIO_AMBIENTE === 'producao'
        ? 'https://melhorenvio.com.br'
        : 'https://sandbox.melhorenvio.com.br';
}

// Token gerado direto no painel do Melhor Envio (Integrações > Tokens de Acesso) — sem Client
// ID/Secret, sem fluxo de autorização. Validade de ~1 ano; quando expirar, gera um novo token no
// painel deles e cola em config/chaves.php, não tem renovação automática pra isso.
function melhorEnvioConectado() {
    return defined('MELHOR_ENVIO_TOKEN') && MELHOR_ENVIO_TOKEN !== '';
}

// Lê o "exp" (data de expiração) de dentro do próprio token, só pra exibir no admin — não valida
// assinatura nem autentica nada com isso, é puramente informativo (JWT é só base64, não é secreto
// decodificar a leitura, só a chave privada de assinatura é secreta e essa a gente nem tem).
function melhorEnvioTokenExpiraEm() {
    if (!melhorEnvioConectado()) {
        return null;
    }
    $partes = explode('.', MELHOR_ENVIO_TOKEN);
    if (count($partes) !== 3) {
        return null;
    }
    $payload = json_decode(base64_decode(strtr($partes[1], '-_', '+/')), true);
    return isset($payload['exp']) ? (int) $payload['exp'] : null;
}

// User-Agent identificando a aplicação não é boa prática opcional aqui — o Melhor Envio rejeita
// requisição sem isso.
function _melhorEnvioRequisicao($metodo, $url, $corpo = null, $token = null) {
    $headers = ['Accept: application/json', 'Content-Type: application/json', 'User-Agent: ' . NOME_LOJA . ' (' . TEXTO_CONTATO . ')'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 10,
    ]);
    if ($corpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo));
    }
    $resposta = curl_exec($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($ch);
    curl_close($ch);

    if ($erroCurl !== '') {
        error_log('Melhor Envio — falha de conexão: ' . $erroCurl);
        return null;
    }
    if ($codigoHttp >= 400) {
        error_log('Melhor Envio — HTTP ' . $codigoHttp . ': ' . $resposta);
        return null;
    }
    return json_decode($resposta, true);
}

// Cotação real — retorna null se não dá pra cotar (token não configurado, API fora, produto sem
// caixa definida). Quem chama cai no calcularFrete() fixo como reserva, checkout nunca trava por
// causa disso.
function obterOpcoesFrete($cepDestino, $itens) {
    if (!defined('MELHOR_ENVIO_CEP_ORIGEM') || MELHOR_ENVIO_CEP_ORIGEM === '' || !melhorEnvioConectado()) {
        return null;
    }
    $token = MELHOR_ENVIO_TOKEN;

    global $pdo;
    $idsProdutos = array_values(array_unique(array_map(fn($item) => $item['variacao']['IDProduto'], $itens)));
    $placeholders = implode(',', array_fill(0, count($idsProdutos), '?'));
    $stmt = $pdo->prepare("SELECT p.IDProduto, c.Peso, c.Altura, c.Largura, c.Comprimento FROM Produto p
        JOIN CaixaEnvio c ON c.IDCaixaEnvio = p.FKCaixaEnvio WHERE p.IDProduto IN ($placeholders)");
    $stmt->execute($idsProdutos);
    $caixasPorProduto = [];
    foreach ($stmt->fetchAll() as $row) {
        $caixasPorProduto[$row['IDProduto']] = $row;
    }

    $produtos = [];
    foreach ($itens as $item) {
        $idProduto = $item['variacao']['IDProduto'];
        if (!isset($caixasPorProduto[$idProduto])) {
            // Sem caixa definida pra pelo menos 1 item do carrinho — não dá pra cotar o pedido
            // inteiro direito, melhor cair no frete fixo do que subcotar por faltar peso/dimensão.
            error_log('Melhor Envio — produto ' . $idProduto . ' sem CaixaEnvio definida, cotação abortada.');
            return null;
        }
        $caixa = $caixasPorProduto[$idProduto];
        $produtos[] = [
            'id' => $idProduto,
            'width' => (float) $caixa['Largura'],
            'height' => (float) $caixa['Altura'],
            'length' => (float) $caixa['Comprimento'],
            'weight' => (float) $caixa['Peso'],
            // Valor unitário, não o subtotal da linha — a API já multiplica isso por "quantity"
            // pra chegar no valor total declarado; mandar o subtotal aqui dobraria (ou pior,
            // multiplicaria pela quantidade de novo) o valor segurado declarado.
            'insurance_value' => (float) $item['variacao']['Preco'],
            'quantity' => (int) $item['Quantidade'],
        ];
    }

    $dados = _melhorEnvioRequisicao('POST', melhorEnvioUrlBase() . '/api/v2/me/shipment/calculate', [
        'from' => ['postal_code' => preg_replace('/\D/', '', MELHOR_ENVIO_CEP_ORIGEM)],
        'to' => ['postal_code' => preg_replace('/\D/', '', $cepDestino)],
        'products' => $produtos,
    ], $token);

    if (!is_array($dados)) {
        return null;
    }

    $opcoes = [];
    foreach ($dados as $opcao) {
        // Cotação parcial: serviço indisponível pra essa rota/peso vem com "error" em vez de preço.
        if (!empty($opcao['error']) || !isset($opcao['id'])) {
            continue;
        }
        $preco = $opcao['custom_price'] ?? $opcao['price'] ?? null;
        if ($preco === null) {
            continue;
        }
        $opcoes[] = [
            'id' => (string) $opcao['id'],
            'transportadora' => $opcao['company']['name'] ?? '',
            'servico' => $opcao['name'] ?? '',
            'preco' => (float) $preco,
            'prazo_dias' => (int) ($opcao['custom_delivery_time'] ?? $opcao['delivery_time'] ?? 0),
        ];
    }
    usort($opcoes, fn($a, $b) => $a['preco'] <=> $b['preco']);
    return $opcoes ?: null;
}

function garantirTabelaCupom() {
    static $jaVerificado = false;
    if ($jaVerificado) {
        return;
    }
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS Cupom (
        IDCupom CHAR(36) PRIMARY KEY,
        Codigo VARCHAR(40) NOT NULL UNIQUE,
        TipoDesconto ENUM('percentual','fixo') NOT NULL,
        ValorDesconto DECIMAL(10,2) NOT NULL,
        DataValidade DATE NULL,
        LimiteUso INT NULL,
        UsosAtuais INT NOT NULL DEFAULT 0,
        Ativo TINYINT(1) NOT NULL DEFAULT 1,
        MomentoCriacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $jaVerificado = true;
}

// Confere existência, ativo, validade e limite de uso — devolve a linha do cupom ou null. Reusado
// tanto na prévia do checkout quanto (de novo, sempre) na hora de fechar o pedido de verdade,
// porque o cupom pode ter expirado/esgotado entre uma coisa e outra.
function validarCupom($codigo) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM Cupom WHERE Codigo = :codigo AND Ativo = 1");
    $stmt->execute(['codigo' => trim($codigo)]);
    $cupom = $stmt->fetch();
    if (!$cupom) {
        return null;
    }
    if ($cupom['DataValidade'] !== null && $cupom['DataValidade'] < date('Y-m-d')) {
        return null;
    }
    if ($cupom['LimiteUso'] !== null && (int) $cupom['UsosAtuais'] >= (int) $cupom['LimiteUso']) {
        return null;
    }
    return $cupom;
}

// Motivo específico de um código não valer — só pra mensagem no checkout (aviso, não erro; o
// cliente só digitou um código errado/vencido, não quebrou nada). validarCupom() continua
// devolvendo só null pra quem não precisa do motivo exato (ex: criarPedido()).
function motivoCupomInvalido($codigo) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM Cupom WHERE Codigo = :codigo");
    $stmt->execute(['codigo' => trim($codigo)]);
    $cupom = $stmt->fetch();
    if (!$cupom || !$cupom['Ativo']) {
        return 'Esse cupom não existe.';
    }
    if ($cupom['DataValidade'] !== null && $cupom['DataValidade'] < date('Y-m-d')) {
        return 'Esse cupom expirou.';
    }
    if ($cupom['LimiteUso'] !== null && (int) $cupom['UsosAtuais'] >= (int) $cupom['LimiteUso']) {
        return 'Esse cupom atingiu o limite de uso.';
    }
    return null;
}

// Desconto fixo nunca deixa o total negativo — trava no valor do subtotal.
function calcularDescontoCupom($cupom, $subtotal) {
    if ($cupom['TipoDesconto'] === 'percentual') {
        return round($subtotal * ((float) $cupom['ValorDesconto'] / 100), 2);
    }
    return min((float) $cupom['ValorDesconto'], $subtotal);
}

// ---------------------------------------------------------------------
// Pedido — IDPedido é AUTO_INCREMENT de propósito (numeração sequencial é valor de negócio
// aqui, "Pedido #00042"), diferente do padrão UUID do resto do sistema.
// ---------------------------------------------------------------------

// Rótulo + badge + ícone por status — usado na lista/detalhe do cliente e no admin, evita
// espalhar essa tabela de tradução por várias telas.
function statusPedidoInfo($status) {
    $mapa = [
        'aguardando_pagamento' => ['label' => 'Aguardando pagamento', 'badge' => 'badge-atencao', 'icone' => 'bi-clock'],
        'pago' => ['label' => 'Pago', 'badge' => 'badge-sucesso', 'icone' => 'bi-check-circle'],
        'preparando' => ['label' => 'Preparando', 'badge' => 'badge-atencao', 'icone' => 'bi-box-seam'],
        'enviado' => ['label' => 'Enviado', 'badge' => 'badge-destaque', 'icone' => 'bi-truck'],
        'entregue' => ['label' => 'Entregue', 'badge' => 'badge-sucesso', 'icone' => 'bi-check-circle-fill'],
        'cancelado' => ['label' => 'Cancelado', 'badge' => 'badge-perigo', 'icone' => 'bi-x-circle'],
    ];
    return $mapa[$status] ?? ['label' => $status, 'badge' => 'badge-atencao', 'icone' => 'bi-question-circle'];
}

function garantirTabelaPedido() {
    static $jaVerificado = false;
    if ($jaVerificado) {
        return;
    }
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS Pedido (
        IDPedido INT AUTO_INCREMENT PRIMARY KEY,
        FKUsuario CHAR(36) NOT NULL,
        Status ENUM('aguardando_pagamento','pago','preparando','enviado','entregue','cancelado') NOT NULL DEFAULT 'aguardando_pagamento',
        ValorSubtotal DECIMAL(10,2) NOT NULL,
        ValorDesconto DECIMAL(10,2) NOT NULL DEFAULT 0,
        ValorFrete DECIMAL(10,2) NOT NULL DEFAULT 0,
        ValorTotal DECIMAL(10,2) NOT NULL,
        FKCupom CHAR(36) NULL,
        EnderecoCep VARCHAR(9) NOT NULL,
        EnderecoLogradouro VARCHAR(200) NOT NULL,
        EnderecoNumero VARCHAR(20) NOT NULL,
        EnderecoComplemento VARCHAR(100) NULL,
        EnderecoBairro VARCHAR(100) NULL,
        EnderecoCidade VARCHAR(100) NOT NULL,
        EnderecoUF CHAR(2) NOT NULL,
        CodigoRastreio VARCHAR(100) NULL,
        ReferenciaPagamento VARCHAR(100) NULL,
        MomentoCriacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (FKUsuario) REFERENCES Usuario(IDUsuario),
        FOREIGN KEY (FKCupom) REFERENCES Cupom(IDCupom) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Copiado do Usuario.Simulacao do dono no momento da criação — não muda com FK viva porque
    // relatório precisa continuar batendo mesmo se o cliente de simulação for recriado depois.
    $temSimulacao = (bool) $pdo->query("SHOW COLUMNS FROM Pedido LIKE 'Simulacao'")->fetchColumn();
    if (!$temSimulacao) {
        $pdo->exec("ALTER TABLE Pedido ADD COLUMN Simulacao TINYINT(1) NOT NULL DEFAULT 0 AFTER Status");
    }

    // Transportadora/serviço/prazo escolhidos no checkout (Melhor Envio) — NULL quando o pedido
    // caiu no frete fixo de reserva (calcularFrete()) por algum motivo.
    $temFreteTransportadora = (bool) $pdo->query("SHOW COLUMNS FROM Pedido LIKE 'FreteTransportadora'")->fetchColumn();
    if (!$temFreteTransportadora) {
        $pdo->exec("ALTER TABLE Pedido ADD COLUMN FreteTransportadora VARCHAR(60) NULL AFTER ValorFrete");
        $pdo->exec("ALTER TABLE Pedido ADD COLUMN FreteServico VARCHAR(60) NULL AFTER FreteTransportadora");
        $pdo->exec("ALTER TABLE Pedido ADD COLUMN FretePrazoDias INT NULL AFTER FreteServico");
    }

    $jaVerificado = true;
}

function garantirTabelaItemPedido() {
    static $jaVerificado = false;
    if ($jaVerificado) {
        return;
    }
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS ItemPedido (
        IDItemPedido CHAR(36) PRIMARY KEY,
        FKPedido INT NOT NULL,
        FKVariacao CHAR(36) NULL,
        NomeProduto VARCHAR(200) NOT NULL,
        DescricaoVariacao VARCHAR(200) NULL,
        Quantidade INT NOT NULL,
        PrecoUnitario DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (FKPedido) REFERENCES Pedido(IDPedido) ON DELETE CASCADE,
        FOREIGN KEY (FKVariacao) REFERENCES VariacaoProduto(IDVariacao) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $jaVerificado = true;
}

function garantirTabelaHistoricoStatusPedido() {
    static $jaVerificado = false;
    if ($jaVerificado) {
        return;
    }
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS HistoricoStatusPedido (
        IDHistorico CHAR(36) PRIMARY KEY,
        FKPedido INT NOT NULL,
        StatusAnterior VARCHAR(30) NULL,
        StatusNovo VARCHAR(30) NOT NULL,
        MomentoMudanca TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (FKPedido) REFERENCES Pedido(IDPedido) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $jaVerificado = true;
}

// Cria o pedido inteiro numa transação: desconta estoque item a item com proteção contra vender
// abaixo de zero (a checagem "de verdade" é aqui, não antes), grava Pedido + ItemPedido (com
// nome/descrição/preço copiados — não ligados por FK viva, pedido antigo não pode mudar
// retroativamente se o produto for editado depois), primeiro HistoricoStatusPedido, soma uso do
// cupom se tiver, e limpa o carrinho só se tudo deu certo. $endereco é um array com
// cep/logradouro/numero/complemento/bairro/cidade/uf (de um Endereco salvo ou digitado na hora).
// $frete e $freteInfo vêm já decididos por quem chama (checkout.php) — não recalcula aqui de
// propósito: o valor mostrado pro cliente na tela tem que ser exatamente o cobrado, e uma chamada
// de rede pro Melhor Envio no meio de uma transação de banco é exatamente o tipo de coisa que
// pode travar seguro. $freteInfo é ['transportadora','servico','prazo_dias'] ou null.
function criarPedido($idUsuario, $endereco, $cupomCodigo, $frete, $freteInfo = null) {
    global $pdo;

    $itens = obterCarrinho();
    if (!$itens) {
        return ['sucesso' => false, 'erro' => 'Seu carrinho está vazio.'];
    }

    $subtotal = array_sum(array_column($itens, 'subtotal'));

    // Nunca confia no cupom "aplicado" antes — valida de novo aqui, pode ter expirado ou
    // esgotado o limite de uso entre a prévia do checkout e a confirmação de verdade.
    $cupom = $cupomCodigo !== '' ? validarCupom($cupomCodigo) : null;
    $desconto = $cupom ? calcularDescontoCupom($cupom, $subtotal) : 0;
    $total = max(0, $subtotal - $desconto) + $frete;

    // Detecta sozinho se é o cliente de simulação (Admin > Simulação) — checkout.php não precisa
    // saber nada sobre simulação, o pedido já nasce marcado certo automaticamente.
    $stmtSimulacao = $pdo->prepare("SELECT Simulacao FROM Usuario WHERE IDUsuario = :id");
    $stmtSimulacao->execute(['id' => $idUsuario]);
    $ehSimulacao = (bool) $stmtSimulacao->fetchColumn();

    $pdo->beginTransaction();
    try {
        foreach ($itens as $item) {
            $stmt = $pdo->prepare("UPDATE VariacaoProduto SET Estoque = Estoque - :qtd WHERE IDVariacao = :id AND Estoque >= :qtd");
            $stmt->execute(['qtd' => $item['Quantidade'], 'id' => $item['IDVariacao']]);
            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                return ['sucesso' => false, 'erro' => 'Estoque insuficiente pra "' . $item['variacao']['NomeProduto'] . '" — atualize seu carrinho e tente de novo.'];
            }
            // Lê de novo em vez de calcular na mão (Estoque em $item pode já estar desatualizado
            // se outra compra mexeu no meio tempo) — o histórico tem que bater com o valor real.
            $stmtResultante = $pdo->prepare("SELECT Estoque FROM VariacaoProduto WHERE IDVariacao = :id");
            $stmtResultante->execute(['id' => $item['IDVariacao']]);
            $pdo->prepare("INSERT INTO MovimentoEstoque (IDMovimento, FKVariacao, Tipo, Quantidade, EstoqueResultante, Motivo) VALUES (:id, :variacao, 'venda', :qtd, :resultante, NULL)")
                ->execute([
                    'id' => gerarUuid(),
                    'variacao' => $item['IDVariacao'],
                    'qtd' => -$item['Quantidade'],
                    'resultante' => $stmtResultante->fetchColumn(),
                ]);
        }

        $stmt = $pdo->prepare("INSERT INTO Pedido (FKUsuario, Status, Simulacao, ValorSubtotal, ValorDesconto, ValorFrete, FreteTransportadora, FreteServico, FretePrazoDias, ValorTotal, FKCupom, EnderecoCep, EnderecoLogradouro, EnderecoNumero, EnderecoComplemento, EnderecoBairro, EnderecoCidade, EnderecoUF)
            VALUES (:usuario, 'aguardando_pagamento', :simulacao, :subtotal, :desconto, :frete, :transportadora, :servico, :prazo, :total, :cupom, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :uf)");
        $stmt->execute([
            'usuario' => $idUsuario,
            'simulacao' => $ehSimulacao ? 1 : 0,
            'subtotal' => $subtotal,
            'desconto' => $desconto,
            'frete' => $frete,
            'transportadora' => $freteInfo['transportadora'] ?? null,
            'servico' => $freteInfo['servico'] ?? null,
            'prazo' => $freteInfo['prazo_dias'] ?? null,
            'total' => $total,
            'cupom' => $cupom['IDCupom'] ?? null,
            'cep' => $endereco['cep'],
            'logradouro' => $endereco['logradouro'],
            'numero' => $endereco['numero'],
            'complemento' => ($endereco['complemento'] ?? '') !== '' ? $endereco['complemento'] : null,
            'bairro' => ($endereco['bairro'] ?? '') !== '' ? $endereco['bairro'] : null,
            'cidade' => $endereco['cidade'],
            'uf' => $endereco['uf'],
        ]);
        $idPedido = (int) $pdo->lastInsertId();

        foreach ($itens as $item) {
            $stmt = $pdo->prepare("INSERT INTO ItemPedido (IDItemPedido, FKPedido, FKVariacao, NomeProduto, DescricaoVariacao, Quantidade, PrecoUnitario) VALUES (:id, :pedido, :variacao, :nome, :descricao, :quantidade, :preco)");
            $stmt->execute([
                'id' => gerarUuid(),
                'pedido' => $idPedido,
                'variacao' => $item['IDVariacao'],
                'nome' => $item['variacao']['NomeProduto'],
                'descricao' => descricaoVariacao($item['variacao']),
                'quantidade' => $item['Quantidade'],
                'preco' => $item['variacao']['Preco'],
            ]);
        }

        $pdo->prepare("INSERT INTO HistoricoStatusPedido (IDHistorico, FKPedido, StatusAnterior, StatusNovo) VALUES (:id, :pedido, NULL, 'aguardando_pagamento')")
            ->execute(['id' => gerarUuid(), 'pedido' => $idPedido]);

        if ($cupom) {
            $pdo->prepare("UPDATE Cupom SET UsosAtuais = UsosAtuais + 1 WHERE IDCupom = :id")->execute(['id' => $cupom['IDCupom']]);
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Erro ao criar pedido: ' . $e->getMessage());
        return ['sucesso' => false, 'erro' => 'Não foi possível concluir o pedido. Tente novamente em instantes.'];
    }

    limparCarrinho();
    return ['sucesso' => true, 'id_pedido' => $idPedido];
}

function obterPedidosPorUsuario($idUsuario) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM Pedido WHERE FKUsuario = :u ORDER BY IDPedido DESC");
    $stmt->execute(['u' => $idUsuario]);
    return $stmt->fetchAll();
}

function obterPedidoPorId($idPedido) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT p.*, u.Nome AS NomeCliente, u.Email AS EmailCliente
        FROM Pedido p JOIN Usuario u ON u.IDUsuario = p.FKUsuario WHERE p.IDPedido = :id");
    $stmt->execute(['id' => $idPedido]);
    $pedido = $stmt->fetch();
    return $pedido ?: null;
}

function obterItensPedido($idPedido) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM ItemPedido WHERE FKPedido = :id");
    $stmt->execute(['id' => $idPedido]);
    return $stmt->fetchAll();
}

function obterHistoricoPedido($idPedido) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM HistoricoStatusPedido WHERE FKPedido = :id ORDER BY MomentoMudanca ASC");
    $stmt->execute(['id' => $idPedido]);
    return $stmt->fetchAll();
}

// Muda o status e grava no histórico numa transação só. Cancelar devolve o estoque reservado na
// criação do pedido pra cada item (nunca desconta 2x, StatusAtual !== 'cancelado' já garante que
// só devolve uma vez).
function mudarStatusPedido($idPedido, $novoStatus) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT Status FROM Pedido WHERE IDPedido = :id");
    $stmt->execute(['id' => $idPedido]);
    $statusAtual = $stmt->fetchColumn();
    if ($statusAtual === false || $statusAtual === $novoStatus) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE Pedido SET Status = :status WHERE IDPedido = :id")
            ->execute(['status' => $novoStatus, 'id' => $idPedido]);
        $pdo->prepare("INSERT INTO HistoricoStatusPedido (IDHistorico, FKPedido, StatusAnterior, StatusNovo) VALUES (:id, :pedido, :anterior, :novo)")
            ->execute(['id' => gerarUuid(), 'pedido' => $idPedido, 'anterior' => $statusAtual, 'novo' => $novoStatus]);

        if ($novoStatus === 'cancelado') {
            $stmtItens = $pdo->prepare("SELECT FKVariacao, Quantidade FROM ItemPedido WHERE FKPedido = :id");
            $stmtItens->execute(['id' => $idPedido]);
            foreach ($stmtItens->fetchAll() as $item) {
                if ($item['FKVariacao'] !== null) {
                    $pdo->prepare("UPDATE VariacaoProduto SET Estoque = Estoque + :qtd WHERE IDVariacao = :id")
                        ->execute(['qtd' => $item['Quantidade'], 'id' => $item['FKVariacao']]);
                    $stmtResultante = $pdo->prepare("SELECT Estoque FROM VariacaoProduto WHERE IDVariacao = :id");
                    $stmtResultante->execute(['id' => $item['FKVariacao']]);
                    $pdo->prepare("INSERT INTO MovimentoEstoque (IDMovimento, FKVariacao, Tipo, Quantidade, EstoqueResultante, Motivo) VALUES (:id, :variacao, 'cancelamento', :qtd, :resultante, :motivo)")
                        ->execute([
                            'id' => gerarUuid(),
                            'variacao' => $item['FKVariacao'],
                            'qtd' => $item['Quantidade'],
                            'resultante' => $stmtResultante->fetchColumn(),
                            'motivo' => 'Pedido #' . $idPedido . ' cancelado',
                        ]);
                }
            }
        }

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Erro ao mudar status do pedido: ' . $e->getMessage());
        return false;
    }
}

// ---------------------------------------------------------------------
// Simulação — Admin > Simulação usa 1 cliente de teste reaproveitado (não cria um novo a cada
// clique), pra deixar o admin passar pelo fluxo de compra de verdade sem misturar com cliente real.
// ---------------------------------------------------------------------

function obterOuCriarClienteSimulacao() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM Usuario WHERE Simulacao = 1 LIMIT 1");
    $cliente = $stmt->fetch();
    if ($cliente) {
        return $cliente;
    }

    $id = gerarUuid();
    $stmt = $pdo->prepare("INSERT INTO Usuario (IDUsuario, Nome, Email, Senha, TipoUsuario, Simulacao) VALUES (:id, :nome, :email, :senha, 'cliente', 1)");
    $stmt->execute([
        'id' => $id,
        'nome' => 'Cliente de Simulação',
        'email' => 'simulacao-' . substr($id, 0, 8) . '@interno.loja',
        'senha' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
    ]);

    $stmt = $pdo->prepare("SELECT * FROM Usuario WHERE IDUsuario = :id");
    $stmt->execute(['id' => $id]);
    return $stmt->fetch();
}

// Cancela (restaura estoque) todo pedido de teste que ainda não tava cancelado e esvazia o
// carrinho — deixa o cliente de simulação limpo pra uma próxima rodada de teste, sem sobrar
// rastro de estoque puxado à toa.
function resetarDadosSimulacao($idClienteSimulacao) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT IDPedido FROM Pedido WHERE FKUsuario = :u AND Status != 'cancelado'");
    $stmt->execute(['u' => $idClienteSimulacao]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $idPedido) {
        mudarStatusPedido($idPedido, 'cancelado');
    }
    $pdo->prepare("DELETE FROM ItemCarrinho WHERE FKUsuario = :u")->execute(['u' => $idClienteSimulacao]);
}

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
            (SELECT Estoque FROM VariacaoProduto WHERE FKProduto = p.IDProduto ORDER BY Preco LIMIT 1) AS EstoqueVariacaoUnica,
            (SELECT Nome FROM Categoria WHERE IDCategoria = p.FKCategoria) AS NomeCategoria
        FROM Favorito f
        JOIN Produto p ON p.IDProduto = f.FKProduto
        WHERE f.FKUsuario = :u
        ORDER BY f.MomentoAdicionado DESC");
    $stmt->execute(['u' => $_SESSION['usuario_id']]);
    return $stmt->fetchAll();
}
