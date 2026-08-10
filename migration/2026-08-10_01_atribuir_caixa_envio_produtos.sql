-- Atribui a caixa de envio (P/M/G) de cada produto do catálogo demo, por categoria/tipo — sem
-- isso a cotação real do Melhor Envio aborta o carrinho inteiro e cai no frete fixo (falta
-- peso/dimensão). Rode direto no phpMyAdmin de produção, depois que 2026-08-09_01 já rodou lá
-- (essa aqui casa pelo Nome da caixa, não pelo ID, porque UUID() gera um ID diferente em cada
-- ambiente onde aquele script roda).

UPDATE Produto SET FKCaixaEnvio = (SELECT IDCaixaEnvio FROM CaixaEnvio WHERE Nome = 'Caixa M')
WHERE Nome IN (
    'Camisa Xadrez Country Manga Longa',
    'Camisa Jeans Western Bordada',
    'Calça Jeans Boot Cut Country',
    'Calça Jeans Slim Western',
    'Chapéu de Feltro Texano',
    'Chapéu de Palha Rodeio'
);

UPDATE Produto SET FKCaixaEnvio = (SELECT IDCaixaEnvio FROM CaixaEnvio WHERE Nome = 'Caixa P')
WHERE Nome IN (
    'Cinto de Couro Legítimo Fivela Prateada',
    'Cinto Trançado Artesanal'
);

UPDATE Produto SET FKCaixaEnvio = (SELECT IDCaixaEnvio FROM CaixaEnvio WHERE Nome = 'Caixa G')
WHERE Nome IN (
    'Bota Country Bico Fino',
    'Bota Texana Cano Curto',
    'Jaqueta de Couro Country',
    'Jaqueta Jeans Western'
);
