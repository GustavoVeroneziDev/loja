<?php
// Copie este arquivo para chaves.php e preencha com as credenciais reais.
// chaves.php fica fora do git (.gitignore) — cada clone/deploy tem sua própria integração.

// Melhor Envio — painel > Integrações > Aplicativos, gera Client ID/Secret ao cadastrar um app.
// Ambiente 'sandbox' cota com dados de teste (preço não é real); 'producao' usa a conta de verdade.
define('MELHOR_ENVIO_AMBIENTE', 'sandbox'); // 'sandbox' ou 'producao'
define('MELHOR_ENVIO_CLIENT_ID', '');
define('MELHOR_ENVIO_CLIENT_SECRET', '');
define('MELHOR_ENVIO_REDIRECT_URI', 'http://localhost/loja/admin/entregas/melhor-envio-callback.php');

// CEP de onde a loja despacha os pedidos (só números, sem traço).
define('MELHOR_ENVIO_CEP_ORIGEM', '');
