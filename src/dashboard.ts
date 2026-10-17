import { subtotal, total } from './domain.ts';
import type { State, Unit } from './domain.ts';
export function localDay(value:string){return new Intl.DateTimeFormat('en-CA',{timeZone:'America/Sao_Paulo',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date(value));}
export function dashboardMetrics(state:State,from:string,to:string,fromTime='00:00',toTime='23:59'){
 const within=(value:string)=>{const day=localDay(value);const time=new Intl.DateTimeFormat('en-GB',{timeZone:'America/Sao_Paulo',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).format(new Date(value));return (!from||day>=from)&&(!to||day<=to)&&time>=(fromTime||'00:00')&&time<=(toTime||'23:59');};
 const visits=[...state.visits,...state.archive];
 const items=visits.flatMap(v=>v.items).filter(i=>within(i.time));
 const products:Record<string,{id:string;name:string;quantity:number;unit?:Unit;value:number;entries:number}>={};
 for(const item of items){const key=`${item.productId}-${item.unit||'un'}`;const row=products[key]||{id:key,name:item.name,quantity:0,unit:item.unit,value:0,entries:0};products[key]={...row,quantity:row.quantity+item.quantity,value:row.value+subtotal(item),entries:row.entries+1};}
 const days:Record<string,number>={};for(const item of items){const day=localDay(item.time);days[day]=(days[day]||0)+subtotal(item);}
 const openValue=state.visits.reduce((sum,v)=>sum+total(v.items),0);
 const cancelled=state.archive.filter(v=>v.cancelledAt&&within(v.cancelledAt));
 const edits=visits.flatMap(v=>v.changes.map(c=>({...c,number:v.number}))).filter(c=>within(c.time));
 return {openValue,openAverage:state.visits.length?Math.round(openValue/state.visits.length):0,entries:items.length,launchedValue:items.reduce((sum,i)=>sum+subtotal(i),0),cancelled:cancelled.length,edits:edits.length,products:Object.values(products).sort((a,b)=>b.entries-a.entries||b.value-a.value),days:Object.entries(days).sort(([a],[b])=>a.localeCompare(b)),recent:[...items.map(i=>({time:i.time,label:i.name,detail:'Lançamento',value:subtotal(i)})),...edits.map(c=>({time:c.time,label:c.item,detail:`Correção · comanda ${String(c.number).padStart(2,'0')}`,value:null}))].sort((a,b)=>b.time.localeCompare(a.time)).slice(0,8)};
}
