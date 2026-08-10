<?php
// Copie este arquivo para chaves.php e preencha com as credenciais reais.
// chaves.php fica fora do git (.gitignore) — cada clone/deploy tem sua própria integração.

// Melhor Envio — painel > Integrações > Tokens de Acesso > Gerar novo token. Token direto (JWT),
// sem Client ID/Secret, sem fluxo de autorização. Validade de ~1 ano; quando expirar, gera um novo
// no painel deles e substitui aqui.
define('MELHOR_ENVIO_AMBIENTE', 'sandbox'); // 'sandbox' ou 'producao' — precisa bater com onde o token foi gerado
define('MELHOR_ENVIO_TOKEN', '');

// CEP de onde a loja despacha os pedidos (só números, sem traço).
define('MELHOR_ENVIO_CEP_ORIGEM', '');

// Remetente — obrigatório pra gerar etiqueta de verdade (não usado só pra cotação). Documento sem
// pontuação (CPF 11 dígitos ou CNPJ 14 dígitos).
define('MELHOR_ENVIO_REMETENTE_NOME', '');
define('MELHOR_ENVIO_REMETENTE_DOCUMENTO', '');
define('MELHOR_ENVIO_REMETENTE_TELEFONE', ''); // só números, com DDD
define('MELHOR_ENVIO_REMETENTE_EMAIL', '');
define('MELHOR_ENVIO_REMETENTE_LOGRADOURO', '');
define('MELHOR_ENVIO_REMETENTE_NUMERO', '');
define('MELHOR_ENVIO_REMETENTE_COMPLEMENTO', ''); // opcional
define('MELHOR_ENVIO_REMETENTE_BAIRRO', '');
define('MELHOR_ENVIO_REMETENTE_CIDADE', '');
define('MELHOR_ENVIO_REMETENTE_UF', '');
