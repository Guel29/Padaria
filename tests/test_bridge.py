import importlib.util, json, tempfile, unittest
from decimal import Decimal
from unittest.mock import patch
from urllib.error import HTTPError
from pathlib import Path
spec=importlib.util.spec_from_file_location('vr_bridge',Path(__file__).parents[1]/'bridge-windows/bridge.py');bridge=importlib.util.module_from_spec(spec);spec.loader.exec_module(bridge)
class BridgeTests(unittest.TestCase):
 def config(self):
  return {'url':'https://example.test','source':'00000000-0000-4000-a000-000000000001','store':1,'lookback_days':45,'interval':30,'product_ids':[999]}
 def response(self,ids,**overrides):
  return json.dumps({'source':self.config()['source'],'store':1,'productIds':ids,**overrides}).encode()
 def select(self,raw):
  class Response:
   def __enter__(self):return self
   def __exit__(self,*args):pass
   def read(self,n):return raw
  with patch.object(bridge,'build_opener') as opener:
   opener.return_value.open.return_value=Response()
   result=bridge.selection_config(self.config(),{'token':'test-token'})
   request=opener.return_value.open.call_args.args[0]
   self.assertEqual(request.get_method(),'GET')
   self.assertEqual(request.get_header('X-padaria-bridge-token'),'test-token')
   self.assertNotIn('test-token',request.full_url)
   return result
 def test_selection_from_admin_replaces_local_and_accepts_empty(self):
  self.assertEqual(self.select(self.response([439,440]))['product_ids'],[439,440])
  self.assertEqual(self.select(self.response([]))['product_ids'],[])
  self.assertEqual(self.config()['product_ids'],[999])
 def test_selection_rejects_wrong_origin_store_and_invalid_ids(self):
  for raw in [self.response([439],source='other'),self.response([439],store=2),self.response([True]),self.response([439,439]),self.response(list(range(1,202))),b'not json']:
   with self.assertRaises(bridge.SafeError):self.select(raw)
 def test_failed_selection_never_falls_back_or_creates_pending(self):
  with tempfile.TemporaryDirectory() as d,patch.object(bridge,'STATE',Path(d)),patch.object(bridge,'build_opener') as opener:
   opener.return_value.open.side_effect=HTTPError('https://example.test',401,'Unauthorized',{},None)
   with self.assertRaises(bridge.SafeError):bridge.cycle(self.config(),{'token':'test-token'},lambda c,s:self.fail('must not read VR'))
   self.assertFalse((Path(d)/'pendente.json').exists())
 def test_each_new_cycle_uses_current_admin_links(self):
  with tempfile.TemporaryDirectory() as d,patch.object(bridge,'STATE',Path(d)):
   ids=iter([[439],[440],[]]);seen=[]
   def selector(c,s):return {**c,'product_ids':next(ids)}
   def collector(c,s):seen.append(c['product_ids']);return {'products':[],'open':[],'history':[]}
   for _ in range(3):bridge.cycle(self.config(),{},collector,lambda *args:None,selector)
   self.assertEqual(seen,[[439],[440],[]])
 def test_units(self):
  self.assertEqual(bridge.unit('UN','UN'),'un')
  self.assertEqual(bridge.unit(' KG ','KG'),'kg')
  self.assertIsNone(bridge.unit('UN','KG'))
  self.assertIsNone(bridge.unit('CX','CX'))
 def test_amounts(self):
  self.assertEqual(bridge.cents(Decimal('4.5000')),450)
  self.assertEqual(bridge.cents(Decimal('0.005')),1)
  self.assertEqual(bridge.barcode(Decimal('439.000')), '439')
  with self.assertRaises(bridge.SafeError):bridge.barcode(Decimal('439.5'))
 def test_pending_replay_is_identical_and_not_recollected(self):
  with tempfile.TemporaryDirectory() as d:
   original=bridge.STATE;bridge.STATE=Path(d)
   try:
    calls=[];payloads=[]
    def collect(c,s):calls.append(True);return {'products':[],'open':[],'history':[]}
    def fail(c,s,p):payloads.append(json.dumps(p,sort_keys=True));raise bridge.SafeError('simulated network failure')
    with self.assertRaises(bridge.SafeError):bridge.cycle({'source':'test','store':1}, {}, collect,fail,lambda c,s:c)
    self.assertTrue((Path(d)/'pendente.json').exists())
    bridge.cycle({'source':'test','store':1}, {}, collect,lambda c,s,p:payloads.append(json.dumps(p,sort_keys=True)),lambda c,s:(_ for _ in ()).throw(AssertionError('pending must not fetch selection')))
    self.assertEqual(len(calls),1);self.assertEqual(payloads[0],payloads[1]);self.assertFalse((Path(d)/'pendente.json').exists());self.assertEqual(json.loads((Path(d)/'estado.json').read_text())['sequence'],1)
   finally:bridge.STATE=original
 def test_no_http_or_url_credentials(self):
  c={'url':'http://example.com','product_ids':[439],'store':1,'lookback_days':45,'interval':30,'source':'00000000-0000-4000-a000-000000000001'}
  with self.assertRaises(bridge.SafeError):bridge.validate_config(c)
  c['url']='https://x:password@example.com'
  with self.assertRaises(bridge.SafeError):bridge.validate_config(c)
 def test_bounds_never_silently_truncate(self):
  with self.assertRaises(bridge.SafeError):bridge.bounded([1,2],1,'test')
if __name__=='__main__':unittest.main()
