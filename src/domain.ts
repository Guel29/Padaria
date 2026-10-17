export type Product = { id: string; name: string; description: string; category: string; price: number; photo: string };
export type Item = { id: string; productId: string; name: string; photo: string; price: number; quantity: number; time: string; attendant: string };
export type Change = { item: string; before: number; after: number; time: string; attendant: string };
export type Visit = { id: string; number: number; items: Item[]; changes: Change[]; closedAt?: string };
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
 {id:'10',name:'Cookie artesanal',description:'Com generosos pedaços de chocolate.',category:'Outros',price:650,photo:photo('photo-1499636136210-6f4ee915583e')},
];
export const money = (cents: number) => (cents / 100).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
export const total = (items: Item[]) => items.reduce((sum,i)=>sum+i.price*i.quantity,0);
export function seed(): State { return {archive:[],visits:[2,5,8,11,14,19].map((number,index)=>({id:`demo-${number}`,number,changes:[],items:[{id:`seed-${number}`,productId:products[index].id,name:products[index].name,photo:products[index].photo,price:products[index].price,quantity:index%2+1,time:new Date(Date.now()-(index+1)*9*60000).toISOString(),attendant:'000001'}]}))}; }
export function addItem(state: State,number: number,item: Item,visitId: string): State {
 if(state.visits.some(v=>v.items.some(i=>i.id===item.id)) || state.archive.some(v=>v.items.some(i=>i.id===item.id))) return state;
 const exists=state.visits.find(v=>v.number===number);
 return {...state,visits:exists?state.visits.map(v=>v.number===number?{...v,items:[...v.items,item]}:v):[...state.visits,{id:visitId,number,items:[item],changes:[]}]};
}
export function editItem(state: State,visitId: string,itemId: string,quantity: number,attendant: string): State {
 if(!Number.isInteger(quantity)||quantity<0) throw new Error('Quantidade inválida');
 return {...state,visits:state.visits.map(v=>{if(v.id!==visitId)return v; const item=v.items.find(i=>i.id===itemId);if(!item)return v;return {...v,items:quantity===0?v.items.filter(i=>i.id!==itemId):v.items.map(i=>i.id===itemId?{...i,quantity}:i),changes:[...v.changes,{item:item.name,before:item.quantity,after:quantity,time:new Date().toISOString(),attendant}]};})};
}
export function closeVisit(state: State,visitId: string): State {
 const visit=state.visits.find(v=>v.id===visitId);if(!visit)return state;
 return {visits:state.visits.filter(v=>v.id!==visitId),archive:[...state.archive,{...visit,closedAt:new Date().toISOString()}]};
}
