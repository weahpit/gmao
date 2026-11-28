#!/usr/bin/env python3
"""Access Req_UserCheck -> API CITRAC. Lecture seule, file SQLite, acquittement explicite.
Python 3.11+ ; pyodbc requis uniquement pour Access. Aucune ecriture dans la pointeuse.
"""
from __future__ import annotations
import argparse
import csv
import ctypes
import datetime as dt
import getpass
import hashlib
import json
import logging
from logging.handlers import RotatingFileHandler
import os
from pathlib import Path
import re
import sqlite3
import sys
import urllib.error
import urllib.parse
import urllib.request
from contextlib import contextmanager

HEADERS = ('USERID', 'Badgenumber', 'Name', 'Gender', 'CHECKTIME', 'CHECKTYPE', 'SENSORID')

class ErreurSync(Exception):
    pass

class Blob(ctypes.Structure):
    _fields_ = [('taille', ctypes.c_ulong), ('donnees', ctypes.POINTER(ctypes.c_ubyte))]

def dpapi(value: bytes, protect: bool) -> bytes:
    if os.name != 'nt':
        raise ErreurSync('Le jeton protege DPAPI est reserve a Windows.')
    memory = ctypes.create_string_buffer(value)
    source = Blob(len(value), ctypes.cast(memory, ctypes.POINTER(ctypes.c_ubyte)))
    target = Blob()
    api = ctypes.WinDLL('crypt32', use_last_error=True)
    kernel = ctypes.WinDLL('kernel32', use_last_error=True)
    operation = api.CryptProtectData if protect else api.CryptUnprotectData
    operation.argtypes = [ctypes.POINTER(Blob), ctypes.c_void_p, ctypes.c_void_p, ctypes.c_void_p, ctypes.c_void_p, ctypes.c_ulong, ctypes.POINTER(Blob)]
    operation.restype = ctypes.c_int
    kernel.LocalFree.argtypes = [ctypes.c_void_p]
    kernel.LocalFree.restype = ctypes.c_void_p
    if not operation(ctypes.byref(source), None, None, None, None, 1, ctypes.byref(target)):
        raise ErreurSync('Jeton DPAPI inaccessible : utiliser le meme compte Windows que pour sa configuration.')
    try:
        return ctypes.string_at(target.donnees, target.taille)
    finally:
        kernel.LocalFree(ctypes.cast(target.donnees, ctypes.c_void_p))

def text(value) -> str:
    if value is None:
        return ''
    if isinstance(value, float) and value.is_integer():
        return str(int(value))
    return str(value).strip()

def normaliser(row: dict, source: str) -> tuple[str, dict]:
    raw = row.get('CHECKTIME')
    if isinstance(raw, dt.datetime):
        when = raw.replace(microsecond=0)
    else:
        try:
            when = dt.datetime.fromisoformat(text(raw)).replace(microsecond=0)
        except ValueError:
            raise ErreurSync('CHECKTIME invalide : horodatage Access ou ISO attendu.') from None
    if when.tzinfo is not None:
        when = when.astimezone(dt.timezone.utc).replace(tzinfo=None)
    payload = dict(badge=text(row.get('Badgenumber')), quand=when.isoformat(timespec='seconds'),
                   userid=text(row.get('USERID')), nom=text(row.get('Name')), genre=text(row.get('Gender')),
                   type=text(row.get('CHECKTYPE')).upper(), capteur=text(row.get('SENSORID')))
    for key, limit in [('badge',60),('userid',60),('nom',255),('genre',30),('type',10),('capteur',60)]:
        value = payload[key]
        if len(value) > limit or '|' in value or (key == 'badge' and not value):
            raise ErreurSync('Champ de passage invalide : '+key)
    canonical = '|'.join([source, payload['badge'], payload['quand'], payload['userid'], payload['type'], payload['capteur']])
    return hashlib.sha256(canonical.encode('utf-8')).hexdigest(), payload

def ouvrir_etat(path: Path, source: str, endpoint: str) -> sqlite3.Connection:
    path.parent.mkdir(parents=True, exist_ok=True)
    db = sqlite3.connect(path, timeout=30)
    db.execute('PRAGMA journal_mode=WAL')
    db.execute('PRAGMA synchronous=FULL')
    db.execute('CREATE TABLE IF NOT EXISTS meta (cle TEXT PRIMARY KEY, valeur TEXT NOT NULL)')
    db.execute('CREATE TABLE IF NOT EXISTS passages (empreinte TEXT PRIMARY KEY, donnees TEXT NOT NULL, acquitte INTEGER NOT NULL DEFAULT 0, vu_le TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, acquitte_le TEXT)')
    expected = json.dumps([source, endpoint], ensure_ascii=False)
    old = db.execute("SELECT valeur FROM meta WHERE cle='destination'").fetchone()
    if old and old[0] != expected:
        db.close()
        raise ErreurSync('Source ou URL modifiee : utiliser un nouveau fichier SQLite pour la nouvelle destination.')
    db.execute("INSERT OR IGNORE INTO meta VALUES ('destination',?)", (expected,))
    db.commit()
    return db

def lignes_csv(path: Path):
    with path.open(encoding='utf-8-sig', newline='') as handle:
        reader = csv.DictReader(handle)
        if not reader.fieldnames or not set(HEADERS).issubset(reader.fieldnames):
            raise ErreurSync('CSV incomplet : colonnes Req_UserCheck attendues.')
        yield from reader

def lignes_access(path: Path, query: str, driver: str):
    if os.name != 'nt':
        raise ErreurSync('La lecture Access active doit etre effectuee sur le poste Windows de la pointeuse.')
    if not path.is_file():
        raise ErreurSync('Base Access introuvable. Verifier le chemin att2000.mdb.')
    if not re.fullmatch(r'[A-Za-z_][A-Za-z0-9_]{0,63}', query):
        raise ErreurSync('Nom de requete Access invalide.')
    try:
        import pyodbc
    except ImportError:
        raise ErreurSync('Installer pyodbc avec le Python utilise par la tache planifiee.') from None
    if driver not in pyodbc.drivers():
        raise ErreurSync('Pilote Access absent : faire correspondre les architectures de Python et du pilote ODBC.')
    escaped_path = str(path).replace('}', '}}')
    # SQL_ATTR_ACCESS_MODE=SQL_MODE_READ_ONLY ; aucune commande INSERT/UPDATE/DELETE Access.
    connection = pyodbc.connect('DRIVER={'+driver+'};DBQ={'+escaped_path+'};ReadOnly=1;',
                                autocommit=True, readonly=True, timeout=30)
    try:
        cursor = connection.cursor()
        cursor.execute('SELECT [USERID], [Badgenumber], [Name], [Gender], [CHECKTIME], [CHECKTYPE], [SENSORID] FROM ['+query+']')
        columns = [item[0] for item in cursor.description]
        if not set(HEADERS).issubset(columns):
            raise ErreurSync('La requete enregistree ne retourne pas les colonnes attendues.')
        while True:
            rows = cursor.fetchmany(500)
            if not rows:
                break
            for row in rows:
                yield dict(zip(columns, row))
    finally:
        connection.close()

def memoriser(db: sqlite3.Connection, rows, source: str) -> tuple[int,int,int]:
    read = new = invalid = 0
    try:
        for row in rows:
            read += 1
            try:
                key, payload = normaliser(row, source)
            except ErreurSync as exc:
                invalid += 1
                # Pas de nom, badge, horodatage ou jeton dans les journaux.
                logging.error('Ligne numero %d non mise en file : %s', read, exc)
                continue
            before = db.total_changes
            db.execute('INSERT OR IGNORE INTO passages (empreinte, donnees) VALUES (?,?)',
                       (key, json.dumps(payload, ensure_ascii=False, separators=(',',':'))))
            new += db.total_changes-before
            if read % 500 == 0:
                db.commit()
        db.commit()
    except Exception:
        db.rollback()
        raise
    return read, new, invalid

class SansRedirection(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

def envoyer(endpoint: str, token: str, source: str, pending: list, timeout: int) -> set[str]:
    body = json.dumps({'source': source, 'passages': [json.loads(row[1]) for row in pending]},
                      ensure_ascii=False, separators=(',',':')).encode('utf-8')
    if len(body) > 1_000_000:
        raise ErreurSync('Lot trop volumineux ; reduire taille_lot dans la configuration.')
    request = urllib.request.Request(endpoint, body, method='POST',
                  headers={'Authorization':'Bearer '+token, 'Content-Type':'application/json; charset=utf-8', 'Accept':'application/json'})
    # Verification TLS standard active. Interdire les redirections pour proteger le Bearer.
    opener = urllib.request.build_opener(SansRedirection())
    try:
        with opener.open(request, timeout=timeout) as response:
            if response.status != 200:
                raise ErreurSync('API : statut inattendu ; aucun acquittement local.')
            answer = json.loads(response.read(1_000_001))
    except urllib.error.HTTPError as exc:
        ref = ''
        try:
            detail = json.loads(exc.read(4096))
            candidate = str(detail.get('reference',''))
            if re.fullmatch(r'[a-f0-9]{12}', candidate):
                ref = ' Reference serveur : '+candidate
        except (ValueError, AttributeError):
            pass
        raise ErreurSync('API HTTP '+str(exc.code)+'. Aucun passage acquitte.'+ref) from None
    except (urllib.error.URLError, TimeoutError, OSError, ValueError):
        raise ErreurSync('Reseau, TLS ou reponse JSON invalide. Lot conserve pour le prochain passage.') from None
    if not isinstance(answer, dict) or not isinstance(answer.get('acquittes'), list):
        raise ErreurSync('API sans liste acquittes : deployer le controleur V5.7 ; aucun acquittement local.')
    ack = answer['acquittes']
    expected = {row[0] for row in pending}
    if any(not isinstance(key,str) or key not in expected for key in ack):
        raise ErreurSync('Acquittement etranger au lot : aucun acquittement local.')
    accepted = set(ack)
    logging.info('API : %d/%d acquittements ; %d badges non rapproches.', len(accepted),len(pending),len(answer.get('badgesNonRapproches') or {}))
    return accepted

@contextmanager
def verrou(path: Path):
    path.parent.mkdir(parents=True,exist_ok=True)
    handle = path.open('a+b')
    try:
        if handle.seek(0,os.SEEK_END) == 0:
            handle.write(b'0');handle.flush()
        handle.seek(0)
        if os.name == 'nt':
            import msvcrt
            try:
                msvcrt.locking(handle.fileno(),msvcrt.LK_NBLCK,1)
            except OSError:
                raise ErreurSync('Une synchronisation est deja en cours.') from None
        else:
            import fcntl
            try:
                fcntl.flock(handle.fileno(),fcntl.LOCK_EX|fcntl.LOCK_NB)
            except OSError:
                raise ErreurSync('Une synchronisation est deja en cours.') from None
        yield
    finally:
        # La fermeture libere aussi le verrou en cas d'arret brutal du processus.
        handle.close()

def charger(path: Path) -> dict:
    config = json.loads(path.read_text(encoding='utf-8-sig'))
    if not isinstance(config,dict):
        raise ErreurSync('Configuration JSON invalide.')
    url = str(config.get('api_url',''))
    parts = urllib.parse.urlsplit(url)
    if parts.scheme!='https' or not parts.hostname or parts.username or parts.password or parts.query or parts.fragment or parts.path!='/api/presences/zkteco':
        raise ErreurSync('URL HTTPS complete attendue : /api/presences/zkteco, sans identifiants ni parametres.')
    if not re.fullmatch(r'[A-Za-z0-9_-]{1,60}',str(config.get('source',''))):
        raise ErreurSync('Source obligatoire : 1 a 60 caracteres lettres, chiffres, tirets ou soulignes.')
    for key, default in [('etat_sqlite','etat.sqlite'),('fichier_jeton','jeton.dpapi'),('journal','journaux/synchronisation.log')]:
        value = Path(config.get(key,default))
        config[key] = value if value.is_absolute() else path.parent/value
    config['taille_lot'] = max(1,min(1000,int(config.get('taille_lot',300))))
    config['lots_maximum'] = max(1,min(500,int(config.get('lots_maximum',20))))
    config['delai_reseau_secondes'] = max(5,min(120,int(config.get('delai_reseau_secondes',30))))
    return config

def lire_jeton(config: dict) -> str:
    token = os.environ.get('ZKTECO_API_TOKEN','')
    if not token:
        token = dpapi(config['fichier_jeton'].read_bytes(),False).decode('utf-8')
    if len(token)<32 or not token.isascii() or '\n' in token or '\r' in token:
        raise ErreurSync('Jeton API invalide : au moins 32 caracteres ASCII sans retour a la ligne.')
    return token

def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config',type=Path,default=Path(__file__).with_name('config.json'))
    parser.add_argument('--csv',type=Path,help='Import ponctuel du CSV ; ne remplace pas la lecture Access automatique.')
    parser.add_argument('--sans-envoi',action='store_true',help='Lecture et mise en file uniquement, sans aucun appel API.')
    parser.add_argument('--envoyer-seulement',action='store_true',help='Reessayer la file locale sans acceder a Access.')
    parser.add_argument('--rejouer',action='store_true',help='Remettre les passages locaux en attente pour rapprochement apres creation de badges ; dedoublonnage serveur.')
    parser.add_argument('--configurer-jeton',action='store_true',help='Enregistrer le jeton chiffre pour le compte Windows courant.')
    args = parser.parse_args()
    db = None
    try:
        cfg = charger(args.config.resolve())
        if args.configurer_jeton:
            token = getpass.getpass('Jeton API (au moins 32 caracteres) : ')
            if len(token)<32 or not token.isascii() or '\n' in token or '\r' in token:
                raise ErreurSync('Jeton invalide.')
            secret = cfg['fichier_jeton'];secret.parent.mkdir(parents=True,exist_ok=True)
            secret.write_bytes(dpapi(token.encode('utf-8'),True))
            print('Jeton chiffre enregistre pour ce compte Windows.');return 0
        cfg['journal'].parent.mkdir(parents=True,exist_ok=True)
        handler = RotatingFileHandler(cfg['journal'],maxBytes=1_000_000,backupCount=5,encoding='utf-8')
        logging.basicConfig(level=logging.INFO,format='%(asctime)s %(levelname)s %(message)s',handlers=[handler,logging.StreamHandler()])
        invalid = 0
        with verrou(cfg['etat_sqlite'].with_suffix('.lock')):
            db = ouvrir_etat(cfg['etat_sqlite'],cfg['source'],cfg['api_url'])
            if args.rejouer:
                db.execute('UPDATE passages SET acquitte=0, acquitte_le=NULL');db.commit()
            if not args.envoyer_seulement:
                rows = lignes_csv(args.csv) if args.csv else lignes_access(Path(cfg['base_access']),cfg.get('requete_access','Req_UserCheck'),cfg.get('pilote_odbc','Microsoft Access Driver (*.mdb, *.accdb)'))
                try:
                    read,new,invalid = memoriser(db,rows,cfg['source'])
                    logging.info('Lecture : %d lignes, %d nouveaux passages en file, %d lignes invalides.',read,new,invalid)
                except Exception as exc:
                    # Continuer a envoyer les anciennes lignes deja en file si Access est occupe.
                    invalid += 1
                    logging.error('Lecture impossible (%s). File deja enregistree conservee.',type(exc).__name__)
                    if isinstance(exc,ErreurSync):
                        logging.error('%s',exc)
            if not args.sans_envoi and db.execute('SELECT COUNT(*) FROM passages WHERE acquitte=0').fetchone()[0]:
                token = lire_jeton(cfg)
                for _ in range(cfg['lots_maximum']):
                    pending = db.execute('SELECT empreinte,donnees FROM passages WHERE acquitte=0 ORDER BY rowid LIMIT ?', (cfg['taille_lot'],)).fetchall()
                    if not pending:
                        break
                    ack = envoyer(cfg['api_url'],token,cfg['source'],pending,cfg['delai_reseau_secondes'])
                    with db:
                        db.executemany('UPDATE passages SET acquitte=1,acquitte_le=CURRENT_TIMESTAMP WHERE empreinte=?',((key,) for key in ack))
                    if len(ack)!=len(pending):
                        raise ErreurSync('Acquittement partiel : lignes non confirmees conservees pour le prochain essai.')
            remaining = db.execute('SELECT COUNT(*) FROM passages WHERE acquitte=0').fetchone()[0]
            logging.info('Fin : %d passages en attente.',remaining)
        return 2 if invalid else 0
    except (ErreurSync,OSError,ValueError,sqlite3.Error) as exc:
        # Ne jamais exposer une exception reseau contenant un jeton ou une reponse HTML.
        logging.error('%s',exc if isinstance(exc,ErreurSync) else type(exc).__name__)
        return 1
    finally:
        if db is not None:
            db.close()

if __name__=='__main__':
    sys.exit(main())
