-- Substitui o catálogo inteiro por um catálogo de demonstração pra apresentar o projeto
-- pro cliente: 2 categorias-mãe (Vestuário, Acessórios), 3 subcategorias em cada, e 12
-- produtos (2 por subcategoria) com descrição e variações realistas.
--
-- Algumas combinações de eixo foram deixadas de propósito faltando (ex: Camisa Xadrez
-- Country não tem Verde no P nem no GG) — mostra o filtro inteligente de variação
-- desabilitando combinação inexistente na página do produto.
--
-- Produtos entram sem foto (ImagemProduto fica vazio) — o site cai no ícone de
-- placeholder até subir fotos reais pela tela de edição no admin.
--
-- Rode direto no phpMyAdmin do banco de produção (ou local, pra testar antes).

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM ItemCarrinho;
DELETE FROM Favorito;
DELETE FROM ImagemProduto;
DELETE FROM VariacaoProduto;
DELETE FROM Produto;
DELETE FROM Categoria;

SET FOREIGN_KEY_CHECKS = 1;

-- Categorias-mãe
SET @cat_vestuario  = UUID();
SET @cat_acessorios = UUID();

INSERT INTO Categoria (IDCategoria, Nome, FKCategoriaPai, Ordem) VALUES
(@cat_vestuario,  'Vestuário',  NULL, 0),
(@cat_acessorios, 'Acessórios', NULL, 1);

-- Subcategorias de Vestuário
SET @cat_camisas  = UUID();
SET @cat_calcas   = UUID();
SET @cat_jaquetas = UUID();

INSERT INTO Categoria (IDCategoria, Nome, FKCategoriaPai, Ordem) VALUES
(@cat_camisas,  'Camisas',      @cat_vestuario, 0),
(@cat_calcas,   'Calças Jeans', @cat_vestuario, 1),
(@cat_jaquetas, 'Jaquetas',     @cat_vestuario, 2);

-- Subcategorias de Acessórios
SET @cat_chapeus = UUID();
SET @cat_cintos  = UUID();
SET @cat_botas   = UUID();

INSERT INTO Categoria (IDCategoria, Nome, FKCategoriaPai, Ordem) VALUES
(@cat_chapeus, 'Chapéus', @cat_acessorios, 0),
(@cat_cintos,  'Cintos',  @cat_acessorios, 1),
(@cat_botas,   'Botas',   @cat_acessorios, 2);

-- =======================================================================
-- VESTUÁRIO / Camisas
-- =======================================================================

-- Camisa Xadrez Country Manga Longa — Cor x Tamanho (Verde não existe no P nem no GG)
SET @prod_cx = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_cx, 'Camisa Xadrez Country Manga Longa',
 'Camisa manga longa em algodão xadrez, corte tradicional country com botões de pressão no punho e bolso frontal. Conforto pro dia a dia e presença nas rodas de música sertaneja.',
 @cat_camisas, 1, 'Cor', 'Tamanho');
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_cx, 'Vermelho', 'P',  'CX-VM-P',  129.90, 10),
(UUID(), @prod_cx, 'Vermelho', 'M',  'CX-VM-M',  129.90, 15),
(UUID(), @prod_cx, 'Vermelho', 'G',  'CX-VM-G',  129.90, 15),
(UUID(), @prod_cx, 'Vermelho', 'GG', 'CX-VM-GG', 129.90, 6),
(UUID(), @prod_cx, 'Azul',     'P',  'CX-AZ-P',  129.90, 12),
(UUID(), @prod_cx, 'Azul',     'M',  'CX-AZ-M',  129.90, 18),
(UUID(), @prod_cx, 'Azul',     'G',  'CX-AZ-G',  129.90, 10),
(UUID(), @prod_cx, 'Azul',     'GG', 'CX-AZ-GG', 129.90, 4),
(UUID(), @prod_cx, 'Verde',    'M',  'CX-VD-M',  129.90, 8),
(UUID(), @prod_cx, 'Verde',    'G',  'CX-VD-G',  129.90, 5);

-- Camisa Jeans Western Bordada — Cor x Tamanho (Azul Escuro não existe no P)
SET @prod_cjb = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_cjb, 'Camisa Jeans Western Bordada',
 'Camisa jeans com bordado western no peito e acabamento em pesponto contrastante. Peça statement pra quem gosta de um visual autêntico e resistente.',
 @cat_camisas, 1, 'Cor', 'Tamanho');
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_cjb, 'Azul Claro',  'P',  'CJB-AC-P',  159.90, 6),
(UUID(), @prod_cjb, 'Azul Claro',  'M',  'CJB-AC-M',  159.90, 9),
(UUID(), @prod_cjb, 'Azul Claro',  'G',  'CJB-AC-G',  159.90, 7),
(UUID(), @prod_cjb, 'Azul Claro',  'GG', 'CJB-AC-GG', 159.90, 3),
(UUID(), @prod_cjb, 'Azul Escuro', 'M',  'CJB-AE-M',  159.90, 10),
(UUID(), @prod_cjb, 'Azul Escuro', 'G',  'CJB-AE-G',  159.90, 8),
(UUID(), @prod_cjb, 'Azul Escuro', 'GG', 'CJB-AE-GG', 159.90, 2);

-- =======================================================================
-- VESTUÁRIO / Calças Jeans
-- =======================================================================

-- Calça Jeans Boot Cut Country — Cor x Numeração (Preto só em 40/42)
SET @prod_cbc = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_cbc, 'Calça Jeans Boot Cut Country',
 'Calça jeans modelagem boot cut, folgada na barra pra vestir por cima da bota sem amassar. Bolsos reforçados e cós médio.',
 @cat_calcas, 1, 'Cor', 'Numeração');
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_cbc, 'Azul',  '38', 'CBC-AZ-38', 189.90, 5),
(UUID(), @prod_cbc, 'Azul',  '40', 'CBC-AZ-40', 189.90, 12),
(UUID(), @prod_cbc, 'Azul',  '42', 'CBC-AZ-42', 189.90, 9),
(UUID(), @prod_cbc, 'Azul',  '44', 'CBC-AZ-44', 189.90, 4),
(UUID(), @prod_cbc, 'Preto', '40', 'CBC-PT-40', 189.90, 7),
(UUID(), @prod_cbc, 'Preto', '42', 'CBC-PT-42', 189.90, 6);

-- Calça Jeans Slim Western — 1 eixo (Numeração) — cor Azul Escuro fixa, produto de eixo único
SET @prod_csw = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_csw, 'Calça Jeans Slim Western',
 'Calça jeans slim com elastano, liberdade de movimento sem abrir mão do estilo country. Caimento mais justo do joelho pra baixo, cor azul escuro.',
 @cat_calcas, 1, 'Numeração', NULL);
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_csw, '38', NULL, 'CSW-38', 179.90, 3),
(UUID(), @prod_csw, '40', NULL, 'CSW-40', 179.90, 10),
(UUID(), @prod_csw, '42', NULL, 'CSW-42', 179.90, 8),
(UUID(), @prod_csw, '44', NULL, 'CSW-44', 179.90, 5);

-- =======================================================================
-- VESTUÁRIO / Jaquetas
-- =======================================================================

-- Jaqueta Jeans Western — 1 eixo (Tamanho) — cor Azul fixa
SET @prod_jjw = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_jjw, 'Jaqueta Jeans Western',
 'Jaqueta jeans clássica com recorte western nos ombros e botões de metal envelhecido. Curinga pra qualquer look, do casual ao evento country, cor azul.',
 @cat_jaquetas, 1, 'Tamanho', NULL);
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_jjw, 'P',  NULL, 'JJW-P',  249.90, 4),
(UUID(), @prod_jjw, 'M',  NULL, 'JJW-M',  249.90, 9),
(UUID(), @prod_jjw, 'G',  NULL, 'JJW-G',  249.90, 7),
(UUID(), @prod_jjw, 'GG', NULL, 'JJW-GG', 249.90, 2);

-- Jaqueta de Couro Country — Cor x Tamanho (Marrom não existe no P, Preto não existe no GG)
SET @prod_jcc = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_jcc, 'Jaqueta de Couro Country',
 'Jaqueta em couro legítimo com forro interno e acabamento artesanal. Peça durável, feita pra acompanhar anos de estrada.',
 @cat_jaquetas, 1, 'Cor', 'Tamanho');
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_jcc, 'Marrom', 'M',  'JCC-MR-M',  399.90, 5),
(UUID(), @prod_jcc, 'Marrom', 'G',  'JCC-MR-G',  399.90, 6),
(UUID(), @prod_jcc, 'Marrom', 'GG', 'JCC-MR-GG', 399.90, 2),
(UUID(), @prod_jcc, 'Preto',  'P',  'JCC-PT-P',  399.90, 3),
(UUID(), @prod_jcc, 'Preto',  'M',  'JCC-PT-M',  399.90, 7),
(UUID(), @prod_jcc, 'Preto',  'G',  'JCC-PT-G',  399.90, 4);

-- =======================================================================
-- ACESSÓRIOS / Chapéus
-- =======================================================================

-- Chapéu de Feltro Texano — Cor x Tamanho (Marrom não existe no P)
SET @prod_cft = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_cft, 'Chapéu de Feltro Texano',
 'Chapéu de feltro com aba curvada e faixa de couro, o clássico texano que não sai de moda. Estrutura firme que mantém a forma mesmo com uso frequente.',
 @cat_chapeus, 1, 'Cor', 'Tamanho');
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_cft, 'Preto',  'P', 'CFT-PT-P', 149.90, 6),
(UUID(), @prod_cft, 'Preto',  'M', 'CFT-PT-M', 149.90, 11),
(UUID(), @prod_cft, 'Preto',  'G', 'CFT-PT-G', 149.90, 5),
(UUID(), @prod_cft, 'Marrom', 'M', 'CFT-MR-M', 149.90, 8),
(UUID(), @prod_cft, 'Marrom', 'G', 'CFT-MR-G', 149.90, 3);

-- Chapéu de Palha Rodeio — 1 eixo (Cor), tamanho único
SET @prod_cpr = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_cpr, 'Chapéu de Palha Rodeio',
 'Chapéu de palha trançada à mão, leve e ventilado — parceiro ideal pros dias quentes de rodeio ou fazenda. Tamanho único, ajuste interno regulável.',
 @cat_chapeus, 1, 'Cor', NULL);
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_cpr, 'Natural',  NULL, 'CPR-NT', 89.90, 14),
(UUID(), @prod_cpr, 'Caramelo', NULL, 'CPR-CM', 89.90, 9);

-- =======================================================================
-- ACESSÓRIOS / Cintos
-- =======================================================================

-- Cinto de Couro Legítimo Fivela Prateada — Cor x Tamanho
SET @prod_tfp = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_tfp, 'Cinto de Couro Legítimo Fivela Prateada',
 'Cinto em couro legítimo com fivela prateada em relevo, acabamento que aguenta o uso diário sem perder o brilho.',
 @cat_cintos, 1, 'Cor', 'Tamanho');
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_tfp, 'Marrom', '90',  'TFP-MR-90',  99.90, 7),
(UUID(), @prod_tfp, 'Marrom', '95',  'TFP-MR-95',  99.90, 10),
(UUID(), @prod_tfp, 'Marrom', '100', 'TFP-MR-100', 99.90, 6),
(UUID(), @prod_tfp, 'Preto',  '90',  'TFP-PT-90',  99.90, 9),
(UUID(), @prod_tfp, 'Preto',  '95',  'TFP-PT-95',  99.90, 12),
(UUID(), @prod_tfp, 'Preto',  '100', 'TFP-PT-100', 99.90, 4);

-- Cinto Trançado Artesanal — 1 eixo (Tamanho) — cor Caramelo fixa
SET @prod_tta = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_tta, 'Cinto Trançado Artesanal',
 'Cinto trançado à mão em couro caramelo, trabalho artesanal que dá um toque único a qualquer produção.',
 @cat_cintos, 1, 'Tamanho', NULL);
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_tta, '90',  NULL, 'TTA-90',  79.90, 5),
(UUID(), @prod_tta, '95',  NULL, 'TTA-95',  79.90, 8),
(UUID(), @prod_tta, '100', NULL, 'TTA-100', 79.90, 3);

-- =======================================================================
-- ACESSÓRIOS / Botas
-- =======================================================================

-- Bota Country Bico Fino — Cor x Numeração (Preto não existe em 38 nem 42)
SET @prod_bcb = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_bcb, 'Bota Country Bico Fino',
 'Bota em couro legítimo com bico fino e sola de borracha antiderrapante. Elegância e resistência pra quem não abre mão do estilo country.',
 @cat_botas, 1, 'Cor', 'Numeração');
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_bcb, 'Marrom', '38', 'BCB-MR-38', 349.90, 3),
(UUID(), @prod_bcb, 'Marrom', '39', 'BCB-MR-39', 349.90, 6),
(UUID(), @prod_bcb, 'Marrom', '40', 'BCB-MR-40', 349.90, 9),
(UUID(), @prod_bcb, 'Marrom', '41', 'BCB-MR-41', 349.90, 5),
(UUID(), @prod_bcb, 'Marrom', '42', 'BCB-MR-42', 349.90, 2),
(UUID(), @prod_bcb, 'Preto',  '39', 'BCB-PT-39', 349.90, 4),
(UUID(), @prod_bcb, 'Preto',  '40', 'BCB-PT-40', 349.90, 8),
(UUID(), @prod_bcb, 'Preto',  '41', 'BCB-PT-41', 349.90, 6);

-- Bota Texana Cano Curto — 1 eixo (Numeração) — cor Caramelo fixa
SET @prod_btc = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_btc, 'Bota Texana Cano Curto',
 'Bota texana de cano curto, bordado lateral e salto médio — o clássico do vestuário country numa versão mais fácil pro dia a dia, cor caramelo.',
 @cat_botas, 1, 'Numeração', NULL);
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_btc, '37', NULL, 'BTC-37', 299.90, 2),
(UUID(), @prod_btc, '38', NULL, 'BTC-38', 299.90, 5),
(UUID(), @prod_btc, '39', NULL, 'BTC-39', 299.90, 9),
(UUID(), @prod_btc, '40', NULL, 'BTC-40', 299.90, 6),
(UUID(), @prod_btc, '41', NULL, 'BTC-41', 299.90, 3);
