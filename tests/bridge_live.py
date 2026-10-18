"""Run only against disposable PostgreSQL/MySQL fixtures. No production access."""
import importlib.util,json,os,copy
from pathlib import Path
from urllib.request import Request,urlopen
from urllib.error import HTTPError
spec=importlib.util.spec_from_file_location('bridge',Path(__file__).parents[1]/'bridge-windows/bridge.py');b=importlib.util.module_from_spec(spec);spec.loader.exec_module(b)
t=json.loads(Path(os.environ['VR_TEST_FIXTURE']).read_text())
c={'host':'localhost','port':55432,'database':'vr_test','user':'vr_reader','store':1,'product_ids':[439,440,441],'lookback_days':45,'interval':30,'source':t['source'],'url':'http://localhost:8788'}
secret={'password':t['pg_password'],'token':t['token']}
data=b.collect(c,secret)
assert len(data['products'])==3
assert data['products'][0]['unit']=='un' and data['products'][0]['pesavel'] is True
assert data['products'][0]['priceCents']==450 and data['products'][0]['barcodes'][0]['code']=='439'
assert data['products'][1]['unit']=='kg' and data['products'][2]['unit'] is None
sale=data['history'][0]['items'][0]['sale'];assert sale['id']==215787 and sale['cancelled'] is True and sale['itemCancelled'] is False and sale['linkVerified'] is True
assert data['open'][0]['items'][0]['quantity']=='0.250'
# Payload fields form an explicit whitelist: no customer/attendant/password columns.
assert 'password' not in json.dumps(data) and 'cpf' not in json.dumps(data)
Path('/tmp/padaria-vr-test/collected.json').write_text(json.dumps(data))
print('PASS: PostgreSQL 14: leitura com usuário SELECT, UN apesar de pesavel=true, KG, unidade ambígua, vínculo explícito e cancelamento do cabeçalho.')
import psycopg
with psycopg.connect(host=c['host'],port=c['port'],dbname=c['database'],user=c['user'],password=secret['password'],options='-c default_transaction_read_only=on') as db:
 try:db.execute('DELETE FROM pdv.ficha WHERE false');raise AssertionError('Writing should fail')
 except (psycopg.errors.ReadOnlySqlTransaction,psycopg.errors.InsufficientPrivilege):pass
print('PASS: gravação impedida no PostgreSQL de teste.')
from datetime import datetime,timezone
import uuid
payload={'schemaVersion':1,'source':c['source'],'store':1,'sequence':int(os.environ.get('VR_TEST_SEQUENCE','1')),'batchId':str(uuid.uuid4()),'capturedAt':datetime.now(timezone.utc).isoformat(),'data':data}
def send(p,token=t['token']):
 req=Request(c['url']+'/api/index.php?action=bridge',data=json.dumps(p,separators=(',',':')).encode(),headers={'Content-Type':'application/json','X-Padaria-Bridge-Token':token})
 try:
  with urlopen(req) as response:return response.status,json.loads(response.read())
 except HTTPError as e:return e.code,json.loads(e.read())
assert send(payload,token='invalid-token')[0]==401
status,ack=send(payload);assert status==200,(status,ack)
Path('/tmp/padaria-vr-test/last-payload.json').write_text(json.dumps(payload))
assert send(payload)[1]['duplicate'] is True
bad=copy.deepcopy(payload);bad['data']['products'][0]['priceCents']=1;assert send(bad)[0]==409
old=copy.deepcopy(payload);old['batchId']=str(uuid.uuid4());assert send(old)[0]==409
bad=copy.deepcopy(payload);bad['sequence']=2;bad['batchId']=str(uuid.uuid4());bad['store']=2;assert send(bad)[0]==422
bad=copy.deepcopy(payload);bad['sequence']=2;bad['batchId']=str(uuid.uuid4());bad['data']['products'][0]['unit']='kg';assert send(bad)[0]==422
bad=copy.deepcopy(payload);bad['sequence']=2;bad['batchId']=str(uuid.uuid4());bad['data']['history'][0]['items'][0]['sale']['coupon']=1;assert send(bad)[0]==422
print('PASS: PHP/MySQL: token, loja, sequência, deduplicação, integridade de unidade e vínculo cupom/ficha.')
