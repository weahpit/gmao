#!/usr/bin/env python3
"""Connecteur Access -> HTTPS. Lecture seule Access ; pyodbc + bibliothèque Python standard.
À exécuter sur le poste local ayant accès au fichier .mdb/.accdb.
Aucun service web / aucun port entrant n’est requis.
"""
import argparse, datetime as dt, json, logging, os, pathlib, re, sys, time
import urllib.request, urllib.error, urllib.parse


def ident(value):
    if not re.fullmatch(r'[A-Za-z_][A-Za-z0-9_ ]{0,63}', value):
        raise ValueError('Nom de table/colonne invalide dans la configuration')
    return '[' + value + ']'


def send(config, batch):
    url = config['url']
    parsed = urllib.parse.urlsplit(url)
    if parsed.scheme != 'https' or parsed.username or parsed.password or parsed.fragment:
        raise ValueError('URL HTTPS sans identifiants obligatoire')
    token = os.environ.get(config.get('token_env', 'ZKTECO_API_TOKEN'), '')
    if len(token) < 32:
        raise ValueError('Le jeton API doit être dans la variable d’environnement (32 caractères minimum)')
    body = json.dumps({'source': config['source'], 'passages': batch}, ensure_ascii=False).encode()
    req = urllib.request.Request(url, data=body, method='POST', headers={
        'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json', 'Accept': 'application/json'})
    # Interdit les redirections pour ne jamais transmettre le Bearer à un autre site.
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, req, fp, code, msg, headers, newurl):
            return None
    opener = urllib.request.build_opener(NoRedirect())
    for attempt in range(4):
        try:
            with opener.open(req, timeout=60) as response:
                result = json.load(response)
                if result.get('recus') != len(batch):
                    raise ValueError('Accusé de réception incohérent')
                return result
        except urllib.error.HTTPError as error:
            if error.code not in (429, 500, 502, 503, 504) or attempt == 3:
                raise RuntimeError('API HTTP ' + str(error.code) + ' — ' + error.read(1000).decode(errors='replace')) from error
        except (urllib.error.URLError, TimeoutError):
            if attempt == 3:
                raise
        time.sleep(2 ** attempt)


def acquire_lock(path):
    path.parent.mkdir(parents=True, exist_ok=True)
    handle = open(path, 'a+b')
    if os.name == 'nt':
        import msvcrt
        handle.seek(0)
        if handle.read(1) == b'':
            handle.write(b'0'); handle.flush()
        handle.seek(0)
        try: msvcrt.locking(handle.fileno(), msvcrt.LK_NBLCK, 1)
        except OSError: handle.close(); return None
    else:
        import fcntl
        try: fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError: handle.close(); return None
    return handle


def sync(config, full_from=None, simulation=False):
    import pyodbc
    root = pathlib.Path(config.get('state_dir', './etat-zkteco')).resolve()
    lock = acquire_lock(root/'sync.lock')
    if lock is None:
        logging.warning('Synchronisation déjà en cours'); return
    try:
        state_file = root/'etat.json'
        state = json.loads(state_file.read_text()) if state_file.exists() else {}
        now = dt.datetime.now().replace(microsecond=0)
        # Reprise glissante de plusieurs jours pour accepter les passages tardifs / doublons.
        since = dt.datetime.fromisoformat(full_from or state.get('dernier_succes', config['premiere_date']))
        if not full_from: since -= dt.timedelta(days=max(1, int(config.get('jours_reprise', 3))))
        tables = config['access']
        conn_str = tables.get('connexion') or ('DRIVER={' + tables.get('driver', 'Microsoft Access Driver (*.mdb, *.accdb)') + '};DBQ=' + tables['fichier'] + ';READONLY=1;')
        conn = pyodbc.connect(conn_str, autocommit=True, readonly=True)
        try:
            p, u = ident(tables.get('table_pointages', 'CHECKINOUT')), ident(tables.get('table_users', 'USERINFO'))
            pid, uid = ident(tables.get('pointage_user_id', 'USERID')), ident(tables.get('user_id', 'USERID'))
            badge, stamp = ident(tables.get('badge', 'Badgenumber')), ident(tables.get('date', 'CHECKTIME'))
            sql = f'SELECT u.{badge}, p.{stamp} FROM {p} AS p INNER JOIN {u} AS u ON p.{pid}=u.{uid} WHERE p.{stamp} >= ? AND p.{stamp} <= ? ORDER BY p.{stamp}, u.{badge}'
            cursor = conn.cursor().execute(sql, since, now)
            total = 0; unknown = set(); size = min(1000, max(1, int(config.get('taille_lot', 500))))
            while True:
                rows = cursor.fetchmany(size)
                if not rows: break
                batch = []
                for b, stamp in rows:
                    if not b or not isinstance(stamp, dt.datetime):
                        raise ValueError('Badge/horodatage Access absent : arrêt pour ne pas ignorer les données')
                    batch.append({'badge': str(b).strip(), 'quand': stamp.replace(microsecond=0).isoformat()})
                total += len(batch)
                if simulation:
                    logging.info('Simulation : %d passages prêts à envoyer', len(batch))
                else:
                    result = send(config, batch)
                    unknown.update(result.get('badgesNonRapproches', {}).keys())
                    logging.info('Lot %d : %d nouveaux, %d doublons', len(batch), result.get('nouveauxPassages', 0), result.get('doublons', 0))
            if not simulation:
                # Marqueur avancé seulement quand TOUS les lots ont été confirmés.
                temporary = state_file.with_suffix('.tmp')
                temporary.write_text(json.dumps({'dernier_succes': now.isoformat(), 'passages': total}, indent=2))
                os.replace(temporary, state_file)
            if unknown:
                logging.warning('Badges non rapprochés (%d) : %s. Renseigner les codes RH, puis rejouer --depuis.', len(unknown), ', '.join(sorted(unknown)))
            logging.info('Terminé : %d passages, période %s -> %s', total, since, now)
        finally:
            conn.close()
    finally:
        lock.close()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--config', required=True)
    parser.add_argument('--depuis', help='Reprise explicite YYYY-MM-DD (ne soustrait pas jours_reprise)')
    parser.add_argument('--simulation', action='store_true')
    args = parser.parse_args()
    logging.basicConfig(level=logging.INFO, format='%(asctime)s %(levelname)s %(message)s')
    try:
        config = json.loads(pathlib.Path(args.config).read_text(encoding='utf-8'))
        if not re.fullmatch(r'[A-Za-z0-9_-]{1,60}', config['source']): raise ValueError('Source invalide')
        sync(config, args.depuis, args.simulation)
        return 0
    except Exception as error:
        logging.error('%s', error); return 1


if __name__ == '__main__':
    sys.exit(main())
