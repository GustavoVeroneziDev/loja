# Instruções do Projeto — Base de Site de Vendas (Loja Física, Template Clonável)

Este arquivo tem duas partes: **(1)** o perfil de como eu (Gustavo) gosto de trabalhar e o estilo técnico/estético que eu quero que você replique aqui — extraído de um projeto real meu (Auralis, um SaaS de gestão financeira) — e **(2)** a especificação do que este projeto específico precisa ser. Trate a Parte 1 como regras permanentes de como codar comigo. Trate a Parte 2 como o briefing do que construir agora.

Sou o dono do produto, não sou desenvolvedor de formação — decido rumo de negócio e UX, mas deixo as decisões técnicas de implementação com você. Fale comigo em português, direto ao ponto, sem enrolação.

---

## Parte 1 — Meu Estilo (regras permanentes)

### 1.1 Stack e filosofia geral

- **PHP puro, orientado a procedural/funcional, sem framework.** Nada de Laravel, Symfony, Slim etc. PDO puro pra banco, sessões nativas do PHP (`session_start()`) pra autenticação. A lógica de negócio mora em funções dentro de um (ou poucos) arquivo(s) `config/funcoes.php` compartilhado — não crio uma classe/arquivo pra cada responsabilidade pequena. Simplicidade e "dá pra entender lendo de cima a baixo" ganham de arquitetura em camadas.
- **MySQL/MariaDB** como banco, sempre acessado via PDO com prepared statements. Nunca concatenar variável direto numa query.
- **Front-end sem framework JS.** Bootstrap 5.3 (modo escuro nativo) pra UI, JavaScript vanilla (ES6+) pra interatividade — sem React/Vue/build step. Scripts ficam em `<script>` inline no fim da página que os usa, não em arquivos `.js` separados por padrão (só separo se for reaproveitado em várias páginas).
- **Sem processo de build.** O que está no repositório é o que roda em produção — sem webpack, sem transpilação, sem `npm run build`. Isso é decisão deliberada, não limitação: o deploy é simples (ver 1.7) e eu quero poder editar um arquivo e já ver o resultado.
- Só adiciono uma biblioteca externa quando resolve um problema real e específico (ex: Chart.js pra gráfico, Cleave.js pra máscara de input). Não trago dependência "por via das dúvidas".

### 1.2 Estrutura de arquivos

Organizo por **feature/domínio em pastas**, não por tipo de arquivo:

```text
/projeto
├── admin/              # Painel administrativo — uma página por seção de gestão
├── config/              # conexao.php (PDO + fuso), funcoes.php (lógica de negócio), chaves/segredos
├── cron/                 # Jobs agendados (PHP CLI), cada um documentado no topo com o comando pro cPanel
├── geral/                # Header, footer, CSS/imagens compartilhadas, landing page
├── usuario/              # Autenticação: login, cadastro, recuperação de senha, SSO
├── vendor/                # Dependências Composer — COMMITADO (ver 1.7, não roda composer install em produção)
├── dashboard.php          # Página central logada
└── <feature>.php          # Páginas de topo (uma por funcionalidade principal), nome direto em português
```

Dentro de uma feature grande (ex: um módulo de carrinho, de pedidos), crio uma subpasta própria (`carrinho/`, `pedido/`) com um `index.php` e páginas de detalhe/ação ao lado — meu padrão pra ações são **POST na própria página, com `$_POST['action']` decidindo o que fazer**, redirecionando de volta com `?ok=1`/`?erro=1` no fim (ver 1.5).

Não crio uma pasta `models/`, `controllers/`, `services/` — a "camada" é a própria pasta de feature + as funções compartilhadas em `config/funcoes.php`.

### 1.3 Convenção de nomes

**Banco de dados** — tudo em português, `PascalCase`:

- Tabelas: substantivo singular (`Usuario`, `Produto`, `Pedido`, `Categoria`).
- Colunas: `PascalCase` também. Chave primária é sempre `ID<NomeDaTabela>` (`IDUsuario`, `IDProduto`). Chave estrangeira é `FK<NomeDaTabela>` (`FKUsuario`, `FKProduto`). Timestamps de "quando aconteceu de verdade" usam `Momento<Coisa>` (`MomentoRegistro`), datas-alvo/vencimento usam `Data<Coisa>` (`DataVencimento`, `DataEntrega`). Colunas de status são um `ENUM` chamado `Status<Coisa>` ou só `Status`.
- Chave primária é **UUID** (`CHAR(36)`, gerado em PHP por uma função `gerarUuid()`), não `AUTO_INCREMENT` — exceto quando a numeração sequencial em si é o valor de negócio (ex: um número de pedido visível pro cliente, tipo "Pedido #00042"), aí uso `AUTO_INCREMENT` de propósito porque resolve concorrência sozinho.

**Funções PHP** — `camelCase`, verbo primeiro, em português, e o nome já entrega a intenção:

- `garantirTabelaX()` / `garantirEstruturaX()` — migração defensiva (ver 1.4), sempre idempotente.
- `obterX()` — busca/leitura que pode ter fallback/default.
- `calcularX()` — cálculo puro.
- `processarX()` — orquestrador de regra de negócio (geralmente com efeito colateral no banco).
- `determinarX()` — decide entre caminhos possíveis.
- `criarX()` — criação de registro(s).
- `verificarX()` — checagem/validação, geralmente booleana ou com efeito colateral de correção automática.
- `concederX()` — concede algo (badge, bônus) de forma idempotente.
- Funções privadas de um arquivo específico levam `_` na frente (`_waRegistrar`, `_cc_diasNoMes`) — sinaliza "isso é interno desse arquivo, não é API pra reaproveitar em outro lugar".

**Front-end**: classes CSS customizadas em `kebab-case` (`.card-admin`, `.badge-pendente`), IDs de elemento em `camelCase` (`#modalAtribuir`, `#codigoParceiro`).

### 1.4 Banco de dados — auto-migração, nunca migration tradicional

**Nunca crio arquivo de migration separado.** Cada função que precisa de uma tabela ou coluna nova começa checando se ela existe (via `INFORMATION_SCHEMA.COLUMNS`/`TABLES`) e criando na hora com `ALTER TABLE`/`CREATE TABLE IF NOT EXISTS` — padrão `garantirTabelaX()`/`garantirEstruturaX()`, chamado defensivamente no topo de toda página que precisa daquilo. Isso existe porque o servidor de produção não tem acesso a terminal (ver 1.7) — não dá pra rodar comando nenhum lá, então o schema tem que se auto-atualizar sozinho no primeiro request depois do deploy.

Toda coluna nova em tabela existente ganha um `DEFAULT` que **reproduz o comportamento antigo** — nunca dou branch de "se a coluna existe faz X, se não existe faz Y" espalhado pelo código; garanto que a coluna sempre existe (rodando a função de migração) e uso um default seguro.

**Idempotência em upsert**: uso `INSERT ... ON DUPLICATE KEY UPDATE` **só** quando tenho certeza absoluta de que existe uma `UNIQUE KEY` real cobrindo aquela combinação de colunas. Quando não tenho certeza (é o caso mais comum numa tabela de configuração genérica tipo chave-valor), faço o padrão mais verboso e seguro: `SELECT` pra checar se existe, `UPDATE` se existir, `INSERT` se não — nunca confio em `ON DUPLICATE KEY UPDATE` "torcendo" pra key existir.

**Config genérica**: tenho (e recomendo sempre ter) uma tabela `ConfiguracaoSistema` (`Chave` VARCHAR, `Valor` TEXT, `FKUsuario` CHAR(36) NULL) — `FKUsuario IS NULL` significa "config global do sistema", preenchido significa "config daquele usuário específico". Reaproveito essa mesma tabela pra qualquer flag/valor configurável novo em vez de criar coluna nova em `Usuario` ou tabela nova toda hora.

### 1.5 Padrão de página com ação (POST) + redirect

Toda página que aceita ações do usuário segue o mesmo esqueleto:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'criar') { /* ... */ $sucesso = 'Criado com sucesso.'; }
    if ($action === 'editar') { /* ... */ }
    if ($action === 'excluir') { /* ... */ }
    header("Location: pagina.php" . ($sucesso ? "?ok=1" : "?erro=1")); exit;
}
$sucesso = isset($_GET['ok'])   ? 'Operação realizada com sucesso.' : null;
$erro    = isset($_GET['erro']) ? 'Ocorreu um erro. Tente novamente.' : null;
```

Se a página tem uma âncora relevante (uma seção específica que o usuário estava olhando), o redirect leva a âncora junto (`?ok=1#secao`) — sem isso o usuário sempre volta pro topo da página depois de qualquer ação, o que é uma fricção chata que eu não gosto.

### 1.6 Estética visual — tema escuro, cores com significado

- **Tema escuro por padrão**, cor de fundo de card em torno de `#1a1d27`, texto secundário em cinza (`text-secondary`/`#9ca3af`).
- Paleta com **significado semântico consistente**, não decorativo:
  - Verde (`#22c55e`/`#86efac`) = sucesso, ativo, pago, positivo.
  - Vermelho (`#ef4444`/`#fca5a5`/`#dc2626`) = perigo, urgente, excluir, negativo.
  - Âmbar/laranja (`#f59e0b`/`#fbbf24`) = atenção, pendente, aviso.
  - Dourado (`#d4af37`) = destaque/premium (era a cor do plano mais alto no Auralis) — uso pra "isso é especial" em geral.
  - Roxo (`#7c3aed`/`#a78bfa`) = seção administrativa/estrutural, navegação ativa.
- **Badges/pills**: `background: rgba(R,G,B,.15); color: #hex; border: 1px solid rgba(R,G,B,.3); border-radius: pill.` Sempre essa proporção de opacidade (fundo bem sutil, borda um pouco mais forte, texto sólido).
- **Cards**: `border-radius: 12px`, borda de `rgba(255,255,255,.08)`, nunca sombra pesada — tudo sutil.
- **Botões de ação**: `rounded-pill`, cor inline batendo com o esquema semântico acima em vez dos botões padrão do Bootstrap (`btn-primary` etc.) — o botão "parece" com o que ele representa.
- **Ícones**: Bootstrap Icons (`bi-*`) em quase todo lugar, coloridos pra bater com o contexto (não fico só em preto/cinza).
- **Escolha binária (A ou B)**: uso `btn-check` + `label.btn.btn-outline-secondary` (rádio disfarçado de botão), não dropdown, quando são só 2-3 opções.
- **Campo condicional**: mostro/escondo com JS vanilla trocando `classList.toggle('d-none', condicao)` — nunca escondo com `display:none` direto no CSS de forma que dependa de JS pra nunca aparecer (senão quebra sem JS).
- **Emoji**: uso em mensagens de WhatsApp/notificação (é convenção nativa desse canal), **não** uso em texto de UI dentro do site.

### 1.7 Mobile — pensar nisso desde o início, não depois

Já tomei mais de um bug chato por não pensar nisso cedo — quero que isso já venha certo desde a primeira versão:

- Todo `<input>`/`<select>` visível em telas `≤767.98px` tem `font-size: 16px` garantido (menor que isso, o Safari iOS dá zoom automático no foco e não volta sozinho).
- Filho de `display:flex` que pode truncar texto (nome, descrição) leva `min-width: 0` explícito — o default de `min-width` num flex-item é `min-content`, não `0`, e isso estoura layout em tela pequena sem aviso nenhum.
- Coluna de tabela com conteúdo variável (uma pill, um botão) recebe `width`/`min-width`/`max-width` idênticos direto no `<td>` — só `max-width` no elemento filho não é suficiente sob `table-layout: auto`, porque a largura da coluna é decidida pelo conteúdo mais largo entre TODAS as linhas da tabela, não só a linha atual.

### 1.8 Comentários e estilo de código

- **Comentário só quando explica o "porquê" não óbvio** — uma decisão que teria sido diferente sem um motivo específico, uma armadilha que já mordeu antes, uma limitação externa (ex: "sem SSH, por isso isso aqui"). Nunca comento o que o código já deixa claro sozinho.
- Sem abstração prematura. Três linhas parecidas em três lugares é melhor que uma função genérica errada de imaginar todos os casos futuros.
- `try { } catch (PDOException $e) { }` silencioso é aceitável em pontos não-críticos (uma migração defensiva que já existe, um log que não pode quebrar o fluxo principal) — mas nunca escondo erro de uma ação que o usuário está esperando confirmação (ali eu quero saber se falhou).
- Sem feature flag pra código velho "só por garantia" — quando troco uma abordagem, apago a antiga.

### 1.9 Fluxo de Git

- Depois de qualquer mudança funcionando: `git add` (dos arquivos relevantes) → commit com mensagem **em português, curta, resumindo o efeito** (prefixo convencional: `feat:`, `fix:`, `docs:`, `chore:`, `perf:`) → `git push`. Não deixo trabalho pronto parado sem commitar.
- Mensagem de commit foca no _efeito_/_motivo_, não numa lista mecânica de arquivos alterados.
- Nunca uso `git reset --hard`, `--force`, ou qualquer coisa destrutiva sem eu pedir explicitamente.

### 1.10 Segurança e dados

- Senha sempre com `password_hash()`/`password_verify()`, nunca hash caseiro.
- Toda query com dado de usuário é prepared statement com parâmetro nomeado — nunca interpolação de string.
- Exclusão de conta é definitiva e em cascata de verdade (apaga tudo relacionado, dentro de uma transação) — não deixo "soft delete" de dado sensível de cliente por padrão, a não ser que a lei/negócio peça retenção.

### 1.11 Produto — gosto de um toque de gamificação/delícia quando cabe

Não é obrigatório em tudo, mas quando fizer sentido pro produto, gosto de: badges/conquistas por marco de uso, ranking, notificação com personalidade (não só texto seco de sistema). Isso é mais "se fizer sentido pro produto de loja" do que uma regra técnica — cito aqui pra você saber que é algo que eu valorizo, não é over-engineering gratuito se vier a calhar (ex: "cliente fiel", badge de tantas compras).

---

## Parte 2 — O que construir agora: esqueleto de loja de produto físico, clonável por cliente

### 2.1 Contexto e objetivo

Quero uma **base de sistema de e-commerce de produto físico** que sirva de ponto de partida pra criar lojas com marca própria pra clientes diferentes. O modelo é **template clonável**: cada cliente novo = uma cópia própria do projeto, com seu próprio banco de dados e seu próprio deploy (não é multi-tenant — não tem "uma instalação com várias lojas dentro"; é "um molde que eu recorto pra cada cliente").

**Divisão esqueleto vs. pele:**

- **Esqueleto (construir agora, genérico, igual pra todo cliente):** banco de dados, autenticação, catálogo de produto com variação e estoque, carrinho, checkout, pagamento, gestão de pedido, conta do cliente, painel administrativo, e-mails transacionais, cálculo de frete.
- **Pele (não construir agora, entra depois por cliente):** logo, paleta de cores da marca, nome/identidade, textos institucionais (sobre, política de troca/devolução, contato), talvez a fonte. Tudo isso deve estar **centralizado e fácil de trocar** (ver 2.5), não deve estar espalhado/hardcoded no meio da lógica.

### 2.2 Funcionalidades do esqueleto

**Catálogo**

- Produto com nome, descrição, preço, categoria, imagens (múltiplas).
- Variação de produto (ex: tamanho, cor) — cada variação com seu próprio SKU e estoque próprio. Um produto sem variação usa uma variação "padrão" implícita, pra não duplicar lógica entre produto simples e produto variável.
- Controle de estoque por variação, com baixa automática na confirmação da venda e proteção contra vender abaixo de zero (checagem no momento do checkout, não só na criação do carrinho).
- Categoria de produto (hierarquia simples, categoria e sub-categoria).

**Carrinho e Checkout**

- Carrinho persistente por sessão (visitante) e por conta (cliente logado) — ao logar com carrinho de visitante, mescla em vez de perder.
- Checkout com: endereço de entrega (CEP com autopreenchimento via API de CEP), cálculo de frete (deixar isso abstraído atrás de uma função `calcularFrete()` — não travar num único provedor logo de cara, mas usar Correios/Melhor Envio como referência inicial), seleção de forma de pagamento.
- **Pagamento via Mercado Pago** (mesma integração que já uso no Auralis — reaproveito o padrão de webhook + confirmação ativa no redirect, idempotência por referência de pagamento).
- Cupom de desconto (percentual ou valor fixo, com validade e limite de uso).

**Pedido**

- Pedido com número sequencial visível (aqui sim uso `AUTO_INCREMENT`, é exatamente o caso de "a numeração em si é valor de negócio" citado na Parte 1).
- Status do pedido com fluxo claro (ex: `aguardando_pagamento` → `pago` → `preparando` → `enviado` → `entregue`, mais `cancelado`), com histórico de mudança de status guardado (não só o status atual — quero poder ver quando cada etapa aconteceu).
- Código de rastreio anexável ao pedido, e-mail automático pro cliente quando o status muda.

**Conta do cliente**

- Cadastro/login (mesmo padrão de sessão nativa + `password_hash` do Auralis), histórico de pedidos, endereços salvos (pode ter mais de um).
- Recuperação de senha por e-mail com token de uso único — mesmo padrão do Auralis.

**Painel administrativo**

- CRUD de produto/variação/categoria/estoque.
- Lista de pedidos com filtro por status, mudança de status, anexar rastreio.
- Configuração da loja (ver 2.5 — é aqui que entra a "pele" depois).
- Relatório simples: vendas por período, produtos mais vendidos.

### 2.3 Tabelas sugeridas (nomeando já no padrão da Parte 1.3)

```text
Cliente          (IDCliente, Nome, Email, Senha, Telefone, TokenRecuperacao...)
Endereco         (IDEndereco, FKCliente, CEP, Logradouro, Numero, Complemento, Cidade, UF, Principal)
Categoria        (IDCategoria, Nome, FKCategoriaPai NULL)
Produto          (IDProduto, Nome, Descricao, FKCategoria, Ativo)
VariacaoProduto  (IDVariacao, FKProduto, Nome/Atributo, SKU, Preco, Estoque)
ImagemProduto    (IDImagem, FKProduto, Url, Ordem)
Pedido           (IDPedido [auto-increment], FKCliente, Status, ValorTotal, ValorFrete, FKEndereco, CriadoEm)
ItemPedido       (IDItemPedido, FKPedido, FKVariacao, Quantidade, PrecoUnitario)
HistoricoStatusPedido (IDHistorico, FKPedido, StatusAnterior, StatusNovo, MomentoMudanca)
Cupom            (IDCupom, Codigo, TipoDesconto, ValorDesconto, DataValidade, LimiteUso, UsosAtuais)
ConfiguracaoLoja (Chave, Valor) — é a "pele": nome da loja, cores, logo, textos institucionais
```

### 2.4 Deploy — mesmo modelo do Auralis, a menos que eu diga outra hospedagem

- Hospedagem compartilhada **sem acesso SSH** — deploy automático via GitHub Actions + FTP ao dar `push` (`SamKirkland/FTP-Deploy-Action` é o que já uso). `vendor/` fica commitado no repositório (é a única forma de "instalar" dependência PHP sem terminal no servidor).
- Arquivos de credencial real (conexão com banco, chaves de API) ficam **fora do git** (`.gitignore`), com um arquivo de exemplo (`config/conexao.example.php`) commitado mostrando a estrutura esperada, já que cada clone-por-cliente terá seu próprio banco/credenciais.
- PHP e MySQL travados no mesmo fuso horário (`America/Sao_Paulo`, `SET time_zone = '-03:00'` na conexão) desde o primeiro dia — evita bug de "vence hoje" variando de dia perto da meia-noite (já bati nisso no Auralis).

### 2.5 Onde a "pele" vai entrar depois — deixe isso pronto pra receber

Pra cada cliente novo só trocar a pele sem mexer em lógica, desde já:

- Toda cor visual de marca (não as cores semânticas de status da Parte 1.6, essas continuam fixas) deve vir de **variáveis CSS centralizadas** num único arquivo (ex: `geral/marca.css` ou bloco de `:root{}` no topo do CSS principal), nunca hardcoded espalhado em `style="..."` inline pelas páginas de loja voltadas pro cliente final (o admin pode ser mais fixo, é meu, não do cliente).
- Nome da loja, logo (URL de imagem), textos institucionais (sobre/política de troca/contato) vêm da tabela `ConfiguracaoLoja` citada acima, lidos em runtime — nunca escritos direto no HTML.
- Favicon/PWA icons também pensados como arquivo substituível (`geral/img/logo.*`), não hardcoded.

### 2.6 Fora de escopo nesta entrega (anotar, não esquecer)

- Multi-tenant de verdade (várias marcas num só banco) — decisão explícita de NÃO fazer isso agora; é template clonável.
- Definição final do provedor de frete — usar uma função abstrata (`calcularFrete()`) até decidir.
- O tema visual/pele de qualquer cliente específico — isso vem depois, fora deste projeto-base.
