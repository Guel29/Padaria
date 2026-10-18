# Ponte VR de leitura — Windows

## O que esta etapa faz

Lê produtos/preços selecionados, fichas abertas e fichas históricas vinculadas a itens de venda do VR PostgreSQL, e envia um espelho por HTTPS à Hostinger. A administração recebe uma seção **Integração VR**. Produtos podem ser importados por ação explícita do administrador; novos entram inativos para conferência.

**Não envia pedidos do tablet ao VR, não paga, não cancela, não libera comandas locais e não grava no PostgreSQL.** O faturamento completo ainda não está integrado. Não use esta ponte como substituta do lançamento no VR/PDV. A existência de um vínculo de tabelas não constitui um contrato de escrita suportado pelo VR.

## Preparar a Hostinger (preservar banco/configuração existente)

1. Faça backup do MySQL e do arquivo privado `config.php`.
2. Baixe o pacote Hostinger atualizado. Atualize o conteúdo público e os arquivos privados `api.php` e `vr.php`. Preserve o seu `config.php`. Não execute instalar.html novamente e não substitua o banco.
3. Importe **apenas `padaria-private/vr-schema.sql`** no phpMyAdmin. É uma migração aditiva: cria `vr_sync`, sem apagar os cadastros/usuários/pedidos.
4. Preserve o caminho privado ajustado em `public_html/padaria/api/index.php`: neste layout, `$entry = dirname(__DIR__, 3) . '/padaria-private/api.php';`. Atualizar o pacote não deve desfazer esse ajuste.
5. As três opções `vr_bridge_token_hash`, `vr_source_id` e `vr_store_id` serão geradas na configuração Windows. Adicione-as **dentro do array** do `config.php` privado sem remover as opções MySQL.

## Preparar o PostgreSQL

Solicite ao responsável pelo VR um usuário exclusivo com CONNECT no banco, USAGE nos schemas `public`, `pdv`, `ficha` e SELECT somente nas tabelas utilizadas (lista em `SCHEMA` no bridge.py). O usuário `postgres` também é aceito quando autorizado pelo responsável pela loja; a conta mantém suas permissões administrativas, embora a ponte use transações somente de leitura. A ponte também força transações READ ONLY, mas isso não substitui as permissões mínimas no banco. Não abra a porta PostgreSQL na internet.

Host e porta observados: 192.168.30.250:8745, banco vr, loja 1. A conexão deve acontecer no computador/rede local da loja. O protocolo PostgreSQL usa por padrão sslmode=prefer do libpq. Para exigir verificação, adicione `"sslmode": "verify-full"` e `"sslrootcert": "C:\\caminho\\ca.crt"` no config.json, usando host compatível com o certificado fornecido pelo suporte. Não use sslmode=disable; confirme com o suporte como exigir TLS/verificação do certificado nessa rede antes de operar fora de rede confiável. HTTPS para a Hostinger sempre verifica certificado.

## Instalar no computador Windows

1. Instale Python 3.12 (64 bits) de python.org (com Python Launcher `py`).
2. Extraia este pacote em uma pasta do **seu usuário**, por exemplo `%LOCALAPPDATA%\PadariaPonte`. Não use uma pasta de acesso público/rede ou da hospedagem.
3. Execute `Instalar.bat` para criar um ambiente Python local e instalar as dependências fixadas.
4. Execute `Configurar.bat`. Informe o usuário PostgreSQL (inclusive `postgres`, se for sua conta atual) e sua senha. A senha não aparece na tela.
5. Escolha explicitamente os IDs de produtos autorizados (por exemplo, 439 para o teste da coxinha). Até 200 por configuração; não há importação irrestrita. Loja padrão 1. O assistente gera um token aleatório e um identificador de origem, sem mostrar o token original.
6. Copie as três opções de `dados-locais/hostinger-config.txt` para o array privado de config.php na Hostinger. Esse arquivo contém somente o hash do token, ID da origem e loja, não a senha do PostgreSQL nem o token original. Nunca envie `segredos.dat` ou config.php ao chat/GitHub.
7. Execute `Testar-Leitura.bat`. Ele consulta com READ ONLY e não envia nada à Hostinger. Se faltar coluna/permissão, a ponte interrompe sem tentar adivinhar tabelas.
8. Execute `Iniciar.bat`. O ciclo padrão é de 30 segundos; mantenha a janela aberta no primeiro teste. No administrador da padaria, abra **Integração VR**. A página verifica a última leitura a cada 15 segundos.
9. Confira o produto 439: UN, R$ 4,50. O indicador pesavel=true NÃO converte esse produto em kg. A unidade só é reconhecida quando embalagem de produto e loja concordam em UN ou KG. Divergências ficam bloqueadas.
10. Use **Importar / atualizar catálogo**. Novos produtos entram inativos na categoria VR; adicione foto/categoria e ative depois de conferir. Preços e unidades só são aplicados ao catálogo quando você importa/atualiza; a coleta periódica não altera o catálogo automaticamente. Fotos/categorias de produtos já vinculados são preservadas.

## Inicialização automática

Após validar manualmente, crie uma tarefa no **Agendador de Tarefas do Windows**:

- Gatilho: Ao fazer logon no seu usuário.
- Programa: caminho completo de `.venv\Scripts\python.exe` dentro da pasta da ponte.
- Argumentos: caminho completo entre aspas de `bridge.py`.
- Iniciar em: pasta da ponte.
- Executar somente quando esse usuário estiver conectado; se já estiver em execução, não iniciar outra instância. Reiniciar após falha, se desejado.

Não use SYSTEM nem outro usuário: as credenciais DPAPI só podem ser abertas pelo usuário que executou Configurar.bat. O processo abre apenas conexões de saída; não há servidor HTTP ou porta de entrada no Windows.

## Pendências, mudanças e limites

- Senha PostgreSQL e token de envio ficam criptografados com Windows DPAPI para o usuário atual. A proteção foi implementada com a API nativa; o instalador/configuração devem ser validados no Windows real. O arquivo público de configuração contém host, usuário, IDs e intervalo, sem senhas.
- Não apague `dados-locais`: `estado.json` mantém a sequência e `pendente.json` conserva a leitura não confirmada, reenviada com o mesmo lote. O servidor não reaplica o mesmo lote nem aceita sequência antiga. Requisições HTTPS com redirecionamento são recusadas para não encaminhar token a outra origem.
- Para mudar produtos/intervalo, pare a ponte e edite apenas `product_ids`, `lookback_days` ou `interval` em config.json. Preserve source, segredos.dat, estado.json e pendente.json. Resolva a pendência antes da mudança. Intervalo mínimo 20 segundos; período 1–365 dias; padrão 45 dias pela abertura da ficha histórica, não pela data de cancelamento.
- Um cancelamento posterior de ficha fora da janela não é capturado. Não há apuração financeira total nem importação de todas as vendas da loja; consultamos somente vínculos de fichas dentro da janela.
- Limites por leitura: 200 produtos, 5.000 códigos de barras, 1.000 fichas abertas, 10.000 itens abertos, 2.000 fichas históricas, 20.000 itens históricos, 8 MB por envio. Ao exceder, a leitura falha inteira; nada é apresentado como completo. Evite intervalos muito curtos e acompanhe impacto no servidor VR.
- Somente o usuário administrativo da aplicação pode consultar o espelho ou importar produtos. O token da ponte não permite administrar usuários/pedidos. O servidor restringe a uma origem e uma loja configuradas.
- O preço consultado é produtocomplemento.precovenda da loja. Ofertas, descontos por quantidade e regras promocionais do PDV não são interpretados. O status cadastral do VR também não é convertido automaticamente em ativo/inativo; confira antes de ativar produtos no aplicativo.
- Identificadores importados de produto são `vr:LOJA:ID`, evitando colisão com produtos locais. Atendimentos do VR são apresentados com seus IDs próprios. Não mapeamos automaticamente comanda 01 do aplicativo para ficha 9001.
- Para trocar computador/usuário Windows, reconfigure a ponte e atualize as opções privadas na Hostinger. A nova origem substitui o espelho na próxima leitura; a origem antiga deixa de ser autorizada. Resolva/exporte pendências antigas antes. Nunca tente descriptografar a credencial em outro usuário.
- A operação de importação é transacional e auditada no MySQL. Não modifica preços/quantidades de pedidos já lançados. Produtos de unidade ambígua são bloqueados; se já vinculados, ficam inativos quando você realiza a importação.

## Evidência usada no mapeamento

No teste informado: loja 1, ficha 9001, atendimento 1, item ficha 1, id_vendaitem 1421679, venda 215787, cupom 67716, ECF 112. O vínculo explícito é `ficha.fichaitem.id_vendaitem -> pdv.vendaitem.id -> pdv.venda.id`. Conferimos também loja/data/cupom/ECF; não usamos coincidência de valor/produto para associar vendas. A venda estava cancelada e seu item não: o status do cabeçalho não pode ser ignorado. Essa evidência sustenta leitura; não autoriza inserts no VR.
