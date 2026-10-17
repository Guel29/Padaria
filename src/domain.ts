export type Product = { id: string; name: string; description: string; category: string; price: number; photo: string; unit?: 'un' | 'kg' };
export type Item = { id: string; productId: string; name: string; photo: string; price: number; quantity: number; time: string; attendant: string; unit?: 'un' | 'kg' };
export type Change = { item: string; before: number; after: number; time: string; attendant: string; unit?: 'un' | 'kg' };
export type Visit = { id: string; number: number; items: Item[]; changes: Change[]; closedAt?: string; cancelledAt?: string };
export type State = { visits: Visit[]; archive: Visit[] };
export const categories = ['Todos', 'Salgados', 'Hambúrgueres', 'Pizzas', 'Pães', 'Bolos', 'Bebidas', 'Outros'];
const photo = (id: string) => `https://images.unsplash.com/${id}?auto=format&fit=crop&w=600&q=85`;
export const products: Product[] = [
 {id:'1',name:'Croissant de manteiga',description:'Folhado, douradinho e feito na casa.',category:'Pães',price:850,photo:photo('photo-1555507036-ab1f4038808a')},
 {id:'2',name:'Pão na chapa',description:'Pão francês quentinho com manteiga.',category:'Pães',price:450,photo:photo('photo-1549931319-a545dcf3bc73')},
 {id:'3',name:'Cappuccino cremoso',description:'Café, leite vaporizado e um toque de cacau.',category:'Bebidas',price:1200,photo:photo('photo-1572442388796-11668a67e53d')},
 {id:'4',name:'Coxinha de frango',description:'Massa crocante e recheio bem temperado.',category:'Salgados',price:750,photo:photo('photo-1601050690597-df0568f70950')},
 {id:'5',name:'Bolo de chocolate',description:'Uma fatia generosa com cobertura da casa.',category:'Bolos',price:1150,photo:photo('photo-1578985545062-69928b1d9587')},
 {id:'6',name:'Suco de laranja',description:'Natural, fresquinho e feito na hora. 300 ml.',category:'Bebidas',price:900,photo:photo('photo-1613478223719-2ab802602423')},
 {id:'7',name:'Hambúrguer da casa',description:'Carne, queijo, salada e pão artesanal.',category:'Hambúrgueres',price:2450,photo:photo('photo-1568901346375-23c9450c58cd')},
 {id:'8',name:'Pizza de muçarela',description:'Fatia de massa leve e queijo derretido.',category:'Pizzas',price:1250,photo:photo('photo-1513104890138-7c749659a591')},
 {id:'9',name:'Pão de queijo',description:'Receita mineira, macio por dentro.',category:'Salgados',price:500,photo:photo('photo-1509440159596-0249088772ff')},
 {id:'11',name:'Pão francês por peso',description:'Informe em kg o peso líquido indicado na balança.',category:'Pães',price:1890,unit:'kg',photo:photo('photo-1549931319-a545dcf3bc73')},
 {id:'12',name:'Bolo caseiro por peso',description:'Venda por kg. Peso líquido, sem embalagem.',category:'Bolos',price:3990,unit:'kg',photo:photo('photo-1578985545062-69928b1d9587')},
 {id:'10',name:'Cookie artesanal',description:'Com generosos pedaços de chocolate.',category:'Outros',price:650,photo:photo('photo-1499636136210-6f4ee915583e')},
];
export const money = (cents: number) => (cents / 100).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
export type Unit = 'un' | 'kg';
export function validQuantity(quantity: number, unit: Unit = 'un', allowZero = false) {
 return Number.isFinite(quantity) && quantity >= (allowZero ? 0 : unit === 'kg' ? 0.001 : 1) && quantity <= (unit === 'kg' ? 99.999 : 99) && (unit === 'kg' ? Math.abs(quantity * 1000 - Math.round(quantity * 1000)) < 1e-8 : Number.isInteger(quantity));
}
export function parseWeight(value: string) {
 return /^\d+(?:[.,]\d{1,3})?$/.test(value.trim()) ? Number(value.trim().replace(',', '.')) : NaN;
}
export function quantityLabel(quantity: number, unit: Unit = 'un') {
 return unit === 'kg' ? `${quantity.toLocaleString('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 })} kg` : `${quantity} un.`;
}
export const subtotal = (item: Item) => item.unit === 'kg' ? Math.round(item.price * Math.round(item.quantity * 1000) / 1000) : item.price * item.quantity;
export const total = (items: Item[]) => items.reduce((sum,i)=>sum+subtotal(i),0);
export function seed(): State { return {archive:[],visits:[2,5,8,11,14,19].map((number,index)=>({id:`demo-${number}`,number,changes:[],items:[{id:`seed-${number}`,productId:products[index].id,name:products[index].name,photo:products[index].photo,price:products[index].price,quantity:index%2+1,time:new Date(Date.now()-(index+1)*9*60000).toISOString(),attendant:'000001'}]}))}; }
export function addItem(state: State,number: number,item: Item,visitId: string): State {
 if (!validQuantity(item.quantity,item.unit)) throw new Error('Quantidade inválida');
 if(state.visits.some(v=>v.items.some(i=>i.id===item.id)) || state.archive.some(v=>v.items.some(i=>i.id===item.id))) return state;
 const exists=state.visits.find(v=>v.number===number);
 return {...state,visits:exists?state.visits.map(v=>v.number===number?{...v,items:[...v.items,item]}:v):[...state.visits,{id:visitId,number,items:[item],changes:[]}]};
}
/** Empty visits are cancelled, never marked paid. Also repairs older saved state. */
export function releaseEmptyVisits(state: State): State {
 const empty=state.visits.filter(v=>v.items.length===0);
 if(!empty.length)return state;
 return {visits:state.visits.filter(v=>v.items.length>0),archive:[...state.archive,...empty.map(v=>({...v,cancelledAt:new Date().toISOString()}))]};
}
export function editItem(state: State,visitId: string,itemId: string,quantity: number,attendant: string): State {
 const original=state.visits.find(v=>v.id===visitId)?.items.find(i=>i.id===itemId);
 if(!validQuantity(quantity, original?.unit, true)) throw new Error('Quantidade inválida');
 return releaseEmptyVisits({...state,visits:state.visits.map(v=>{if(v.id!==visitId)return v; const item=v.items.find(i=>i.id===itemId);if(!item)return v;return {...v,items:quantity===0?v.items.filter(i=>i.id!==itemId):v.items.map(i=>i.id===itemId?{...i,quantity}:i),changes:[...v.changes,{item:item.name,before:item.quantity,after:quantity,unit:item.unit,time:new Date().toISOString(),attendant}]};})});
}
export function closeVisit(state: State,visitId: string): State {
 const visit=state.visits.find(v=>v.id===visitId);if(!visit)return state;
 return {visits:state.visits.filter(v=>v.id!==visitId),archive:[...state.archive,{...visit,closedAt:new Date().toISOString()}]};
}
