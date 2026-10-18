"""Disposable local fixtures only; no production access."""
import json,os,importlib.util
from pathlib import Path
from urllib.request import Request,urlopen
from urllib.parse import urlencode
from urllib.error import HTTPError
f=json.loads(Path(os.environ['VR_TEST_FIXTURE']).read_text())
base='http://localhost:8788/api/index.php?'
def get(params,token):
 try:
  with urlopen(Request(base+urlencode(params),headers={'X-Padaria-Bridge-Token':token}),timeout=10) as r:return r.status,json.load(r)
 except HTTPError as e:return e.code,None
params={'action':'bridge_selection','source':f['source'],'store':1}
code,data=get(params,f['token']);assert code==200,(code,'selection failed')
assert data['source']==f['source'] and data['store']==1
assert get(params,'x'*48)[0]==401
assert get({**params,'store':2},f['token'])[0]==422
assert get({**params,'source':'wrong'},f['token'])[0]==422
spec=importlib.util.spec_from_file_location('bridge',Path(__file__).parents[1]/'bridge-windows/bridge.py');b=importlib.util.module_from_spec(spec);spec.loader.exec_module(b)
c={'host':'localhost','port':55432,'database':'vr_test','user':'vr_reader','store':1,'product_ids':data['productIds'],'lookback_days':45}
products=b.collect(c,{'password':f['pg_password']})['products']
assert {p['id'] for p in products}==set(data['productIds'])
c['product_ids']=[];assert b.collect(c,{'password':f['pg_password']})['products']==[]
print('PASS live: authenticated PHP/MySQL selection, invalid token/source/store denied; selected IDs and empty selection read successfully from PostgreSQL14.')
