<?php
// Read-only mirror ingestion. No PostgreSQL connection and no PDV financial mutations.
function vr_int(mixed $v,int $min=1,int $max=9007199254740991):int{check(is_int($v)&&$v>=$min&&$v<=$max,'Número VR inválido.');return $v;}
function vr_optional_int(mixed $v):?int{return $v===null?null:vr_int($v,0);}
function vr_bool(mixed $v,bool $nullable=false):?bool{check(is_bool($v)||($nullable&&$v===null),'Estado VR inválido.');return $v;}
function vr_list(mixed $v,int $max):array{check(is_array($v)&&array_is_list($v)&&count($v)<=$max,'Lista VR inválida ou muito grande.');return $v;}
function vr_time(mixed $v):string{$s=text($v,50);check((bool)preg_match('/^\d{4}-\d{2}-\d{2}(T| )\d{2}:\d{2}:\d{2}/',$s)&&strtotime($s)!==false,'Horário VR inválido.');return $s;}
function vr_date(mixed $v):?string{if($v===null)return null;$s=text($v,10);check((bool)preg_match('/^\d{4}-\d{2}-\d{2}$/D',$s)&&strtotime($s)!==false,'Data VR inválida.');return $s;}
function vr_decimal(mixed $v):string{$s=text($v,32);check((bool)preg_match('/^-?\d{1,12}(\.\d{1,9})?$/D',$s),'Quantidade VR inválida.');return $s;}
function vr_barcode(mixed $v):string{$s=text($v,32);check((bool)preg_match('/^\d{1,25}$/D',$s),'Código de barras VR inválido.');return $s;}
function vr_data(array $data):array{
 $products=[];$ids=[];
 foreach(vr_list($data['products']??null,200) as $p){check(is_array($p),'Produto VR inválido.');$id=vr_int($p['id']??null);check(!isset($ids[$id]),'Produto VR repetido.');$ids[$id]=true;$up=text($p['unitProduct']??'',10);$us=text($p['unitStore']??'',10);$expected=$up===$us?(['UN'=>'un','KG'=>'kg'][$us]??null):null;check(($p['unit']??null)===$expected,'Unidade VR divergente.');$codes=[];foreach(vr_list($p['barcodes']??null,5000) as $c)$codes[]=['code'=>vr_barcode($c['code']??null),'unit'=>text($c['unit']??'',10),'pack'=>vr_int($c['pack']??null,1,1000000)];$name=text($p['name']??'',200);check($name!=='','Nome VR obrigatório.');$products[]=['id'=>$id,'name'=>$name,'priceCents'=>vr_int($p['priceCents']??null,0,99999999),'unit'=>$expected,'unitProduct'=>$up,'unitStore'=>$us,'pesavel'=>vr_bool($p['pesavel']??null),'barcodes'=>$codes];}
 $out=['products'=>$products,'lookbackDays'=>vr_int($data['lookbackDays']??null,1,365)];
 foreach(['open'=>1000,'history'=>2000] as $kind=>$max){$out[$kind]=[];$seen=[];foreach(vr_list($data[$kind]??null,$max) as $f){check(is_array($f),'Ficha VR inválida.');$fid=vr_int($f['id']??null);check(!isset($seen[$fid]),'Ficha VR repetida.');$seen[$fid]=true;$record=['id'=>$fid,'number'=>vr_int($f['number']??null),'at'=>vr_time($f['at']??null),'items'=>[]];if($kind==='history')$record+=['date'=>vr_date($f['date']??null),'coupon'=>vr_optional_int($f['coupon']??null),'ecf'=>vr_optional_int($f['ecf']??null)];$itemIds=[];
 foreach(vr_list($f['items']??null,20000) as $i){$iid=vr_int($i['id']??null);check(!isset($itemIds[$iid]),'Item VR repetido.');$itemIds[$iid]=true;$item=['id'=>$iid,'sequence'=>vr_int($i['sequence']??null,0),'barcode'=>vr_barcode($i['barcode']??null),'quantity'=>vr_decimal($i['quantity']??null),'priceCents'=>vr_int($i['priceCents']??null,0,99999999),'cancelled'=>vr_bool($i['cancelled']??null,true)];
 if($kind==='history'){$item['saleItemId']=vr_optional_int($i['saleItemId']??null);$item['sale']=null;$sale=$i['sale']??null;if($sale!==null){check(is_array($sale),'Venda vinculada inválida.');$s=['id'=>vr_int($sale['id']??null),'itemId'=>vr_int($sale['itemId']??null),'productId'=>vr_int($sale['productId']??null),'date'=>vr_date($sale['date']??null),'coupon'=>vr_int($sale['coupon']??null,0),'ecf'=>vr_int($sale['ecf']??null,0),'cancelled'=>vr_bool($sale['cancelled']??null),'itemCancelled'=>vr_bool($sale['itemCancelled']??null),'valueCents'=>vr_int($sale['valueCents']??null,0,999999999),'linkVerified'=>vr_bool($sale['linkVerified']??null)];if($s['linkVerified'])check($item['saleItemId']===$s['itemId']&&$record['date']===$s['date']&&$record['coupon']===$s['coupon']&&$record['ecf']===$s['ecf'],'Vínculo ficha/cupom inconsistente.');$item['sale']=$s;}}
 $record['items'][]=$item;}$out[$kind][]=$record;}}
 return $out;
}
function vr_available(PDO $db):bool{return (bool)query($db,"SHOW TABLES LIKE 'vr_sync'")->fetch();}
function vr_receive(PDO $db,array $config,array $body,string $raw):never{
 $hash=$config['vr_bridge_token_hash']??'';$source=$config['vr_source_id']??'';$store=$config['vr_store_id']??null;
 if(!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash)||!is_int($store)||!is_string($source)||$source==='')fail(503,'Ponte VR não configurada no servidor.');
 $header=$_SERVER['HTTP_X_PADARIA_BRIDGE_TOKEN']??'';
 if(!preg_match('/^[A-Za-z0-9_-]{40,200}$/D',$header)||!hash_equals($hash,hash('sha256',$header)))fail(401,'Credencial da ponte inválida.');
 if(!vr_available($db))fail(503,'Importe vr-schema.sql antes de iniciar a ponte.');
 check(($body['schemaVersion']??null)===1&&($body['source']??null)===$source&&($body['store']??null)===$store,'Origem ou loja da ponte inválida.');
 $seq=vr_int($body['sequence']??null);$batch=text($body['batchId']??'',36);check((bool)preg_match('/^[a-f0-9-]{36}$/D',$batch),'Lote inválido.');$at=vr_time($body['capturedAt']??null);check(strtotime($at)<=time()+300,'Relógio da ponte adiantado.');check(is_array($body['data']??null),'Leitura inválida.');$data=vr_data($body['data']);$digest=hash('sha256',$raw);
 $db->beginTransaction();query($db,"INSERT IGNORE INTO vr_sync (id,source_id,store_id,sequence_no,batch_id,payload_hash,captured_at,received_epoch,payload) VALUES (1,'',0,0,'','','',0,'{}')");$old=query($db,'SELECT * FROM vr_sync WHERE id=1 FOR UPDATE')->fetch();
 if($old['source_id']===$source){if($old['batch_id']===$batch){if(!hash_equals($old['payload_hash'],$digest))fail(409,'Lote reutilizado com conteúdo diferente.');$db->commit();output(['ok'=>true,'batchId'=>$batch,'sequence'=>$seq,'duplicate'=>true]);}if($seq<=(int)$old['sequence_no'])fail(409,'Sequência antiga. Preserve o estado da ponte.');}
 query($db,'UPDATE vr_sync SET source_id=?,store_id=?,sequence_no=?,batch_id=?,payload_hash=?,captured_at=?,received_epoch=?,payload=? WHERE id=1',[$source,$store,$seq,$batch,$digest,$at,time(),json_encode($data,JSON_THROW_ON_ERROR)]);$db->commit();output(['ok'=>true,'batchId'=>$batch,'sequence'=>$seq]);
}
function vr_status(PDO $db,array $config):array{
 if(!vr_available($db))return ['ready'=>false,'message'=>'Importe a migração vr-schema.sql.'];$row=query($db,'SELECT * FROM vr_sync WHERE id=1')->fetch();
 if(!$row||!$row['sequence_no'])return ['ready'=>false,'message'=>'Aguardando a primeira leitura da ponte Windows.'];
 $current=($config['vr_source_id']??'')===$row['source_id']&&($config['vr_store_id']??null)===(int)$row['store_id'];
 return ['ready'=>$current,'message'=>$current?'Leitura recebida.':'Origem configurada mudou; aguardando leitura dessa origem.','store'=>(int)$row['store_id'],'sequence'=>(int)$row['sequence_no'],'capturedAt'=>$row['captured_at'],'receivedAt'=>(int)$row['received_epoch'],'data'=>json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR)];
}
// Explicit null unlinks even products originally imported with a vr: ID.
function vr_product_link(array $p):?array{
 if(array_key_exists('vrLink',$p)){
  if($p['vrLink']===null)return null;
  check(is_array($p['vrLink']),'Vínculo VR inválido.');
  return ['store'=>vr_int($p['vrLink']['store']??null),'productId'=>vr_int($p['vrLink']['productId']??null)];
 }
 if(preg_match('/^vr:([1-9][0-9]*):([1-9][0-9]*)$/D',$p['id']??'',$m))return ['store'=>vr_int((int)$m[1]),'productId'=>vr_int((int)$m[2])];
 return null;
}
function vr_catalog(array $s,array $incoming,int $store):array{
 $links=[];$ids=[];
 foreach($s['products'] as $i=>$p){$ids[$p['id']]=true;$link=vr_product_link($p);if($link){$key=$link['store'].':'.$link['productId'];check(!isset($links[$key]),'Código VR associado a mais de um produto.');$links[$key]=$i;}}
 $imported=0;$blocked=0;
 foreach($incoming as $p){$key=$store.':'.$p['id'];$index=$links[$key]??null;
  if(!in_array($p['unit'],['un','kg'],true)||$p['priceCents']<=0){$blocked++;if($index!==null)$s['products'][$index]['active']=false;continue;}
  $link=['store'=>$store,'productId'=>$p['id']];
  if($index!==null){$s['products'][$index]=array_replace($s['products'][$index],['price'=>$p['priceCents'],'unit'=>$p['unit'],'vrLink'=>$link]);}
  else{
   if(!in_array('VR',$s['categories'],true))$s['categories'][]='VR';
   $id='vr:'.$store.':'.$p['id'];if(isset($ids[$id]))$id=uuid();$ids[$id]=true;
   $s['products'][]=['id'=>$id,'name'=>$p['name'],'description'=>'Código VR: '.$p['id'],'category'=>'VR','price'=>$p['priceCents'],'unit'=>$p['unit'],'photo'=>'','active'=>false,'vrLink'=>$link];$links[$key]=array_key_last($s['products']);
  }$imported++;
 }
 check(count($s['categories'])<=100,'O cadastro excederia 100 categorias.');check(count($s['products'])<=500,'O catálogo excederia 500 produtos.');
 return [$s,$imported,$blocked];
}
function vr_import(PDO $db,array $config,array $body,array $user):never{
 $status=vr_status($db,$config);check($status['ready']&&time()-($status['receivedAt']??0)<120&&time()-strtotime($status['capturedAt']??'')<180,'Leitura VR ausente ou desatualizada. Inicie a ponte e tente novamente.');
 $db->beginTransaction();$row=query($db,'SELECT * FROM app_data WHERE id=1 FOR UPDATE')->fetch();if(!$row)fail(503,'Instalação ainda não concluída.');if(($body['settingsVersion']??null)!==(int)$row['settings_version'])fail(409,'Catálogo alterado em outro dispositivo. Atualize e tente novamente.');$s=json_decode($row['settings'],true,512,JSON_THROW_ON_ERROR);[$s,$imported,$blocked]=vr_catalog($s,$status['data']['products'],$status['store']);
 query($db,'UPDATE app_data SET settings=?,settings_version=settings_version+1,version=version+1 WHERE id=1',[json_encode($s,JSON_THROW_ON_ERROR)]);query($db,'INSERT INTO audit (user_id,action,details) VALUES (?,?,?)',[$user['id'],'vr_import',json_encode(['store'=>$status['store'],'sequence'=>$status['sequence'],'imported'=>$imported,'blocked'=>$blocked])]);$db->commit();output(['imported'=>$imported,'blocked'=>$blocked]);
}
