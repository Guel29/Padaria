# Ativar a ponte VR em padaria.instara.com.br

Esta é uma **atualização da instalação existente**, não uma reinstalação. Ela apenas lê o VR e espelha dados na Hostinger. O envio dos pedidos do tablet ao VR e a liberação automática das comandas após pagamento **não estão implementados** nesta etapa.

## 1. Atualizar a Hostinger

Faça backup do MySQL e do arquivo privado config.php. Baixe:

https://github.com/Guel29/Padaria/raw/refs/heads/main/downloads/atualizacao-vr-hostinger.zip

Extraia. Envie **o conteúdo** de `public_html` para a pasta `public_html/padaria` do subdomínio, sobrescrevendo os arquivos correspondentes. Não crie outra pasta public_html dentro de padaria. Preserve os assets antigos até atualizar o PWA nos tablets.

A parte `padaria-private` contém somente os arquivos privados atualizados e a migração: envie para a sua pasta privada **fora de public_html**. Esse pacote não contém nem substitui config.php, api/index.php ou .htaccess, preservando o caminho privado já ajustado. Não envie a pasta privada para dentro de public_html/padaria. Não execute instalar.html de novo e não reimporte schema.sql.

No phpMyAdmin do banco **MySQL da Hostinger**, importe **vr-schema.sql**. Ele cria apenas a nova tabela vr_sync, sem limpar dados. Não execute essa migração no PostgreSQL do VR.

## 2. Configurar a ponte no Windows

Baixe:

https://github.com/Guel29/Padaria/raw/refs/heads/main/downloads/ponte-vr-windows.zip

Extraia em uma pasta do usuário do Windows e siga o arquivo LEIA-ME.md. A ordem é:

1. Instale Python 3.12 (64 bits), com o launcher `py`.
2. Execute Instalar.bat.
3. Solicite/configure uma conta PostgreSQL exclusiva de leitura; não use postgres nem reutilize a credencial exposta.
4. Execute Configurar.bat. Informe host, porta, banco e essa conta. Selecione inicialmente apenas o produto 439 e loja 1 para validação.
5. A configuração gera `dados-locais/hostinger-config.txt`. Copie as três opções desse arquivo para **dentro do array** do config.php privado da Hostinger. Se já existirem, substitua seus valores em vez de duplicar as chaves. Não altere as credenciais MySQL.
6. Execute Testar-Leitura.bat: ele não envia dados. Resolva qualquer erro de acesso/estrutura antes de prosseguir.
7. Execute Iniciar.bat e mantenha a janela aberta para validar a primeira coleta.

A senha PostgreSQL e o token de envio ficam criptografados por usuário com Windows DPAPI; o arquivo para copiar à Hostinger contém somente hash do token, identificador da origem e loja. Não envie segredos.dat, config.php ou senhas pelo chat.

## 3. Conferir e importar

Abra https://padaria.instara.com.br/#admin, atualize o aplicativo quando solicitado e clique em **Integração VR**. Confira:

- Última leitura recente, loja 1.
- Produto 439: COXINHA DE FRANGO GRANDE, UN, R$ 4,50, mesmo com pesavel=true.
- Ficha histórica 9001, cupom 67716 / ECF 112: cupom cancelado se ainda estiver dentro da janela e no mesmo estado do VR.

Use **Importar / atualizar catálogo** para aplicar os produtos selecionados. Novos produtos entram **inativos** na categoria VR. Confira foto, categoria e preço em Produtos e ative-os. A importação não altera pedidos já lançados, não soma vendas e não encerra comandas.

A coleta ocorre a cada 30 segundos; o painel consulta a cada 15 segundos. A atualização do catálogo exige a ação Importar/atualizar. A janela padrão é de 45 dias pela abertura das fichas: não cobre todos os eventos financeiros e não deve ser usada como faturamento total.

## Validação feita e pendente

Foram testados coletor em PostgreSQL 14 com usuário de SELECT, API PHP/MySQL, deduplicação, credenciais da ponte, ordem de lotes, unidades UN/KG, vínculo explícito ficha-venda, cancelamento do cabeçalho, importação inativa, acesso administrativo e preservação de pedidos locais. O reenvio conserva o mesmo lote após falha.

Não temos acesso à rede da loja nem à sessão Windows: DPAPI, instalação/agendamento e a leitura dos registros reais ainda precisam ser validados no seu computador. Nenhuma senha real está nos pacotes. Esta etapa não disponibiliza gravações no VR. Consulte o LEIA-ME da ponte para limites, permissões e agendamento automático.
