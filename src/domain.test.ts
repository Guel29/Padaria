import { test } from 'node:test';
import assert from 'node:assert/strict';
import { addItem, editItem, closeVisit, total, releaseEmptyVisits } from './domain.ts';
import type { Item, State } from './domain.ts';
const empty=():State=>({visits:[],archive:[]});
const item=(id:string):Item=>({id,productId:'p',name:'Café',photo:'',price:450,quantity:2,time:new Date().toISOString(),attendant:'000001'});
test('reenvio da mesma operação não duplica; novos lançamentos ficam separados',()=>{let s=addItem(empty(),25,item('a'),'visit-a');s=addItem(s,25,item('a'),'ignored');assert.equal(s.visits[0].items.length,1);s=addItem(s,25,item('b'),'ignored');assert.equal(s.visits[0].items.length,2);assert.equal(total(s.visits[0].items),1800);});
test('edição e remoção preservam valores anteriores no histórico',()=>{let s=addItem(empty(),25,item('a'),'visit-a');s=editItem(s,'visit-a','a',3,'000002');assert.equal(total(s.visits[0].items),1350);assert.equal(s.visits[0].changes[0].before,2);assert.equal(s.visits[0].changes[0].attendant,'000002');s=editItem(s,'visit-a','a',0,'000002');assert.equal(s.visits.length,0);assert.equal(s.archive[0].changes.length,2);assert.ok(s.archive[0].cancelledAt);assert.equal(s.archive[0].closedAt,undefined);assert.throws(()=>editItem(s,'visit-a','a',-1,'000002'));});
test('encerramento libera a numeração e preserva atendimento anterior',()=>{let s=addItem(empty(),25,item('a'),'visit-a');s=closeVisit(s,'visit-a');assert.equal(s.visits.length,0);assert.equal(s.archive[0].items.length,1);assert.ok(s.archive[0].closedAt);s=addItem(s,25,item('b'),'visit-b');assert.equal(s.visits[0].id,'visit-b');assert.equal(s.visits[0].items.length,1);s=editItem(s,'visit-a','a',99,'000001');assert.equal(s.archive[0].items[0].quantity,2);s=addItem(s,25,item('a'),'ignored');assert.equal(s.visits[0].items.length,1);});
test('peso fracionado: arredondamento em centavos por lançamento e edição',()=>{
 const weighted:Item={...item('kg'),price:1890,quantity:0.25,unit:'kg'};
 let s=addItem(empty(),1,weighted,'v');assert.equal(total(s.visits[0].items),473);
 s=editItem(s,'v','kg',0.375,'000001');assert.equal(total(s.visits[0].items),709);assert.equal(s.visits[0].changes[0].unit,'kg');
 assert.throws(()=>addItem(empty(),1,{...weighted,quantity:0.0001},'v'));
 assert.throws(()=>addItem(empty(),1,{...weighted,quantity:NaN},'v'));
 assert.throws(()=>addItem(empty(),1,{...item('unit'),quantity:0.5},'v'));
 assert.throws(()=>editItem(s,'v','kg',0.1234,'000001'));
 s=editItem(s,'v','kg',0,'000001');assert.equal(s.visits.length,0);assert.ok(s.archive[0].cancelledAt);
});

test('remover último item libera comanda e novo pedido ganha atendimento independente',()=>{
 let s=addItem(empty(),1,item('a'),'old');
 s=addItem(s,1,item('b'),'ignored');
 s=editItem(s,'old','a',0,'000001');assert.equal(s.visits.length,1);
 s=editItem(s,'old','b',0,'000001');assert.equal(s.visits.length,0);assert.equal(s.archive[0].changes.length,2);
 s=addItem(s,1,item('c'),'new');assert.equal(s.visits[0].id,'new');assert.equal(s.visits[0].items.length,1);assert.equal(s.archive.length,1);
});
test('corrige atendimentos vazios já salvos, preservando histórico sem arquivar novamente',()=>{
 const s:State={visits:[{id:'old',number:1,items:[],changes:[]}],archive:[]};
 const fixed=releaseEmptyVisits(s);assert.equal(fixed.visits.length,0);assert.equal(fixed.archive.length,1);assert.equal(releaseEmptyVisits(fixed).archive.length,1);
});

import { dashboardMetrics, localDay } from './dashboard.ts';
test('dashboard distingue lançamentos, posição em aberto e cancelamentos por período de Brasília',()=>{
 const i={...item('dash'),time:'2026-10-18T01:30:00.000Z'};
 const state:State={visits:[{id:'open',number:1,items:[i],changes:[]}],archive:[{id:'cancelled',number:2,items:[],changes:[{item:'Café',before:1,after:0,time:'2026-10-17T15:00:00.000Z',attendant:'000001'}],cancelledAt:'2026-10-17T15:00:00.000Z'}]};
 assert.equal(localDay(i.time),'2026-10-17');
 const metrics=dashboardMetrics(state,'2026-10-17','2026-10-17');assert.equal(metrics.entries,1);assert.equal(metrics.launchedValue,900);assert.equal(metrics.openValue,900);assert.equal(metrics.openAverage,900);assert.equal(metrics.cancelled,1);assert.equal(metrics.edits,1);assert.equal(metrics.products[0].quantity,2);
 const next=dashboardMetrics(state,'2026-10-18','2026-10-18');assert.equal(next.entries,0);assert.equal(next.launchedValue,0);assert.equal(next.openValue,900);assert.equal(next.cancelled,0);
 const noData=dashboardMetrics(empty(),'','');assert.equal(noData.openAverage,0);assert.deepEqual(noData.days,[]);
});
test('dashboard filtra faixas de horário do dia em Brasília',()=>{
 const s:State={visits:[{id:'v',number:1,changes:[],items:[{...item('morning'),time:'2026-10-17T12:00:00Z'},{...item('afternoon'),time:'2026-10-17T18:00:00Z'}]}],archive:[]};
 assert.equal(dashboardMetrics(s,'2026-10-17','2026-10-17','06:00','11:59').entries,1);
 assert.equal(dashboardMetrics(s,'2026-10-17','2026-10-17','12:00','17:59').entries,1);
 assert.equal(dashboardMetrics(s,'2026-10-17','2026-10-17','18:00','23:59').entries,0);
});
