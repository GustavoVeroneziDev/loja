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
