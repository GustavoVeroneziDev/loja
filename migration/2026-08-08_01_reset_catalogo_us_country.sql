-- Reseta o catálogo (produtos, variações, imagens, categorias) e recria com 5 categorias
-- e 5 produtos combinando com a marca US Country. Rode isso direto no phpMyAdmin do
-- banco de produção (ou local, pra testar antes).
--
-- Alguns produtos já vêm com "grupo de variações" configurado (Cor, Tamanho, Numeração) —
-- a Camisa Xadrez Western tem Cor x Tamanho de propósito com uma combinação faltando
-- (Vermelha no P não existe), pra você ver o aviso de "essa combinação não está disponível"
-- funcionando na prática.
--
-- Os produtos entram sem foto (ImagemProduto fica vazio) — o site já cai sozinho no
-- ícone de placeholder até você subir fotos reais pela tela de edição no admin.

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM ItemCarrinho;
DELETE FROM Favorito;
DELETE FROM ImagemProduto;
DELETE FROM VariacaoProduto;
DELETE FROM Produto;
DELETE FROM Categoria;

SET FOREIGN_KEY_CHECKS = 1;

-- Categorias
SET @cat_relogios  = UUID();
SET @cat_camisetas = UUID();
SET @cat_bones     = UUID();
SET @cat_cintos    = UUID();
SET @cat_botas     = UUID();

INSERT INTO Categoria (IDCategoria, Nome, FKCategoriaPai, Ordem) VALUES
(@cat_relogios,  'Relógios',  NULL, 0),
(@cat_camisetas, 'Camisetas', NULL, 1),
(@cat_bones,     'Bonés',     NULL, 2),
(@cat_cintos,    'Cintos',    NULL, 3),
(@cat_botas,     'Botas',     NULL, 4);

-- Relógio Boston Country — 1 eixo (Cor: Preto / Azul)
SET @prod_relogio = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_relogio, 'Relógio Boston Country',
 'Relógio analógico com pulseira de couro sintético, resistente à água. Estilo clássico western.',
 @cat_relogios, 1, 'Cor', NULL);
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_relogio, 'Preto', NULL, 'REL-BOSTON-PRETO', 249.90, 12),
(UUID(), @prod_relogio, 'Azul',  NULL, 'REL-BOSTON-AZUL',  249.90, 8);

-- Camisa Xadrez Western — 2 eixos (Cor x Tamanho) — Vermelha no P não existe de propósito
SET @prod_camisa = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_camisa, 'Camisa Xadrez Western',
 'Camisa de manga longa em algodão, estampa xadrez, botões de pressão estilo country.',
 @cat_camisetas, 1, 'Cor', 'Tamanho');
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_camisa, 'Azul',     'P', 'CAM-AZUL-P',      129.90, 15),
(UUID(), @prod_camisa, 'Azul',     'M', 'CAM-AZUL-M',      129.90, 20),
(UUID(), @prod_camisa, 'Azul',     'G', 'CAM-AZUL-G',      129.90, 10),
-- 'Vermelha' + 'P' não existe — mostra o aviso de combinação indisponível na página do produto
(UUID(), @prod_camisa, 'Vermelha', 'M', 'CAM-VERMELHA-M',  134.90, 9),
(UUID(), @prod_camisa, 'Vermelha', 'G', 'CAM-VERMELHA-G',  134.90, 6);

-- Boné Trucker US Country — sem eixo, produto simples (1 variação padrão)
SET @prod_bone = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo) VALUES
(@prod_bone, 'Boné Trucker US Country',
 'Boné trucker com tela respirável e fivela ajustável, bordado da marca.',
 @cat_bones, 1);
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, SKU, Preco, Estoque) VALUES
(UUID(), @prod_bone, 'BONE-TRUCKER', 79.90, 25);

-- Cinto de Couro Legítimo — 1 eixo (Cor: Marrom / Preto)
SET @prod_cinto = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_cinto, 'Cinto de Couro Legítimo',
 'Cinto de couro legítimo com fivela em metal envelhecido.',
 @cat_cintos, 1, 'Cor', NULL);
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_cinto, 'Marrom', NULL, 'CIN-COURO-MARROM', 99.90, 18),
(UUID(), @prod_cinto, 'Preto',  NULL, 'CIN-COURO-PRETO',  99.90, 14);

-- Bota Country Masculina — 1 eixo (Numeração: 39 / 40 / 41)
SET @prod_bota = UUID();
INSERT INTO Produto (IDProduto, Nome, Descricao, FKCategoria, Ativo, NomeAtributo1, NomeAtributo2) VALUES
(@prod_bota, 'Bota Country Masculina',
 'Bota em couro sintético, cano curto, sola emborrachada antiderrapante.',
 @cat_botas, 1, 'Numeração', NULL);
INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @prod_bota, '39', NULL, 'BOTA-COUNTRY-39', 349.90, 4),
(UUID(), @prod_bota, '40', NULL, 'BOTA-COUNTRY-40', 349.90, 6),
(UUID(), @prod_bota, '41', NULL, 'BOTA-COUNTRY-41', 349.90, 3);
