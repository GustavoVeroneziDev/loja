-- "Yellow Stone" ficou visível na loja com variação a R$ 0,00 (antes da validação existir).
-- Volta pra rascunho até o preço ser corrigido pela tela de edição no admin.
-- Rode no phpMyAdmin do banco de produção.

UPDATE Produto SET Ativo = 0 WHERE Nome = 'Yellow Stone';
