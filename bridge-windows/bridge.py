"""VR -> Hostinger: SELECT-only PostgreSQL collector. Never writes into VR."""
from __future__ import annotations
import argparse, base64, ctypes, getpass, hashlib, json, logging, os, secrets, sys, time, uuid
from contextlib import contextmanager
from datetime import date, datetime, timezone
from decimal import Decimal, ROUND_HALF_UP
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.parse import urlsplit
from urllib.request import Request, build_opener, HTTPRedirectHandler

ROOT = Path(__file__).resolve().parent
STATE = ROOT / 'dados-locais'
SCHEMA = {
 ('public','produto'): {'id','descricaocompleta','pesavel','id_tipoembalagem'},
 ('public','produtocomplemento'): {'id_produto','id_loja','precovenda','id_tipoembalagem'},
 ('public','tipoembalagem'): {'id','descricao'},
 ('public','produtoautomacao'): {'id_produto','codigobarras','id_tipoembalagem','qtdembalagem'},
 ('pdv','ficha'): {'id','id_loja','numeroficha','datahora'},
 ('pdv','fichaitem'): {'id','id_ficha','sequencia','codigobarras','quantidade','precovenda','iscancelado'},
 ('ficha','ficha'): {'id','id_loja','numeroficha','datahora','data','numerocupom','ecf'},
 ('ficha','fichaitem'): {'id','id_ficha','sequencia','codigobarras','quantidade','precovenda','iscancelado','id_vendaitem'},
 ('pdv','vendaitem'): {'id','id_venda','id_produto','quantidade','precovenda','valortotal','cancelado'},
 ('pdv','venda'): {'id','id_loja','data','numerocupom','ecf','cancelado'},
}
class SafeError(Exception): pass
class Blob(ctypes.Structure):
    _fields_ = [('size', ctypes.c_ulong), ('data', ctypes.POINTER(ctypes.c_ubyte))]
def protect(raw: bytes, decrypt=False) -> bytes:
    if os.name != 'nt': raise SafeError('A configuração de credenciais deve ser feita no Windows com DPAPI.')
    buf=ctypes.create_string_buffer(raw); inp=Blob(len(raw),ctypes.cast(buf,ctypes.POINTER(ctypes.c_ubyte))); out=Blob()
    fn=ctypes.windll.crypt32.CryptUnprotectData if decrypt else ctypes.windll.crypt32.CryptProtectData
    if not fn(ctypes.byref(inp), None, None, None, None, 1, ctypes.byref(out)): raise SafeError('Falha no cofre do Windows. Use o mesmo usuário que configurou a ponte.')
    try: return ctypes.string_at(out.data,out.size)
    finally: ctypes.windll.kernel32.LocalFree(out.data)
def save_json(path: Path, obj):
    temp=path.with_suffix('.tmp');temp.write_text(json.dumps(obj,ensure_ascii=False),encoding='utf-8');os.replace(temp,path)
def config():
    try: c=json.loads((STATE/'config.json').read_text(encoding='utf-8')); secret=json.loads(protect((STATE/'segredos.dat').read_bytes(),True))
    except SafeError: raise
    except Exception: raise SafeError('Configuração indisponível. Execute Configurar.bat no mesmo usuário do Windows.')
    validate_config(c);return c,secret

def validate_config(c):
    u=urlsplit(c['url'])
    if u.scheme!='https' or not u.hostname or u.username or u.password or u.query or u.fragment or u.path not in ('','/'):
        raise SafeError('Use a origem HTTPS da Hostinger, sem caminhos, usuário ou senha.')
    if not isinstance(c['store'],int) or c['store']<1: raise SafeError('Código de loja inválido.')
    ids=c['product_ids']
    if not isinstance(ids,list) or not 1<=len(ids)<=200 or any(type(i)!=int or i<1 for i in ids): raise SafeError('Selecione de 1 a 200 IDs de produtos. Não existe importação irrestrita.')
    if not 1<=c['lookback_days']<=365 or not 20<=c['interval']<=3600: raise SafeError('Período ou intervalo inválido.')
    if c.get('sslmode','prefer') not in ('prefer','require','verify-ca','verify-full'):raise SafeError('Modo SSL inválido. Não use sslmode=disable.')
    uuid.UUID(c['source'])

def configure():
    if os.name!='nt': raise SafeError('Execute Configurar.bat no computador Windows da loja.')
    STATE.mkdir(exist_ok=True)
    if (STATE/'config.json').exists(): raise SafeError('Já existe configuração. Preserve dados-locais; consulte o guia antes de reconfigurar.')
    print('Não use a conta postgres. Solicite um usuário PostgreSQL exclusivo, com acesso somente SELECT.')
    host=input('Servidor PostgreSQL [192.168.30.250]: ').strip() or '192.168.30.250'
    port=int(input('Porta [8745]: ').strip() or '8745');database=input('Banco [vr]: ').strip() or 'vr'
    user=input('Usuário de leitura PostgreSQL: ').strip();password=getpass.getpass('Senha PostgreSQL (não aparece): ')
    if not user or not password or user.lower()=='postgres': raise SafeError('Use um usuário exclusivo de leitura, diferente de postgres.')
    ids=sorted(set(int(v.strip()) for v in input('IDs de produtos autorizados, separados por vírgula (ex.: 439): ').split(',')))
    c={'url':(input('Origem HTTPS [https://padaria.instara.com.br]: ').strip() or 'https://padaria.instara.com.br').rstrip('/'),'host':host,'port':port,'database':database,'user':user,'store':int(input('Loja [1]: ').strip() or '1'),'product_ids':ids,'lookback_days':45,'interval':30,'source':str(uuid.uuid4())}
    validate_config(c);token=secrets.token_urlsafe(48)
    # Only the current Windows user can decrypt this file (not machine-wide DPAPI).
    encrypted=protect(json.dumps({'password':password,'token':token}).encode('utf-8'))
    (STATE/'segredos.dat').write_bytes(encrypted);save_json(STATE/'config.json',c)
    lines="\n// Ponte de leitura VR; adicionar dentro do array de config.php:\n 'vr_bridge_token_hash' => '%s',\n 'vr_source_id' => '%s',\n 'vr_store_id' => %d,\n"%(hashlib.sha256(token.encode()).hexdigest(),c['source'],c['store'])
    (STATE/'hostinger-config.txt').write_text(lines,encoding='utf-8')
    print('Configuração salva. Copie as três opções de dados-locais/hostinger-config.txt para o config.php privado da Hostinger. Esse arquivo não contém a senha nem o token original.')

PRODUCTS_SQL='''SELECT p.id, p.descricaocompleta, p.pesavel, pc.precovenda,
 ep.descricao AS unit_product, el.descricao AS unit_store
 FROM public.produto p JOIN public.produtocomplemento pc ON pc.id_produto=p.id
 LEFT JOIN public.tipoembalagem ep ON ep.id=p.id_tipoembalagem
 LEFT JOIN public.tipoembalagem el ON el.id=pc.id_tipoembalagem
 WHERE pc.id_loja=%s AND p.id=ANY(%s) ORDER BY p.id'''
BARCODES_SQL='''SELECT pa.id_produto, pa.codigobarras, pa.qtdembalagem, e.descricao AS unit
 FROM public.produtoautomacao pa LEFT JOIN public.tipoembalagem e ON e.id=pa.id_tipoembalagem
 WHERE pa.id_produto=ANY(%s) ORDER BY pa.id_produto, pa.codigobarras LIMIT 5001'''
OPEN_SQL='''SELECT id, numeroficha, datahora FROM pdv.ficha WHERE id_loja=%s ORDER BY id LIMIT 1001'''
OPEN_ITEMS_SQL='''SELECT i.id,i.id_ficha,i.sequencia,i.codigobarras,i.quantidade,i.precovenda,i.iscancelado
 FROM pdv.fichaitem i JOIN pdv.ficha f ON f.id=i.id_ficha WHERE f.id_loja=%s ORDER BY i.id LIMIT 10001'''
CLOSED_SQL='''SELECT id,numeroficha,datahora,data,numerocupom,ecf FROM ficha.ficha
 WHERE id_loja=%s AND datahora >= CURRENT_DATE - %s::integer ORDER BY id LIMIT 2001'''
CLOSED_ITEMS_SQL='''SELECT i.id,i.id_ficha,i.sequencia,i.codigobarras,i.quantidade,i.precovenda,i.iscancelado,
 i.id_vendaitem,vi.id_venda,vi.id_produto,vi.valortotal,vi.cancelado AS sale_item_cancelled,
 v.id AS linked_sale_id,v.id_loja AS sale_store,v.data AS sale_date,v.numerocupom AS sale_coupon,
 v.ecf AS sale_ecf,v.cancelado AS sale_cancelled
 FROM ficha.fichaitem i JOIN ficha.ficha f ON f.id=i.id_ficha
 LEFT JOIN pdv.vendaitem vi ON vi.id=i.id_vendaitem
 LEFT JOIN pdv.venda v ON v.id=vi.id_venda
 WHERE f.id_loja=%s AND f.datahora >= CURRENT_DATE - %s::integer
 ORDER BY i.id LIMIT 20001'''

def cents(value):
    if value is None: raise SafeError('Preço ausente no VR. Nenhum dado foi enviado.')
    return int((Decimal(value)*100).quantize(Decimal('1'),rounding=ROUND_HALF_UP))
def scalar(v):
    if isinstance(v,(datetime,date)): return v.isoformat()
    if isinstance(v,Decimal):return str(v)
    return v

def barcode(value):
    n=Decimal(value)
    if not n.is_finite() or n<0 or n!=n.to_integral_value():raise SafeError('Código de barras numérico inválido no VR.')
    return str(int(n))

def unit(product,store):
    p=str(product or '').strip().upper();s=str(store or '').strip().upper()
    return {'UN':'un','KG':'kg'}.get(s) if p==s else None

def bounded(rows,maximum,label):
    if len(rows)>maximum:raise SafeError('Limite de %s excedido. Reduza o período/escopo; uma leitura incompleta não será enviada.'%label)
    return rows

def collect(c,secret):
    import psycopg
    from psycopg.rows import dict_row
    try:
        with psycopg.connect(host=c['host'],port=c['port'],dbname=c['database'],user=c['user'],password=secret['password'],connect_timeout=8,application_name='PadariaPonteLeitura',sslmode=c.get('sslmode','prefer'),sslrootcert=c.get('sslrootcert'),options='-c default_transaction_read_only=on -c statement_timeout=10000 -c idle_in_transaction_session_timeout=15000',row_factory=dict_row) as db:
            with db.transaction():
                db.execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY')
                if db.execute('SHOW transaction_read_only').fetchone()['transaction_read_only']!='on': raise SafeError('A conexão não está em modo somente leitura.')
                cols=db.execute("SELECT table_schema,table_name,column_name FROM information_schema.columns WHERE table_schema IN ('public','pdv','ficha')").fetchall()
                actual={}
                for r in cols:actual.setdefault((r['table_schema'],r['table_name']),set()).add(r['column_name'])
                for table,required in SCHEMA.items():
                    if not required<=actual.get(table,set()):raise SafeError('Estrutura ou permissão divergente em %s.%s; confirme com o suporte.'%table)
                prods=db.execute(PRODUCTS_SQL,(c['store'],c['product_ids'])).fetchall()
                if len(prods)!=len(c['product_ids']):raise SafeError('Algum produto selecionado não existe ou não possui preço na loja. Nenhum dado foi enviado.')
                barcodes=bounded(db.execute(BARCODES_SQL,(c['product_ids'],)).fetchall(),5000,'códigos de barras')
                opened=bounded(db.execute(OPEN_SQL,(c['store'],)).fetchall(),1000,'fichas abertas')
                oi=bounded(db.execute(OPEN_ITEMS_SQL,(c['store'],)).fetchall(),10000,'itens abertos')
                closed=bounded(db.execute(CLOSED_SQL,(c['store'],c['lookback_days'])).fetchall(),2000,'fichas históricas')
                ci=bounded(db.execute(CLOSED_ITEMS_SQL,(c['store'],c['lookback_days'])).fetchall(),20000,'itens históricos')
    except SafeError:raise
    except Exception as e:raise SafeError('Leitura do VR falhou (%s). Confira rede, permissões e estrutura; nenhuma escrita foi realizada.'%type(e).__name__) from None
    products=[]
    for p in prods:
        products.append({'id':p['id'],'name':p['descricaocompleta'],'priceCents':cents(p['precovenda']),'unit':unit(p['unit_product'],p['unit_store']),'unitProduct':str(p['unit_product'] or '').strip(),'unitStore':str(p['unit_store'] or '').strip(),'pesavel':p['pesavel'],'barcodes':[{'code':barcode(b['codigobarras']),'unit':str(b['unit'] or '').strip(),'pack':b['qtdembalagem']} for b in barcodes if b['id_produto']==p['id']]})
    def build(headers,items,history):
        result=[]
        for h in headers:
            record={'id':h['id'],'number':h['numeroficha'],'at':scalar(h['datahora']),'items':[]}
            if history:record.update({'date':scalar(h['data']),'coupon':h['numerocupom'],'ecf':h['ecf']})
            for i in items:
                if i['id_ficha']!=h['id']:continue
                item={'id':i['id'],'sequence':i['sequencia'],'barcode':barcode(i['codigobarras']),'quantity':str(i['quantidade']),'priceCents':cents(i['precovenda']),'cancelled':i['iscancelado']}
                if history:
                    item['saleItemId']=i['id_vendaitem'];item['sale']=None
                    # A link must match store, coupon, ECF and date; do not infer from value or product.
                    if i['linked_sale_id'] is not None:
                        match=i['sale_store']==c['store'] and i['sale_coupon']==h['numerocupom'] and i['sale_ecf']==h['ecf'] and i['sale_date']==h['data']
                        item['sale']={'id':i['linked_sale_id'],'itemId':i['id_vendaitem'],'productId':i['id_produto'],'date':scalar(i['sale_date']),'coupon':i['sale_coupon'],'ecf':i['sale_ecf'],'cancelled':i['sale_cancelled'],'itemCancelled':i['sale_item_cancelled'],'valueCents':cents(i['valortotal']),'linkVerified':bool(match)}
                record['items'].append(item)
            result.append(record)
        return result
    return {'products':products,'open':build(opened,oi,False),'history':build(closed,ci,True),'lookbackDays':c['lookback_days']}

class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):raise SafeError('O endereço HTTPS redirecionou. Confira a URL; o token não será encaminhado a outro endereço.')
def upload(c,secret,payload):
    raw=json.dumps(payload,ensure_ascii=False,separators=(',',':')).encode('utf-8')
    if len(raw)>8*1024*1024:raise SafeError('Leitura maior que 8 MB. Reduza o escopo; nada foi enviado.')
    req=Request(c['url']+'/api/index.php?action=bridge',data=raw,headers={'Content-Type':'application/json','X-Padaria-Bridge-Token':secret['token']},method='POST')
    try:
        with build_opener(NoRedirect()).open(req,timeout=30) as response:ack=json.loads(response.read(16384))
    except SafeError:raise
    except HTTPError as e:raise SafeError('Hostinger recusou a leitura (HTTP %s). Confira migração, token, origem e sequência. A pendência foi preservada.'%e.code) from None
    except Exception:raise SafeError('Envio sem confirmação. A pendência foi preservada para reenvio idempotente.') from None
    if ack.get('batchId')!=payload['batchId'] or ack.get('sequence')!=payload['sequence']:raise SafeError('Confirmação não corresponde à leitura enviada. Pendência preservada.')

def cycle(c,secret,collector=collect,sender=upload):
    STATE.mkdir(exist_ok=True);pending=STATE/'pendente.json';checkpoint=STATE/'estado.json'
    if pending.exists():payload=json.loads(pending.read_text(encoding='utf-8'))
    else:
        previous=json.loads(checkpoint.read_text(encoding='utf-8')) if checkpoint.exists() else {'sequence':0}
        payload={'schemaVersion':1,'source':c['source'],'store':c['store'],'sequence':previous['sequence']+1,'batchId':str(uuid.uuid4()),'capturedAt':datetime.now(timezone.utc).isoformat(),'data':collector(c,secret)}
        save_json(pending,payload)
    if payload['source']!=c['source']:raise SafeError('Pendência de outra instalação. Preserve os arquivos e confira o guia.')
    sender(c,secret,payload)
    save_json(checkpoint,{'sequence':payload['sequence']});pending.unlink()
    logging.info('Leitura confirmada: sequência %s; produtos %s, fichas abertas %s, históricas %s.',payload['sequence'],len(payload['data']['products']),len(payload['data']['open']),len(payload['data']['history']))

@contextmanager
def single_instance():
    STATE.mkdir(exist_ok=True)
    with (STATE/'execucao.lock').open('a+b') as lock:
        lock.seek(0);lock.write(b'0');lock.flush();lock.seek(0)
        try:
            if os.name=='nt':
                import msvcrt;msvcrt.locking(lock.fileno(),msvcrt.LK_NBLCK,1)
            else:
                import fcntl;fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        except OSError:raise SafeError('A ponte já está em execução neste usuário.')
        try:yield
        finally:
            lock.seek(0)
            if os.name=='nt':msvcrt.locking(lock.fileno(),msvcrt.LK_UNLCK,1)
            else:fcntl.flock(lock,fcntl.LOCK_UN)
def main():
    parser=argparse.ArgumentParser();parser.add_argument('--configure',action='store_true');parser.add_argument('--once',action='store_true');parser.add_argument('--check',action='store_true');args=parser.parse_args()
    logging.basicConfig(level=logging.INFO,format='%(asctime)s %(levelname)s %(message)s')
    if args.configure:configure();return
    c,secret=config()
    with single_instance():
        if args.check:
            snapshot=collect(c,secret);print('Leitura validada, sem envio: %s produtos, %s fichas abertas, %s históricas.'%(len(snapshot['products']),len(snapshot['open']),len(snapshot['history'])));return
        while True:
            try:cycle(c,secret)
            except SafeError as e:
                logging.error('%s',e)
                if args.once:raise
            if args.once:break
            time.sleep(c['interval'])
if __name__=='__main__':
    try:main()
    except (SafeError,ValueError,KeyError) as e:print('Erro:',str(e),file=sys.stderr);sys.exit(1)
    except KeyboardInterrupt:print('Ponte interrompida; pendências preservadas.')
