import importlib.util, json, tempfile, unittest
from decimal import Decimal
from pathlib import Path
spec=importlib.util.spec_from_file_location('vr_bridge',Path(__file__).parents[1]/'bridge-windows/bridge.py');bridge=importlib.util.module_from_spec(spec);spec.loader.exec_module(bridge)
class BridgeTests(unittest.TestCase):
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
    with self.assertRaises(bridge.SafeError):bridge.cycle({'source':'test','store':1}, {}, collect,fail)
    self.assertTrue((Path(d)/'pendente.json').exists())
    bridge.cycle({'source':'test','store':1}, {}, collect,lambda c,s,p:payloads.append(json.dumps(p,sort_keys=True)))
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
