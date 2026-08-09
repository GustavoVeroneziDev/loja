<?php
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/funcoes.php';
require_once __DIR__ . '/config/marca.php';

require __DIR__ . '/geral/header.php';
?>
<div class="row">
    <div class="col-lg-9 mx-auto">
        <div class="card p-4 p-md-5">
            <h1 class="h3 mb-1 titulo-estilizado">Termos de Uso</h1>
            <p class="text-secundario mb-4">Última atualização: 09/08/2026</p>

            <p>
                Estes Termos de Uso regulam o acesso e a utilização do site da <?= htmlspecialchars(NOME_LOJA) ?>
                por qualquer pessoa que o visite ou faça compras através dele ("cliente" ou "você"). Ao criar
                uma conta ou realizar um pedido, você declara que leu, entendeu e concorda com todas as
                condições descritas abaixo.
            </p>

            <h2 class="h5 mt-4 mb-2">1. Cadastro e conta</h2>
            <p>
                Pra comprar, você pode precisar criar uma conta informando nome, e-mail, telefone e uma senha.
                Você é responsável por manter seus dados de acesso em sigilo e por toda atividade realizada
                através da sua conta. Avise a gente imediatamente se suspeitar de uso não autorizado.
            </p>

            <h2 class="h5 mt-4 mb-2">2. Uso do site</h2>
            <p>
                Você concorda em usar o site apenas para fins lícitos, sem tentar burlar mecanismos de
                segurança, coletar dados de outros usuários, sobrecarregar nossa infraestrutura ou utilizar o
                catálogo/conteúdo pra qualquer finalidade comercial que não seja a compra de produtos aqui
                oferecidos.
            </p>

            <h2 class="h5 mt-4 mb-2">3. Produtos, preços e disponibilidade</h2>
            <p>
                Fazemos o possível pra manter descrições, fotos, preços e estoque corretos e atualizados, mas
                erros podem acontecer. Nos reservamos o direito de corrigir preços incorretos, cancelar pedidos
                afetados por erro evidente de precificação e limitar quantidade por cliente. A disponibilidade
                de estoque é confirmada apenas no momento da finalização da compra.
            </p>

            <h2 class="h5 mt-4 mb-2">4. Pedidos e pagamento</h2>
            <p>
                O pedido é considerado confirmado após a aprovação do pagamento pela forma escolhida no
                checkout. Podemos recusar ou cancelar um pedido em casos de suspeita de fraude, dados
                incorretos ou indisponibilidade do produto, com reembolso integral quando já houver cobrança
                efetuada.
            </p>

            <h2 class="h5 mt-4 mb-2">5. Trocas e devoluções</h2>
            <p><?= nl2br(htmlspecialchars(TEXTO_POLITICA_TROCA)) ?></p>

            <h2 class="h5 mt-4 mb-2">6. Propriedade intelectual</h2>
            <p>
                Marca, logotipo, textos, fotos e demais conteúdos do site pertencem à <?= htmlspecialchars(NOME_LOJA) ?>
                ou são usados sob licença, e não podem ser copiados, reproduzidos ou redistribuídos sem
                autorização prévia por escrito.
            </p>

            <h2 class="h5 mt-4 mb-2">7. Limitação de responsabilidade</h2>
            <p>
                Não nos responsabilizamos por indisponibilidade temporária do site por motivo técnico, nem por
                danos indiretos decorrentes do uso do site fora do que prevê a legislação de defesa do
                consumidor aplicável.
            </p>

            <h2 class="h5 mt-4 mb-2">8. Alterações destes termos</h2>
            <p>
                Podemos atualizar estes Termos de Uso a qualquer momento pra refletir mudanças no site, na lei
                ou na nossa operação. A versão vigente é sempre a publicada nesta página, com a data de
                atualização no topo.
            </p>

            <h2 class="h5 mt-4 mb-2">9. Lei aplicável</h2>
            <p>
                Estes termos são regidos pela legislação brasileira, incluindo o Código de Defesa do
                Consumidor, e eventuais disputas serão resolvidas no foro do domicílio do consumidor.
            </p>

            <h2 class="h5 mt-4 mb-2">10. Contato</h2>
            <p>
                Dúvidas sobre estes termos? Fala com a gente em
                <a href="mailto:<?= htmlspecialchars(TEXTO_CONTATO) ?>" class="link-marca"><?= htmlspecialchars(TEXTO_CONTATO) ?></a>.
            </p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/geral/footer.php'; ?>
