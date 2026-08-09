-- 3 caixas de envio iniciais (P/M/G), com peso e dimensão só de referência pra roupa/acessório
-- leve — ajuste os números reais pela tela Admin > Entregas depois, isso aqui é só ponto de
-- partida pra não começar com a lista vazia. Rode direto no phpMyAdmin (produção ou local).

INSERT INTO CaixaEnvio (IDCaixaEnvio, Nome, Peso, Altura, Largura, Comprimento) VALUES
(UUID(), 'Caixa P', 0.300, 10, 15, 20),  -- cinto, chapéu dobrado, carteira
(UUID(), 'Caixa M', 0.600, 12, 25, 30),  -- camisa, calça dobrada
(UUID(), 'Caixa G', 1.500, 20, 30, 40);  -- bota, jaqueta de couro
