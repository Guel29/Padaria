<?php
// Pure catalog regression checks; no database connection or credentials.
function check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
function text(mixed $s,int $max=180):string{check(is_string($s)&&strlen($s)<=$max,'Texto inválido');return trim($s);}
function image(mixed $s):string{return text($s);}
function uuid():string{return 'new-independent-id';}
require __DIR__.'/../backend/private/vr.php';
$source=file_get_contents(__DIR__.'/../backend/private/api.php');
$start=strpos($source,'function settings(');$end=strpos($source,'function snapshot(',$start);
eval(substr($source,$start,$end-$start));
function expect(bool $ok):void{if(!$ok)throw new RuntimeException('Assertion failed');}
function rejects(callable $fn):void{try{$fn();}catch(RuntimeException){return;}throw new RuntimeException('Expected rejection');}
$p=['id'=>'local-uuid','name'=>'Meu nome','description'=>'Minha descrição','category'=>'Lanches','photo'=>'foto','active'=>true,'price'=>300,'unit'=>'un','vrLink'=>['store'=>1,'productId'=>439]];
$s=['store'=>['name'=>'Padaria'],'products'=>[$p],'categories'=>['Lanches'],'tableCount'=>25];
$incoming=[['id'=>439,'name'=>'Nome VR','priceCents'=>450,'unit'=>'un']];
[$out,$count]=vr_catalog($s,$incoming,1);
expect($count===1&&count($out['products'])===1&&$out['products'][0]['id']==='local-uuid');
expect($out['products'][0]['name']==='Meu nome'&&$out['products'][0]['photo']==='foto'&&$out['products'][0]['category']==='Lanches'&&$out['products'][0]['active']&&$out['products'][0]['price']===450);
$valid=settings($out,['visits'=>[]]);expect($valid['products'][0]['vrLink']===$p['vrLink']);
$duplicate=$s;$duplicate['products'][]=array_replace($p,['id'=>'another']);rejects(fn()=>settings($duplicate,['visits'=>[]]));rejects(fn()=>vr_catalog($duplicate,$incoming,1));
$bad=$s;$bad['products'][0]['vrLink']['productId']='439';rejects(fn()=>settings($bad,['visits'=>[]]));
$legacy=$s;$legacy['products'][0]['id']='vr:1:439';unset($legacy['products'][0]['vrLink']);[$out]=vr_catalog($legacy,$incoming,1);expect(count($out['products'])===1&&$out['products'][0]['vrLink']===$p['vrLink']);
$legacy['products'][0]['vrLink']=null;[$out]=vr_catalog($legacy,$incoming,1);expect($out===$legacy);
[$out]=vr_catalog($s,$incoming,2);expect($out===$s);
$unlinked=$s;$unlinked['products'][0]['vrLink']=null;[$out,$count,$blocked,$ignored]=vr_catalog($unlinked,$incoming,1);expect($out===$unlinked&&$count===0&&$blocked===0&&$ignored===1);
$empty=$s;$empty['products']=[];[$out,$count,$blocked,$ignored]=vr_catalog($empty,$incoming,1);expect($out===$empty&&$ignored===1);
$incoming[0]['unit']=null;[$out,$count,$blocked]=vr_catalog($s,$incoming,1);expect($count===0&&$blocked===1&&!$out['products'][0]['active']);
echo "VR link checks passed: matching, preservation, validation, duplicates, legacy, unlink, store isolation, unsafe units.\n";

expect(vr_selection($s,1)===[439]);expect(vr_selection($s,2)===[]);
$unlinked=$s;$unlinked['products'][0]['vrLink']=null;expect(vr_selection($unlinked,1)===[]);
$inactive=$s;$inactive['products'][0]['active']=false;expect(vr_selection($inactive,1)===[439]);
$many=$s;$many['products']=[];for($i=1;$i<=201;$i++)$many['products'][]=array_replace($p,['id'=>'local-'.$i,'vrLink'=>['store'=>1,'productId'=>$i]]);
rejects(fn()=>vr_selection($many,1));echo "Admin selection checks passed: store filtering, unlinked exclusion, inactive links and bounds.\n";
