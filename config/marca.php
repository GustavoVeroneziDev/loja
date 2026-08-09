<?php
/**
 * A "pele" desta loja, em arquivo — não vem mais de banco de dados.
 * Pra clonar pra um cliente novo: edita esse arquivo (e as cores em geral/estilo.css).
 */

define('NOME_LOJA', 'US Country');
define('LOGO_URL', '/geral/img/uscountry-logosolo.svg');
define('FAVICON_URL', '/geral/img/uscountry-logosolo.ico');
define('TEXTO_SOBRE', 'Conte aqui a história da sua loja.');
define('TEXTO_POLITICA_TROCA', 'Descreva aqui a política de trocas e devoluções.');
define('TEXTO_CONTATO', 'contato@minhaloja.com.br');
define('TELEFONE_CONTATO', '(11) 99999-9999');
define('WHATSAPP_NUMERO', '5511999999999');
define('INSTAGRAM_URL', 'https://instagram.com/minhaloja');
define('FACEBOOK_URL', 'https://facebook.com/minhaloja');

// Frete provisório — calcularFrete() em funcoes.php isola essa regra; trocar por um provedor de
// verdade (Melhor Envio etc.) depois não muda quem chama a função, só o corpo dela.
define('FRETE_VALOR_PADRAO', 19.90);
define('FRETE_GRATIS_ACIMA_DE', 300.00);

// A partir de quantas unidades avisa "acabando" — usado quando a variação não tem
// EstoqueMinimo próprio definido.
define('ESTOQUE_MINIMO_PADRAO', 5);
