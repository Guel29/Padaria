import fs from 'node:fs';
import path from 'node:path';
import { zipSync } from 'fflate';
import { products, categories } from '../src/domain.ts';
fs.rmSync('release/hostinger',{recursive:true,force:true});
fs.mkdirSync('release/hostinger/public_html',{recursive:true});
fs.cpSync('dist-hostinger','release/hostinger/public_html',{recursive:true});
fs.cpSync('backend/public','release/hostinger/public_html',{recursive:true});
fs.mkdirSync('release/hostinger/padaria-private',{recursive:true});
for(const name of ['api.php','config.example.php','schema.sql','.htaccess'])fs.copyFileSync(`backend/private/${name}`,`release/hostinger/padaria-private/${name}`);
fs.writeFileSync('release/hostinger/padaria-private/initial.json',JSON.stringify({store:{name:'Padaria do Supermercado',legalName:'',cnpj:'',address:'',city:'',phone:'',logo:''},products:products.map(p=>({...p,active:true})),categories:categories.filter(c=>c!=='Todos'),tableCount:25,users:[]}));
fs.copyFileSync('HOSTINGER.md','release/hostinger/LEIA-ME.md');
console.log('Pacote preparado em release/hostinger. Não contém credenciais reais.');

const entries={};
function collect(dir){for(const name of fs.readdirSync(dir)){const file=path.join(dir,name);if(fs.statSync(file).isDirectory())collect(file);else entries[path.relative('release/hostinger',file).split(path.sep).join('/')]=new Uint8Array(fs.readFileSync(file));}}
collect('release/hostinger');
fs.mkdirSync('downloads',{recursive:true});
fs.writeFileSync('downloads/padaria-hostinger.zip',zipSync(entries,{level:9,mtime:new Date('1980-01-01T00:00:00Z')}));
fs.copyFileSync('downloads/padaria-hostinger.zip','release/padaria-hostinger.zip');
console.log('ZIP pronto: downloads/padaria-hostinger.zip');
