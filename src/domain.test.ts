import { test } from 'node:test';
import assert from 'node:assert/strict';
import { addItem, editItem, closeVisit, total } from './domain.ts';
import type { Item, State } from './domain.ts';
const empty=():State=>({visits:[],archive:[]});
const item=(id:string):Item=>({id,productId:'p',name:'Café',photo:'',price:450,quantity:2,time:new Date().toISOString(),attendant:'000001'});
test('reenvio da mesma operação não duplica; novos lançamentos ficam separados',()=>{let s=addItem(empty(),25,item('a'),'visit-a');s=addItem(s,25,item('a'),'ignored');assert.equal(s.visits[0].items.length,1);s=addItem(s,25,item('b'),'ignored');assert.equal(s.visits[0].items.length,2);assert.equal(total(s.visits[0].items),1800);});
test('edição e remoção preservam valores anteriores no histórico',()=>{let s=addItem(empty(),25,item('a'),'visit-a');s=editItem(s,'visit-a','a',3,'000002');assert.equal(total(s.visits[0].items),1350);assert.equal(s.visits[0].changes[0].before,2);assert.equal(s.visits[0].changes[0].attendant,'000002');s=editItem(s,'visit-a','a',0,'000002');assert.equal(s.visits[0].items.length,0);assert.equal(s.visits[0].changes.length,2);assert.throws(()=>editItem(s,'visit-a','a',-1,'000002'));});
test('encerramento libera a numeração e preserva atendimento anterior',()=>{let s=addItem(empty(),25,item('a'),'visit-a');s=closeVisit(s,'visit-a');assert.equal(s.visits.length,0);assert.equal(s.archive[0].items.length,1);assert.ok(s.archive[0].closedAt);s=addItem(s,25,item('b'),'visit-b');assert.equal(s.visits[0].id,'visit-b');assert.equal(s.visits[0].items.length,1);s=editItem(s,'visit-a','a',99,'000001');assert.equal(s.archive[0].items[0].quantity,2);s=addItem(s,25,item('a'),'ignored');assert.equal(s.visits[0].items.length,1);});
