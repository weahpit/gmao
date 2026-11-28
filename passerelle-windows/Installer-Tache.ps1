<# A executer sous le MEME compte Windows que --configurer-jeton.
Ne pas lancer ce script sur le serveur web ; uniquement sur le poste qui voit att2000.mdb.
Le compte doit avoir le droit 'Ouvrir une session en tant que tache'.
#>
param(
    [Parameter(Mandatory=$true)][string]$PythonExe,
    [string]$Dossier = $PSScriptRoot,
    [string]$NomTache = 'CITRAC - Pointages ZKTeco',
    [ValidateRange(1,60)][int]$Minutes = 5
)
$ErrorActionPreference = 'Stop'
$PythonExe = (Resolve-Path -LiteralPath $PythonExe).Path
$Dossier = (Resolve-Path -LiteralPath $Dossier).Path
$Script = Join-Path $Dossier 'synchroniser.py'
$Config = Join-Path $Dossier 'config.json'
if (!(Test-Path -LiteralPath $Script) -or !(Test-Path -LiteralPath $Config)) { throw 'synchroniser.py et config.json sont requis.' }
$Compte = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
$Identifiants = Get-Credential -UserName $Compte -Message 'Mot de passe du compte Windows courant pour la tache planifiee (pas le jeton API)'
if ($null -eq $Identifiants -or $Identifiants.UserName -ne $Compte) { throw 'Utiliser exactement le compte Windows courant ; le jeton DPAPI lui est lie.' }
$Action = New-ScheduledTaskAction -Execute $PythonExe -Argument ('"{0}" --config "{1}"' -f $Script, $Config) -WorkingDirectory $Dossier
# Repetition pendant dix ans ; le poste doit etre allume et le compte toujours valide.
$Declencheur = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes $Minutes) -RepetitionDuration (New-TimeSpan -Days 3650)
$Reglages = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 20) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$Mdp = $Identifiants.GetNetworkCredential().Password
try {
    Register-ScheduledTask -TaskName $NomTache -Action $Action -Trigger $Declencheur -Settings $Reglages -User $Compte -Password $Mdp -RunLevel Limited -Description 'Access Req_UserCheck en lecture seule vers API CITRAC HTTPS ; file locale et acquittements.' -Force | Out-Null
} finally { $Mdp = $null; $Identifiants = $null }
Write-Host 'Tache installee. Tester avec Start-ScheduledTask puis consulter les journaux et les presences RH.'
