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
$legacy['products'][0]['vrLink']=null;[$out]=vr_catalog($legacy,$incoming,1);expect(count($out['products'])===2&&$out['products'][0]['vrLink']===null&&$out['products'][1]['id']!=='vr:1:439');
[$out]=vr_catalog($s,$incoming,2);expect(count($out['products'])===2&&!$out['products'][1]['active']);
$incoming[0]['unit']=null;[$out,$count,$blocked]=vr_catalog($s,$incoming,1);expect($count===0&&$blocked===1&&!$out['products'][0]['active']);
echo "VR link checks passed: matching, preservation, validation, duplicates, legacy, unlink, store isolation, unsafe units.\n";
