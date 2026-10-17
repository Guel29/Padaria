import { useSyncExternalStore } from 'react';
import type { State } from './domain';
import type { Settings, DemoUser } from './settings';
export const serverMode=import.meta.env.VITE_BACKEND==='php';
export type Identity={id:string;login:string;name:string;role:'admin'|'attendant'};
export type Snapshot={settings:Settings;orders:State;version:number;settingsVersion:number};
export type Operation={action:string;body:Record<string,unknown>;operationId:string};
let identity:Identity|null=null;let csrf='';let data:Snapshot|null=null;let connectionError='';let pending:Operation|null=null;
const listeners=new Set<()=>void>();
function emit(){for(const fn of listeners)fn();}
export class ApiError extends Error{constructor(message:string,public status:number){super(message);}}
export async function request<T>(action:string,body?:unknown):Promise<T>{
 let response:Response;try{response=await fetch(`${import.meta.env.BASE_URL}api/index.php?action=${encodeURIComponent(action)}`,{method:body===undefined?'GET':'POST',credentials:'same-origin',cache:'no-store',headers:body===undefined?{}:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:body===undefined?undefined:JSON.stringify(body),signal:AbortSignal.timeout(20000)});}catch{throw new ApiError('Sem confirmação do servidor. Confira a conexão e tente novamente.',0);}
 let json;try{json=await response.json();}catch{throw new ApiError('Resposta inválida do servidor. Verifique a instalação.',502);}
 if(!response.ok){if(response.status===401&&action!=='login'){identity=null;window.dispatchEvent(new Event('padaria-auth'));}throw new ApiError(json.error||'Operação recusada.',response.status);}
 return json;
}
function accept(next:Snapshot){if(!data||next.version>=data.version){data=next;connectionError='';emit();}}
export function getRemote(){if(!data)throw new Error('Sessão ainda não carregada.');return data;}
function subscribe(fn:()=>void){listeners.add(fn);return()=>{listeners.delete(fn);};}
export function useRemote(){return useSyncExternalStore(subscribe,getRemote);}
export function getIdentity(){return identity;}
export async function session(){const result=await request<{user:Identity;csrf:string;data:Snapshot}>('session');identity=result.user;csrf=result.csrf;accept(result.data);restorePending();return identity;}
export async function login(role:Identity['role'],login:string,password:string){const result=await request<{user:Identity;csrf:string;data:Snapshot}>('login',{role,login,password});identity=result.user;csrf=result.csrf;data=null;accept(result.data);restorePending();return identity;}
export async function logout(){await request('logout',{});identity=null;csrf='';data=null;connectionError='';window.dispatchEvent(new Event('padaria-auth'));}
export async function refresh(){try{accept(await request<Snapshot>('snapshot'));}catch(error){connectionError=(error as Error).message;window.dispatchEvent(new Event('padaria-network'));}}
function pendingKey(){return `padaria-pending-${identity?.id}`;}
function restorePending(){try{pending=JSON.parse(localStorage.getItem(pendingKey())||'null');}catch{pending=null;}window.dispatchEvent(new Event('padaria-network'));}
export function getPending(){return pending;}
export function getConnectionError(){return connectionError;}
export async function mutate(action:string,body:Record<string,unknown>){
 if(pending)throw new Error('Há uma operação sem confirmação. Use Reenviar antes de continuar.');
 const op={action,body,operationId:crypto.randomUUID()};
 // Credentials and catalog images are never queued in localStorage. Only orders need durable replay.
 if(action==='user'||action==='settings'){accept(await request<Snapshot>(action,{...body,operationId:op.operationId}));return;}
 // Persist the exact request before sending; a retry always uses the same key and payload.
 localStorage.setItem(pendingKey(),JSON.stringify(op));pending=op;window.dispatchEvent(new Event('padaria-network'));
 return sendPending();
}
export async function sendPending(){if(!pending)return;const op=pending;
 try{const next=await request<Snapshot>(op.action,{...op.body,operationId:op.operationId});accept(next);localStorage.removeItem(pendingKey());pending=null;connectionError='';}
 catch(error){if(error instanceof ApiError&&error.status>=400&&error.status<500){localStorage.removeItem(pendingKey());pending=null;await refresh();}throw error;}
 finally{window.dispatchEvent(new Event('padaria-network'));}
}
export async function saveUser(user:DemoUser,pin:string){await mutate('user',{user,pin});}
