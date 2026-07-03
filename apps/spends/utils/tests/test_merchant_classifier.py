import sys
import tracemalloc
import unittest
from pathlib import Path
BASE_DIR=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(BASE_DIR))
from expense_ocr_worker import infer_currency_from_text,parse_amount_and_currency,parse_amount_line_only,resolve_store_match
from merchant_classifier import MerchantClassifier,read_csv
SAMPLE_PATH=Path(__file__).resolve().parent/"sample_transactions.csv"
class MerchantClassifierTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls)->None:
        cls.classifier=MerchantClassifier()
    def test_known_aliases(self)->None:
        cases={"LIDL POLSKA SP Z O O":"LIDL","PLATNOSC KARTA BIEDRONKA NR 123":"BIEDRONKA","AUCHAN RETAIL POLSKA":"AUCHAN","CASTORAMA POLSKA GDANSK":"CASTORAMA"}
        for description,merchant in cases.items():
            with self.subTest(description=description):
                self.assertEqual(self.classifier.classify(description).merchant,merchant)
    def test_multilingual_and_noisy_aliases(self)->None:
        cases={"ЛИДЛ СОФИЯ":"LIDL","BEDRONKA 446 BIALYSTOK":"BIEDRONKA","BIDRONKA EXPRESS":"BIEDRONKA","АШАН ТРК МОСКВА":"AUCHAN","ASHAN CITY ALMATY":"AUCHAN","КАСТОРАМА 005 МОСКВА":"CASTORAMA","KASTORAMA ONLINE":"CASTORAMA"}
        for description,merchant in cases.items():
            with self.subTest(description=description):
                self.assertEqual(self.classifier.classify(description).merchant,merchant)
    def test_user_examples_with_noise(self)->None:
        cases={"TRIUMPH 123 WARSAW":"TRIUMPH","CASTORAMA*POLSKA#998":"CASTORAMA","HB S.C. POZNAN":"HB_S_C","CARREFOUR EXPRESS 44":"CARREFOUR","STOKROTKA/POZNAN":"STOKROTKA","ВЕЛОПЛАНЕТА КИЇВ":"VELOPLANETA","STRONH 2024":"STRONH","FAiNI LODi #55":"FAINI_LODI","FO-P PIKA DANYLO KYRYL MARKET":"FO_P_PIKA_DANYLO_KYRYL"}
        for description,merchant in cases.items():
            with self.subTest(description=description):
                self.assertEqual(self.classifier.classify(description).merchant,merchant)
    def test_unrelated_store_stays_other(self)->None:
        result=self.classifier.classify("UNKNOWN MERCHANT 123")
        self.assertEqual(result.merchant,"OTHER")
        self.assertLess(result.score,0.67)
    def test_sample_aggregation(self)->None:
        totals,classified,unknown=self.classifier.aggregate_rows(read_csv(SAMPLE_PATH))
        self.assertEqual(len(classified),13)
        self.assertEqual(len(unknown),0)
        self.assertAlmostEqual(totals["LIDL"],174.95,places=2)
        self.assertAlmostEqual(totals["BIEDRONKA"],86.89,places=2)
        self.assertAlmostEqual(totals["AUCHAN"],157.40,places=2)
        self.assertAlmostEqual(totals["CASTORAMA"],1564.00,places=2)
        self.assertAlmostEqual(totals["CARREFOUR"],22.10,places=2)
    def test_memory_under_limit(self)->None:
        templates=["LIDL POLSKA {index}","BIDRONKA EXPRESS {index}","ASHAN CITY {index}","KASTORAMA ONLINE {index}","CARREFOUR EXPRESS {index}"]
        totals={}
        tracemalloc.start()
        for index in range(10000):
            merchant=self.classifier.classify(templates[index%len(templates)].format(index=index)).merchant
            totals[merchant]=totals.get(merchant,0)+1
        current,peak=tracemalloc.get_traced_memory()
        tracemalloc.stop()
        self.assertEqual(sum(totals.values()),10000)
        self.assertLess(peak/(1024*1024),300.0)
class OCRWorkerMerchantResolutionTests(unittest.TestCase):
    def test_catalog_match_resolves_existing_store(self)->None:
        result=resolve_store_match("BIDRONKA EXPRESS",["Lidl","Biedronka","Auchan"])
        self.assertEqual(result["matched"],"Biedronka")
    def test_non_catalog_existing_store_still_matches(self)->None:
        result=resolve_store_match("Leroy Merlin",["Lidl","Leroy Merlin"])
        self.assertEqual(result["matched"],"Leroy Merlin")
    def test_catalog_suggestion_returned_for_missing_store(self)->None:
        result=resolve_store_match("CASTORAMA POLSKA GDANSK",["Lidl"])
        self.assertIsNone(result["matched"])
        self.assertEqual(result["suggested_store"],"CASTORAMA")
class OCRWorkerCurrencyTests(unittest.TestCase):
    def test_parse_amount_and_currency_supports_symbols_and_codes(self)->None:
        self.assertEqual(parse_amount_and_currency("12,40 zł"),(12.4,"PLN",""))
        self.assertEqual(parse_amount_and_currency("299.99 ₽"),(299.99,"RUB",""))
        self.assertEqual(parse_amount_and_currency("BYN 45,67"),(45.67,"BYN",""))
        self.assertEqual(parse_amount_and_currency("129.90 грн"),(129.9,"UAH",""))
    def test_parse_amount_line_only_supports_more_currencies(self)->None:
        self.assertEqual(parse_amount_line_only("BYN 15.50"),(15.5,"BYN"))
        self.assertEqual(parse_amount_line_only("299,90 RUB"),(299.9,"RUB"))
    def test_infer_currency_from_text_prefers_detected_currency(self)->None:
        self.assertEqual(infer_currency_from_text("ИТОГО 299.99 ₽\nНАЛИЧНЫМИ","USD"),"RUB")
        self.assertEqual(infer_currency_from_text("SUMA 15.20 zł\nPARAGON","USD"),"PLN")
        self.assertEqual(infer_currency_from_text("TOTAL 10.00","EUR"),"EUR")
if __name__=="__main__":
    unittest.main()
