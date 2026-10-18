# Instalar em https://padaria.instara.com.br

Este pacote hospeda interface React/PWA, API PHP e persistência MySQL na Hostinger. Não precisa executar Node no plano: o build é feito antes do upload. Requer PHP 8.2+ (testado com 8.3), PDO MySQL e cookies HTTPS. A API usa PDO; não usa Composer.

## Download pronto (sem compilar)

Baixe **https://github.com/Guel29/Padaria/raw/refs/heads/main/downloads/padaria-hostinger.zip**. Não use Code → Download ZIP: ele contém o código-fonte e pode deixar a tela branca se enviado diretamente ao servidor.

Extraia o ZIP. Envie **o conteúdo** de `public_html` (index.html, assets, api etc.) para a raiz pública do subdomínio. Não crie uma segunda pasta public_html dentro da primeira. A pasta `padaria-private` fica fora de todas as pastas públicas, como explicado abaixo.

Se a tela não iniciar, abra `https://padaria.instara.com.br/verificar.html` e consulte os resultados. A página não altera o banco. Não apague pedidos ou configurações existentes para corrigir o upload.

## 1. Preparar o pacote

```sh
npm ci
npm test
npm run build:hostinger
```

Os arquivos ficam em `release/hostinger`. Este pacote é separado da demonstração do GitHub Pages (`npm run build`) e não reutiliza seus logins ou pedidos locais. Não publique `dist/` na Hostinger; publique o conteúdo de `release/hostinger/public_html/`.

## 2. Enviar os arquivos

No gerenciador de arquivos/SFTP do site `padaria.instara.com.br`, localize o document root real mostrado pelo hPanel. Em muitos planos ele se chama `public_html`, mas pode ser uma pasta de subdomínio. Use o root específico deste site, preservando arquivos de outros sites.

Estrutura exigida (os nomes são importantes):

```
PASTA_DO_SITE/
  padaria-private/           # fora da pasta pública
    api.php
    config.example.php
    config.php              # criado por você, nunca enviado ao GitHub
    initial.json
    schema.sql
    .htaccess
  public_html/              # ou o document root real do subdomínio
    index.html
    assets/
    api/index.php
    instalar.html
    manifest.webmanifest
    sw.js
    workbox-*.js
    icon*.png
    icon.svg
    .htaccess
```

A pasta `padaria-private` deve ser irmã da pasta pública e ficar fora de **todas** as pastas públicas da conta. Se a raiz do subdomínio estiver dentro do `public_html` de outro domínio, não use essa posição como pasta privada: mova `padaria-private` para um diretório realmente privado da conta e ajuste a variável `$entry` em `api/index.php` para o caminho absoluto correto de `api.php`, informado pelo gerenciador de arquivos. O caminho não é uma senha, mas precisa existir e ser legível pelo PHP. Se seu hPanel não permite criar essa pasta fora do document root, não coloque senhas nos arquivos públicos: será necessário ajustar o caminho privado ou usar SFTP/SSH. O ponto de entrada `api/index.php` busca `padaria-private` dois níveis acima de seu próprio diretório. Não envie `node_modules`, `.git`, `.env` ou arquivos de teste ao site.

## 3. Configurar o MySQL sem expor a senha

1. No hPanel, selecione PHP 8.2 ou superior e habilite `pdo_mysql`.
2. No phpMyAdmin do banco já criado, importe `padaria-private/schema.sql`. As tabelas são novas e pertencem a este aplicativo; não use o banco do PDV.
3. No arquivo privado, copie `config.example.php` para `config.php`.
4. Preencha `db_host`, `db_port`, `db_name`, `db_user` e `db_password` com os dados da Hostinger. O nome completo pode conter um prefixo fornecido pela hospedagem. Não suponha que o host seja localhost: use o exibido no painel.
5. Mantenha `origin` como `https://padaria.instara.com.br` (sem barra final).
6. Gere um token aleatório de pelo menos 32 caracteres no seu gerenciador de senhas e defina `setup_token`. Não use a frase de exemplo. Não envie o arquivo preenchido ou as senhas pelo chat.
7. A pasta e o arquivo privados precisam ser legíveis pelo processo PHP, mas não pelo público. Use a menor permissão compatível com sua conta (normalmente 750/640 ou 700/600). Não use 777.

## 4. Inicializar as contas

1. Confirme que o SSL está ativo e o navegador não mostra erro de certificado.
2. Abra `https://padaria.instara.com.br/instalar.html`.
3. Informe o token de instalação, seu e-mail, uma senha administrativa nova de pelo menos 12 caracteres, um código de seis dígitos e uma senha de quatro dígitos para o primeiro atendente.
4. A instalação começa sem comandas ocupadas e sem vendas. O catálogo ilustrativo é uma base para cadastro: revise produtos e preços antes do atendimento real.
5. Depois do sucesso, exclua `instalar.html` da pasta pública e remova/esvazie `setup_token` em `config.php`. O servidor também recusa nova instalação se o banco já foi inicializado.
6. Administração: `https://padaria.instara.com.br/#admin`; atendimento: `https://padaria.instara.com.br/`.

A conta `admin@padaria.demo` / `Padaria@123` **não existe nesta instalação**. Use a conta definida por você. Não há recuperação por e-mail implementada nesta versão; mantenha as credenciais administrativas no gerenciador de senhas.

## 5. Validar antes de usar

- Entre com o administrador; confirme nome/logo/produtos e preços. Crie/edite atendentes definindo a senha de quatro dígitos. A senha é armazenada como hash no servidor.
- Abra a mesma URL em dois navegadores/tablets, entre como atendente, selecione uma comanda e adicione um produto. O outro dispositivo deve mostrar a atualização em até aproximadamente três segundos, enquanto conectado.
- Teste um produto por kg (0,250 kg), edição e remoção do último item. A comanda vazia volta a livre, preservando o cancelamento.
- Ao editar simultaneamente, uma alteração desatualizada deve ser recusada. Atualize a tela e confira o item antes de uma nova correção.
- Corte a conexão durante um lançamento: uma operação sem confirmação permanece como pendente e pode ser reenviada com a mesma chave. Não adicione outro item para substituir uma tentativa sem resposta. Reenvios idempotentes não criam outra linha.
- Atendentes não podem alterar cadastros pelo servidor nem processar pagamentos. Alterar/desativar atendente invalida suas sessões. O limite de login é 10 tentativas por conta e IP a cada 15 minutos; sessões duram 12 horas.
- A API não deve ser armazenada em cache (`Cache-Control: no-store`). Verifique o PWA e instale no tablet com HTTPS.
- Faça backup MySQL diário e antes das atualizações; teste restauração. Imagens fazem parte dos dados do catálogo. O pacote não faz backups automaticamente.

## Atualizações

O GitHub Pages permanece uma demonstração independente. O envio ao GitHub **não atualiza automaticamente a Hostinger** nesta configuração. Para atualizar, gere um novo pacote e envie a parte pública e `padaria-private/api.php`, preservando `config.php` e o banco. Não reimporte/reinicialize dados de produção. Remova `instalar.html` de pacotes de atualização. Mantenha os assets do build anterior até a troca do Service Worker nos tablets; depois podem ser limpos em uma janela de manutenção.

O PWA usa a raiz `/`, sem `/Padaria/`. O Service Worker não faz fallback nem cache da API. Novas versões são oferecidas pelo aviso de atualização. Gravações precisam de rede; a abertura offline não confirma pedidos no servidor. Uma operação pendente é local ao navegador e ao usuário; não limpe dados do site nem desinstale o PWA antes de resolver tentativas sem resposta.

## Arquitetura e limites

A API mantém catálogo/configurações e atendimentos em documentos JSON no MySQL, serializando gravações em uma linha InnoDB com `SELECT FOR UPDATE`; chaves de idempotência e auditoria ficam em tabelas próprias. É uma estrutura inicial para uma única loja e poucos tablets, não o esquema do software comercial. O tamanho do histórico aumenta com uso: mantenha monitoramento/backup e planeje normalização/arquivamento antes de uso prolongado. Imagens aceitam PNG/JPEG/WebP até 1 MB e resolução máxima 8000x8000; o corpo da configuração é limitado a 12 MB. Cadastros simultâneos usam versão; edição de pedidos usa versão global, podendo exigir conferência se outro tablet alterar qualquer comanda.

Não há integração com o PDV. Pagamento e encerramento financeiro continuam exclusivamente nele; a liberação automática após pagamento e os dados de faturamento dependem do futuro conector oficial. Não há endpoint HTTP para marcar pedidos como pagos. Pedidos no banco deste app **não são automaticamente enviados ao software comercial**. Valide essa ponte antes de substituir o fluxo operacional da loja.

## Ponte de leitura VR no Windows

A instalação existente pode receber a ponte de leitura seguindo [HOSTINGER-VR.md](HOSTINGER-VR.md). Use o ZIP específico de atualização para preservar config.php e api/index.php (incluindo o caminho privado do subdomínio). O pacote Windows não contém senhas; exige uma conta PostgreSQL de leitura configurada localmente. A nova aba administrativa Integração VR consulta o espelho e permite importar produtos por ação explícita. Não há envio de pedidos ao VR nem encerramento automático nesta etapa.
