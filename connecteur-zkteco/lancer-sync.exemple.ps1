# Poste Windows : le Planificateur de tâches peut appeler ce fichier toutes les 5 minutes.
# Le jeton est défini comme variable d’environnement du compte de tâche, pas dans ce script.
$ErrorActionPreference = 'Stop'
& 'C:\Python312\python.exe' 'C:\CitracZkteco\zkteco_sync.py' --config 'C:\CitracZkteco\config.json'
exit $LASTEXITCODE
