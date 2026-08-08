-- Adiciona "Cor" como 2º eixo na Bota Country Masculina, junto com a Numeração que já
-- existe. Algumas combinações ficam de propósito sem existir (39-Caramelo, 41-Marrom,
-- 41-Caramelo), pra continuar mostrando o aviso de "combinação não disponível".
-- Rode no phpMyAdmin do banco de produção — só mexe no produto Bota, não afeta o resto.

SET @id_bota = (SELECT IDProduto FROM Produto WHERE Nome = 'Bota Country Masculina' LIMIT 1);

UPDATE Produto SET NomeAtributo1 = 'Numeração', NomeAtributo2 = 'Cor' WHERE IDProduto = @id_bota;

DELETE FROM VariacaoProduto WHERE FKProduto = @id_bota;

INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES
(UUID(), @id_bota, '39', 'Marrom',   'BOTA-39-MARROM',   349.90, 4),
(UUID(), @id_bota, '39', 'Preto',    'BOTA-39-PRETO',    349.90, 3),
-- 39-Caramelo não existe
(UUID(), @id_bota, '40', 'Marrom',   'BOTA-40-MARROM',   349.90, 6),
(UUID(), @id_bota, '40', 'Preto',    'BOTA-40-PRETO',    349.90, 5),
(UUID(), @id_bota, '40', 'Caramelo', 'BOTA-40-CARAMELO', 369.90, 2),
-- 41-Marrom e 41-Caramelo não existem
(UUID(), @id_bota, '41', 'Preto',    'BOTA-41-PRETO',    349.90, 3);
