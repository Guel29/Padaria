import { useEffect, useState } from 'react';
import { products, categories } from './domain';
import { serverMode, getRemote, useRemote, mutate } from './remote';
import type { Product } from './domain';
export type CatalogProduct = Product & { active: boolean; vrLink?: {store:number;productId:number}|null };
export type DemoUser = { id: string; code: string; name: string; active: boolean };
export type Settings = {
 store: { name: string; legalName: string; cnpj: string; address: string; city: string; phone: string; logo: string };
 products: CatalogProduct[]; categories: string[]; tableCount: number; users: DemoUser[];
};
const KEY='padaria-settings-v1';
export function defaultSettings():Settings{return {store:{name:'Padaria do Supermercado',legalName:'',cnpj:'',address:'',city:'',phone:'',logo:''},products:products.map(p=>({...p,active:true})),categories:categories.filter(c=>c!=='Todos'),tableCount:25,users:[{id:'demo',code:'000001',name:'Atendente demonstração',active:true}]};}
export function readSettings():Settings{if(serverMode)return getRemote().settings;try{const raw=localStorage.getItem(KEY);if(raw){const value=JSON.parse(raw);if(value.store&&Array.isArray(value.products)&&Array.isArray(value.categories)&&Array.isArray(value.users)&&Number.isInteger(value.tableCount)&&value.tableCount>0&&value.tableCount<=100)return value;}}catch{}return defaultSettings();}
export function useSettings(){
 const [settings,setSettings]=useState(readSettings);
 // The build target is immutable; both modes keep their own persistence.
 const remote=serverMode?useRemote():null;
 useEffect(()=>{const sync=()=>setSettings(readSettings());window.addEventListener('storage',sync);window.addEventListener('padaria-settings',sync);return()=>{window.removeEventListener('storage',sync);window.removeEventListener('padaria-settings',sync);};},[]);
 async function save(next:Settings){if(serverMode){await mutate('settings',{settings:next,settingsVersion:getRemote().settingsVersion});return;}localStorage.setItem(KEY,JSON.stringify(next));setSettings(next);window.dispatchEvent(new Event('padaria-settings'));}
 return {settings:remote?.settings||settings,save};
}
/** Images are data URLs, bounded to avoid exhausting local demo storage. */
export function readImage(file:File):Promise<string>{return new Promise((resolve,reject)=>{if(!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>1024*1024){reject(new Error('Use PNG, JPG ou WebP de até 1 MB.'));return;}const reader=new FileReader();reader.onerror=()=>reject(new Error('Não foi possível ler a imagem.'));reader.onload=()=>resolve(String(reader.result));reader.readAsDataURL(file);});}

export function vrLinkOf(p:CatalogProduct): {store:number;productId:number}|null {
 if(p.vrLink!==undefined)return p.vrLink;
 const m=/^vr:([1-9]\d*):([1-9]\d*)$/.exec(p.id);
 return m?{store:Number(m[1]),productId:Number(m[2])}:null;
}
