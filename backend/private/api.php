<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
function fail(int $status,string $message):never{http_response_code($status);echo json_encode(['error'=>$message],JSON_UNESCAPED_UNICODE);exit;}
function output(array $data):never{echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
function uuid():string{$h=bin2hex(random_bytes(16));return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-a'.substr($h,17,3).'-'.substr($h,20);}
function now():string{return gmdate('Y-m-d\TH:i:s.000\Z');}
function check(bool $condition,string $message):void{if(!$condition)fail(422,$message);}
function text(mixed $value,int $max=180):string{check(is_string($value)&&strlen($value)<=$max,'Texto inválido.');return trim($value);}
function query(PDO $db,string $sql,array $params=[]):PDOStatement{$q=$db->prepare($sql);$q->execute($params);return $q;}
function image(mixed $value):string{
 $value=text($value,1500000);if($value==='')return '';
 if(str_starts_with($value,'https://')){check((bool)filter_var($value,FILTER_VALIDATE_URL),'URL de imagem inválida.');return $value;}
 check((bool)preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#D',$value,$m),'Formato de imagem inválido.');
 $bytes=base64_decode($m[2],true);check($bytes!==false&&strlen($bytes)<=1048576,'Imagem maior que 1 MB.');
 $info=@getimagesizefromstring($bytes);check(is_array($info)&&in_array($info['mime'],['image/png','image/jpeg','image/webp'],true)&&$info[0]<=8000&&$info[1]<=8000,'Imagem inválida ou muito grande.');return $value;
}
function quantity(mixed $value,string $unit,bool $zero=false):float|int{
 check(is_int($value)||is_float($value),'Quantidade inválida.');
 check(is_finite((float)$value)&&$value>=($zero?0:($unit==='kg'?0.001:1))&&$value<=($unit==='kg'?99.999:99),'Quantidade fora do limite.');
 check($unit==='kg'?abs($value*1000-round($value*1000))<1e-8:floor($value)===$value*1.0,'Precisão de quantidade inválida.');
 return $unit==='kg'?round($value,3):(int)$value;
}
function settings(array $s,array $orders):array{
 check(isset($s['store'],$s['products'],$s['categories'],$s['tableCount']),'Configuração incompleta.');
 check(is_int($s['tableCount'])&&$s['tableCount']>=1&&$s['tableCount']<=100,'Quantidade de comandas inválida.');
 foreach($orders['visits'] as $v)check($v['number']<=$s['tableCount'],'Não remova comandas com pedidos em aberto.');
 $store=[];foreach(['name','legalName','cnpj','address','city','phone'] as $key)$store[$key]=text($s['store'][$key]??'',180);
 check($store['name']!=='','Nome da loja obrigatório.');$store['logo']=image($s['store']['logo']??'');
 check(is_array($s['categories'])&&count($s['categories'])<=100,'Categorias inválidas.');$cats=[];$seen=[];
 foreach($s['categories'] as $c){$c=text($c,80);check($c!==''&&$c!=='Todos'&&!isset($seen[strtolower($c)]),'Categoria duplicada ou inválida.');$cats[]=$c;$seen[strtolower($c)]=true;}
 check(is_array($s['products'])&&count($s['products'])<=500,'Catálogo inválido.');$products=[];$ids=[];$vrLinks=[];
 foreach($s['products'] as $p){$id=text($p['id']??'',64);check($id!==''&&!isset($ids[$id]),'Produto duplicado.');$ids[$id]=true;$name=text($p['name']??'',200);$unit=$p['unit']??'un';check($name!==''&&in_array($unit,['kg','un'],true),'Nome ou unidade inválidos.');check(in_array($p['category']??'', $cats,true),'Categoria não cadastrada.');check(is_int($p['price']??null)&&$p['price']>0&&$p['price']<=99999999,'Preço inválido.');check(is_bool($p['active']??null),'Status inválido.');$link=vr_product_link($p);if($link){$key=$link['store'].':'.$link['productId'];check(!isset($vrLinks[$key]),'Este código VR já está associado a outro produto.');$vrLinks[$key]=true;}$products[]=['vrLink'=>$link,'id'=>$id,'name'=>$name,'description'=>text($p['description']??'',250),'unit'=>$unit,'category'=>$p['category'],'price'=>$p['price'],'photo'=>image($p['photo']??''),'active'=>$p['active']];}
 return ['store'=>$store,'products'=>$products,'categories'=>$cats,'tableCount'=>$s['tableCount'],'users'=>[]];
}
function snapshot(PDO $db,array $user):array{
 $row=query($db,'SELECT * FROM app_data WHERE id=1')->fetch();if(!$row)fail(503,'Instalação ainda não concluída.');
 $s=json_decode($row['settings'],true,512,JSON_THROW_ON_ERROR);
 $s['users']=$user['role']==='admin'?array_map(fn($u)=>['id'=>$u['id'],'code'=>$u['login'],'name'=>$u['name'],'active'=>(bool)$u['active']],query($db,"SELECT id,login,name,active FROM users WHERE role='attendant' ORDER BY name")->fetchAll()):[];
 if($user['role']!=='admin')$s['products']=array_values(array_filter($s['products'],fn($p)=>$p['active']));
 return ['settings'=>$s,'orders'=>json_decode($row['orders'],true,512,JSON_THROW_ON_ERROR),'version'=>(int)$row['version'],'settingsVersion'=>(int)$row['settings_version']];
}
try{
 if(!is_file(__DIR__.'/config.php'))fail(503,'Configure o arquivo privado config.php na hospedagem.');
 $config=require __DIR__.'/config.php';
 $action=$_GET['action']??'';$method=$_SERVER['REQUEST_METHOD']??'GET';
 check(in_array($method,['GET','POST'],true),'Método não suportado.');
 $origin=$_SERVER['HTTP_ORIGIN']??'';
 if($method==='POST'){
  if($action!=='bridge'&&$origin!==$config['origin'])fail(403,'Origem não autorizada.');
  if(!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json'))fail(415,'Envie JSON.');
  if((int)($_SERVER['CONTENT_LENGTH']??0)>12582912)fail(413,'Requisição muito grande.');
 }
 $raw=$method==='POST'?file_get_contents('php://input',false,null,0,12582913):'';
 if(strlen($raw)>12582912)fail(413,'Requisição muito grande.');
 $body=$method==='POST'?json_decode($raw,true,512,JSON_THROW_ON_ERROR):[];
 if($method==='POST'&&!is_array($body))fail(400,'JSON inválido.');
 $db=new PDO('mysql:host='.$config['db_host'].';port='.($config['db_port']??3306).';dbname='.$config['db_name'].';charset=utf8mb4',$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 require_once __DIR__.'/vr.php';
 if($action==='bridge_selection'){if($method!=='GET')fail(405,'Use GET.');vr_bridge_selection($db,$config);}
 if($action==='bridge'){if($method!=='POST')fail(405,'Use POST.');vr_receive($db,$config,$body,$raw);}
 if($action==='install'){
  if($method!=='POST')fail(405,'Use POST.');
  if(query($db,'SELECT id FROM app_data WHERE id=1')->fetch())fail(409,'Aplicativo já instalado.');
  $token=$config['setup_token']??'';if(strlen($token)<32||str_starts_with($token,'SUBSTITUA')||!hash_equals($token,(string)($body['token']??'')))fail(403,'Token de instalação inválido.');
  $email=text($body['email']??'',190);$password=$body['password']??'';$code=$body['code']??'';$pin=$body['pin']??'';
  check((bool)filter_var($email,FILTER_VALIDATE_EMAIL)&&is_string($password)&&strlen($password)>=12&&strlen($password)<=200,'Informe e-mail válido e senha administrativa de 12 a 200 caracteres.');
  check(is_string($code)&&preg_match('/^\d{6}$/D',$code)&&is_string($pin)&&preg_match('/^\d{4}$/D',$pin),'Atendente: código de 6 dígitos e senha de 4 dígitos.');
  $initial=json_decode(file_get_contents(__DIR__.'/initial.json'),true,512,JSON_THROW_ON_ERROR);$s=settings($initial,['visits'=>[]]);
  $db->beginTransaction();
  query($db,"INSERT INTO users (id,login,name,role,password_hash) VALUES (?,?,?,'admin',?)",[uuid(),strtolower($email),'Administrador',password_hash($password,PASSWORD_DEFAULT)]);
  query($db,"INSERT INTO users (id,login,name,role,password_hash) VALUES (?,?,?,'attendant',?)",[uuid(),$code,'Atendente',password_hash($pin,PASSWORD_DEFAULT)]);
  query($db,'INSERT INTO app_data (id,settings,orders) VALUES (1,?,?)',[json_encode($s),json_encode(['visits'=>[],'archive'=>[]])]);$db->commit();output(['ok'=>true]);
 }
 if($action==='login'){
  if($method!=='POST')fail(405,'Use POST.');$role=$body['role']??'';check(in_array($role,['admin','attendant'],true),'Perfil inválido.');
  $login=strtolower(text($body['login']??'',190));$password=$body['password']??'';check(is_string($password)&&strlen($password)<=200,'Senha inválida.');
  // Both account and IP buckets are counted atomically, including concurrent attempts.
  $buckets=[hash('sha256','account:'.$role.':'.$login),hash('sha256','ip:'.($_SERVER['REMOTE_ADDR']??''))];sort($buckets);
  $db->beginTransaction();foreach($buckets as $bucket){query($db,'INSERT IGNORE INTO login_limits (bucket,attempts,window_start) VALUES (?,0,?)',[$bucket,time()]);$limit=query($db,'SELECT * FROM login_limits WHERE bucket=? FOR UPDATE',[$bucket])->fetch();if(time()-(int)$limit['window_start']>=900){query($db,'UPDATE login_limits SET attempts=0,window_start=? WHERE bucket=?',[time(),$bucket]);$limit['attempts']=0;}if((int)$limit['attempts']>=10){$db->commit();fail(429,'Muitas tentativas. Aguarde 15 minutos.');}query($db,'UPDATE login_limits SET attempts=attempts+1 WHERE bucket=?',[$bucket]);}$db->commit();
  $u=query($db,'SELECT * FROM users WHERE login=? AND role=?',[$login,$role])->fetch();
  if(!$u||!$u['active']||!password_verify($password,$u['password_hash'])){usleep(200000);fail(401,'Credenciais inválidas.');}
  $token=bin2hex(random_bytes(32));$csrf=bin2hex(random_bytes(32));query($db,'DELETE FROM sessions WHERE expires_at<?',[time()]);query($db,'INSERT INTO sessions (token_hash,user_id,csrf,expires_at) VALUES (?,?,?,?)',[hash('sha256',$token),$u['id'],$csrf,time()+43200]);
  setcookie('padaria_session',$token,['expires'=>time()+43200,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
  unset($u['password_hash']);output(['user'=>['id'=>$u['id'],'login'=>$u['login'],'name'=>$u['name'],'role'=>$u['role']],'csrf'=>$csrf,'data'=>snapshot($db,$u)]);
 }
 $token=$_COOKIE['padaria_session']??'';
 $u=query($db,'SELECT u.*,s.csrf FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND s.expires_at>? AND u.active=1',[hash('sha256',$token),time()])->fetch();
 if(!$u)fail(401,'Entre novamente para continuar.');
 if($method==='POST'&&!hash_equals($u['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))fail(403,'Sessão inválida. Reabra o aplicativo.');
 if(in_array($action,['vr_status','vr_import'],true)){if($u['role']!=='admin')fail(403,'Acesso administrativo necessário.');if($action==='vr_status'){if($method!=='GET')fail(405,'Use GET.');output(vr_status($db,$config));}if($method!=='POST')fail(405,'Use POST.');vr_import($db,$config,$body,$u);}
 if($action==='session')output(['user'=>['id'=>$u['id'],'login'=>$u['login'],'name'=>$u['name'],'role'=>$u['role']],'csrf'=>$u['csrf'],'data'=>snapshot($db,$u)]);
 if($action==='snapshot')output(snapshot($db,$u));
 if($action==='logout'){
  if($method!=='POST')fail(405,'Use POST.');query($db,'DELETE FROM sessions WHERE token_hash=?',[hash('sha256',$token)]);setcookie('padaria_session','',['expires'=>1,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);output(['ok'=>true]);
 }
 if($method!=='POST')fail(405,'Use POST.');
 if(!in_array($action,['add','edit','settings','user'],true))fail(404,'Operação inexistente.');
 if(in_array($action,['settings','user'],true)&&$u['role']!=='admin')fail(403,'Acesso administrativo necessário.');
 $operation=text($body['operationId']??'',64);check((bool)preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$operation),'Identificador da operação inválido.');$hashBody=$body;if($action==='user')unset($hashBody['pin']);$hash=hash('sha256',json_encode(['action'=>$action,'body'=>$hashBody],JSON_THROW_ON_ERROR));
 $db->beginTransaction();$row=query($db,'SELECT * FROM app_data WHERE id=1 FOR UPDATE')->fetch();
 $existing=query($db,'SELECT request_hash FROM operations WHERE user_id=? AND operation_id=?',[$u['id'],$operation])->fetch();
 if($existing){if(!hash_equals($existing['request_hash'],$hash))fail(409,'Identificador reutilizado para outra operação.');$db->commit();output(snapshot($db,$u));}
 $orders=json_decode($row['orders'],true,512,JSON_THROW_ON_ERROR);$s=json_decode($row['settings'],true,512,JSON_THROW_ON_ERROR);
 $record=[];
 if($action==='add'){
  $number=$body['number']??null;check(is_int($number)&&$number>=1&&$number<=$s['tableCount'],'Comanda inválida.');
  $index=null;foreach($orders['visits'] as $i=>$v)if($v['number']===$number)$index=$i;
  $actual=$index!==null?$orders['visits'][$index]['id']:null;
  if(($body['visitId']??null)!==$actual)fail(409,'A comanda mudou em outro dispositivo. Confira o atendimento atualizado.');
  $p=null;foreach($s['products'] as $candidate)if($candidate['id']===($body['productId']??'')&&$candidate['active'])$p=$candidate;
  check($p!==null,'Produto indisponível.');$unit=$p['unit']??'un';$q=quantity($body['quantity']??null,$unit);
  if($index===null){$orders['visits'][]=['id'=>uuid(),'number'=>$number,'items'=>[],'changes'=>[]];$index=count($orders['visits'])-1;}
  $item=['id'=>$operation,'productId'=>$p['id'],'name'=>$p['name'],'photo'=>$p['photo'],'price'=>$p['price'],'quantity'=>$q,'unit'=>$unit,'time'=>now(),'attendant'=>$u['login']];
  $orders['visits'][$index]['items'][]=$item;$record=['visitId'=>$orders['visits'][$index]['id'],'number'=>$number,'item'=>$item];
 }elseif($action==='edit'){
  if(($body['version']??null)!==(int)$row['version'])fail(409,'Os pedidos mudaram em outro dispositivo. Confira antes de editar.');
  $found=false;foreach($orders['visits'] as $vi=>&$v){if($v['id']!==($body['visitId']??''))continue;
   foreach($v['items'] as $ii=>&$item){if($item['id']!==($body['itemId']??''))continue;$q=quantity($body['quantity']??null,$item['unit']??'un',true);$change=['item'=>$item['name'],'before'=>$item['quantity'],'after'=>$q,'unit'=>$item['unit']??'un','time'=>now(),'attendant'=>$u['login']];$v['changes'][]=$change;$record=['visitId'=>$v['id'],'itemId'=>$item['id'],'change'=>$change];$found=true;if($q==0){unset($v['items'][$ii]);$v['items']=array_values($v['items']);}else $item['quantity']=$q;break;}unset($item);
   if(!$v['items']){$v['cancelledAt']=now();$orders['archive'][]=$v;unset($orders['visits'][$vi]);}break;
  }unset($v);check($found,'Lançamento não encontrado ou atendimento arquivado.');$orders['visits']=array_values($orders['visits']);
 }elseif($action==='settings'){
  if(($body['settingsVersion']??null)!==(int)$row['settings_version'])fail(409,'O cadastro foi alterado por outro administrador. Atualize e tente novamente.');
  check(is_array($body['settings']??null),'Configuração inválida.');$s=settings($body['settings'],$orders);$row['settings_version']++;$record=['settingsVersion'=>(int)$row['settings_version']];
 }elseif($action==='user'){
  $user=$body['user']??[];$id=text($user['id']??'',64);$login=text($user['code']??'',6);$name=text($user['name']??'',100);check($id!==''&&preg_match('/^\d{6}$/D',$login)&&$name!==''&&is_bool($user['active']??null),'Dados de atendente inválidos.');$pin=$body['pin']??'';check(is_string($pin)&&($pin===''||preg_match('/^\d{4}$/D',$pin)),'Senha deve ter quatro dígitos.');
  $old=query($db,'SELECT * FROM users WHERE id=?',[$id])->fetch();if($old)check($old['role']==='attendant','Perfil não editável.');else check($pin!=='','Defina a senha do novo atendente.');
  $duplicate=query($db,'SELECT id FROM users WHERE login=? AND id<>?',[$login,$id])->fetch();check(!$duplicate,'Código já cadastrado.');
  $pwd=$pin!==''?password_hash($pin,PASSWORD_DEFAULT):$old['password_hash'];
  if($old)query($db,'UPDATE users SET login=?,name=?,active=?,password_hash=? WHERE id=?',[$login,$name,(int)$user['active'],$pwd,$id]);else query($db,"INSERT INTO users (id,login,name,active,password_hash,role) VALUES (?,?,?,?,?,'attendant')",[$id,$login,$name,(int)$user['active'],$pwd]);
  query($db,'DELETE FROM sessions WHERE user_id=?',[$id]);$record=['userId'=>$id,'active'=>$user['active']];
 }
 query($db,'UPDATE app_data SET settings=?,orders=?,version=version+1,settings_version=? WHERE id=1',[json_encode($s,JSON_THROW_ON_ERROR),json_encode($orders,JSON_THROW_ON_ERROR),$row['settings_version']]);
 query($db,'INSERT INTO operations (user_id,operation_id,request_hash) VALUES (?,?,?)',[$u['id'],$operation,$hash]);
 query($db,'INSERT INTO audit (user_id,action,details) VALUES (?,?,?)',[$u['id'],$action,json_encode($record,JSON_THROW_ON_ERROR)]);$db->commit();output(snapshot($db,$u));
}catch(JsonException $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fail(400,'JSON inválido.');}
catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('Padaria API: '.$e->getMessage());fail(500,'Falha no servidor. Verifique a instalação ou tente novamente sem repetir a operação.');}
