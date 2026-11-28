"""Tests hors reseau : python -m unittest test_sync.py"""
import datetime as dt
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import synchroniser as sync

ROW = {'USERID':46,'Badgenumber':'01091','Name':'NOM TEST','Gender':'Male',
       'CHECKTIME':dt.datetime(2026,7,21,12,35,8),'CHECKTYPE':'I','SENSORID':1}
URL = 'https://exemple.invalid/api/presences/zkteco'
SOURCE = 'TEST-CITRAC'

class Response:
    status = 200
    def __init__(self,data): self.data=data
    def __enter__(self): return self
    def __exit__(self,*args): pass
    def read(self,*args): return json.dumps(self.data).encode()

class Tests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory()
        self.path=Path(self.tmp.name)/'etat.sqlite'
        self.db=sync.ouvrir_etat(self.path,SOURCE,URL)
    def tearDown(self):
        self.db.close();self.tmp.cleanup()
    def test_i_et_I_meme_identite(self):
        a,p=sync.normaliser(ROW,SOURCE)
        b,_=sync.normaliser(dict(ROW,CHECKTYPE='i'),SOURCE)
        self.assertEqual(a,b);self.assertEqual(p['badge'],'01091')
    def test_capteur_type_et_userid_distincts(self):
        a,_=sync.normaliser(ROW,SOURCE)
        for key,value in [('SENSORID',2),('CHECKTYPE','O'),('USERID',47)]:
            self.assertNotEqual(a,sync.normaliser(dict(ROW,**{key:value}),SOURCE)[0])
    def test_relecture_et_retard(self):
        self.assertEqual(sync.memoriser(self.db,[ROW,ROW],SOURCE),(2,1,0))
        older=dict(ROW,CHECKTIME='2026-01-01 07:30:00')
        self.assertEqual(sync.memoriser(self.db,[older,ROW],SOURCE),(2,1,0))
        self.assertEqual(self.db.execute('SELECT count(*) FROM passages WHERE acquitte=0').fetchone()[0],2)
    def test_etat_persiste_apres_redemarrage(self):
        sync.memoriser(self.db,[ROW],SOURCE);self.db.close()
        self.db=sync.ouvrir_etat(self.path,SOURCE,URL)
        self.assertEqual(self.db.execute('SELECT count(*) FROM passages').fetchone()[0],1)
    def test_destination_modifiee_refusee(self):
        with self.assertRaises(sync.ErreurSync): sync.ouvrir_etat(self.path,'AUTRE',URL)
    def test_badge_vide_et_date_invalide(self):
        for row in [dict(ROW,Badgenumber=''),dict(ROW,CHECKTIME='2026-02-30 10:00:00')]:
            with self.assertRaises(sync.ErreurSync): sync.normaliser(row,SOURCE)
    def pending(self):
        sync.memoriser(self.db,[ROW],SOURCE)
        return self.db.execute('SELECT empreinte,donnees FROM passages').fetchall()
    def test_reponse_sans_acquittement_ne_supprime_rien(self):
        pending=self.pending()
        with patch('synchroniser.urllib.request.build_opener') as opener:
            opener.return_value.open.return_value=Response({'recus':1})
            with self.assertRaises(sync.ErreurSync):sync.envoyer(URL,'x'*64,SOURCE,pending,30)
        self.assertEqual(self.db.execute('SELECT acquitte FROM passages').fetchone()[0],0)
    def test_confirmation_explicite(self):
        pending=self.pending()
        with patch('synchroniser.urllib.request.build_opener') as opener:
            opener.return_value.open.return_value=Response({'acquittes':[pending[0][0]],'badgesNonRapproches':{'01091':'Badge sans employe'}})
            ack=sync.envoyer(URL,'x'*64,SOURCE,pending,30)
        self.assertEqual(ack,{pending[0][0]})
        with self.db:
            self.db.executemany('UPDATE passages SET acquitte=1 WHERE empreinte=?',((key,)for key in ack))
        self.assertEqual(sync.memoriser(self.db,[ROW],SOURCE),(1,0,0))
        self.assertEqual(self.db.execute('SELECT count(*) FROM passages WHERE acquitte=0').fetchone()[0],0)
    def test_acquittement_etranger_refuse(self):
        with patch('synchroniser.urllib.request.build_opener') as opener:
            opener.return_value.open.return_value=Response({'acquittes':['f'*64]})
            with self.assertRaises(sync.ErreurSync):sync.envoyer(URL,'x'*64,SOURCE,self.pending(),30)
    def test_panne_reseau_ne_supprime_rien(self):
        pending=self.pending()
        with patch('synchroniser.urllib.request.build_opener') as opener:
            opener.return_value.open.side_effect=TimeoutError()
            with self.assertRaises(sync.ErreurSync):sync.envoyer(URL,'x'*64,SOURCE,pending,30)
        self.assertEqual(self.db.execute('SELECT acquitte FROM passages').fetchone()[0],0)
    def test_pas_redirection(self):
        handler=sync.SansRedirection()
        self.assertIsNone(handler.redirect_request(None,None,302,'',{},'https://autre.invalid'))
    def test_acquittement_partiel(self):
        pending=self.pending()
        with patch('synchroniser.urllib.request.build_opener') as opener:
            opener.return_value.open.return_value=Response({'acquittes':[]})
            self.assertEqual(sync.envoyer(URL,'x'*64,SOURCE,pending,30),set())
    def test_lecture_bornee_et_readonly(self):
        import inspect
        code=inspect.getsource(sync.lignes_access)
        self.assertIn('readonly=True',code);self.assertIn('fetchmany(500)',code)
        import ast
        literals=[n.value for n in ast.walk(ast.parse(code)) if isinstance(n,ast.Constant) and isinstance(n.value,str)]
        self.assertTrue(any(v.startswith('SELECT ') for v in literals))
        self.assertFalse(any(v.upper().startswith(('INSERT ','UPDATE ','DELETE ')) for v in literals))

if __name__=='__main__': unittest.main()
