param([string]$PostgresBin = 'C:\Program Files\PostgreSQL\17\bin')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$clusterPath = Join-Path $projectRoot 'storage\postgres'
$credentialPath = Join-Path $projectRoot 'storage\postgres-password.txt'
$logPath = Join-Path $projectRoot 'storage\postgres.log'
if (Test-Path -LiteralPath $clusterPath) { throw 'Project database cluster already exists. Use pg_ctl to start it; do not reinitialize.' }
$passwordBytes = New-Object byte[] 32
$randomSource = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$randomSource.GetBytes($passwordBytes)
$password = [Convert]::ToBase64String($passwordBytes)
[IO.File]::WriteAllText($credentialPath,$password)
try {
    & (Join-Path $PostgresBin 'initdb.exe') -D $clusterPath -U ledger -A scram-sha-256 --pwfile=$credentialPath --encoding=UTF8 --locale=C
    if ($LASTEXITCODE -ne 0) { throw 'initdb failed' }
} finally { Remove-Item -LiteralPath $credentialPath -ErrorAction SilentlyContinue }
Add-Content -LiteralPath (Join-Path $clusterPath 'postgresql.conf') -Value "`nport = 55432`nlisten_addresses = '127.0.0.1'"
$startArguments = @('-D', ('"' + $clusterPath + '"'), '-l', ('"' + $logPath + '"'), '-w', 'start')
$startProcess = Start-Process -FilePath (Join-Path $PostgresBin 'pg_ctl.exe') -ArgumentList $startArguments -WindowStyle Hidden -PassThru
$startProcess.WaitForExit()
if ($startProcess.ExitCode -ne 0) { throw 'PostgreSQL startup failed; inspect storage/postgres.log' }
$env:PGPASSWORD = $password
try {
    & (Join-Path $PostgresBin 'createdb.exe') -h 127.0.0.1 -p 55432 -U ledger ledger
    if ($LASTEXITCODE -ne 0) { throw 'Creating application database failed' }
    & (Join-Path $PostgresBin 'createdb.exe') -h 127.0.0.1 -p 55432 -U ledger ledger_test
    if ($LASTEXITCODE -ne 0) { throw 'Creating test database failed' }
} finally { Remove-Item Env:PGPASSWORD }
$environmentPath = Join-Path $projectRoot '.env'
$configuration = [IO.File]::ReadAllText($environmentPath)
$settings = @{DB_CONNECTION='pgsql';DB_HOST='127.0.0.1';DB_PORT='55432';DB_DATABASE='ledger';DB_USERNAME='ledger';DB_PASSWORD=$password;APP_NAME='Ledger'}
foreach ($entry in $settings.GetEnumerator()) {
    $line = $entry.Key + '=' + $entry.Value
    if ($configuration -match ('(?m)^#?\s*' + $entry.Key + '=')) { $configuration = [regex]::Replace($configuration,('(?m)^#?\s*' + $entry.Key + '=.*$'),$line) }
    else { $configuration += "`n" + $line }
}
[IO.File]::WriteAllText($environmentPath,$configuration)
Write-Output 'Project PostgreSQL is running on 127.0.0.1:55432. Credentials were written privately to .env.'
