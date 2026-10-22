[CmdletBinding()]
param(
    [string]$OutputDirectory = '.backups'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = $PSScriptRoot
$OutputRoot = if ([IO.Path]::IsPathRooted($OutputDirectory)) {
    $OutputDirectory
}
else {
    Join-Path $ProjectRoot $OutputDirectory
}

function Invoke-NativeCommand {
    param(
        [Parameter(Mandatory)][string]$Command,
        [string[]]$ArgumentList = @()
    )

    & $Command @ArgumentList
    if ($LASTEXITCODE -ne 0) {
        throw "Command failed with exit code ${LASTEXITCODE}: $Command $($ArgumentList -join ' ')"
    }
}

function Get-ComposeProjectName {
    $json = (& docker compose config --format json) | Out-String
    if ($LASTEXITCODE -ne 0) {
        throw 'Unable to read Docker Compose configuration.'
    }
    return ($json | ConvertFrom-Json).name
}

function Export-Volume {
    param(
        [Parameter(Mandatory)][string]$Volume,
        [Parameter(Mandatory)][string]$OutputName,
        [Parameter(Mandatory)][string]$StageDirectory
    )

    $mountSource = (Resolve-Path -LiteralPath $StageDirectory).Path
    Invoke-NativeCommand -Command 'docker' -ArgumentList @(
        'run', '--rm',
        '--mount', "type=volume,source=$Volume,target=/source,readonly",
        '--mount', "type=bind,source=$mountSource,target=/backup",
        'alpine:3.20',
        'tar', '-czf', "/backup/$OutputName", '-C', '/source', '.'
    )
}

Push-Location $ProjectRoot
$maintenanceEnabled = $false
$stage = $null
try {
    foreach ($command in @('docker', 'tar')) {
        if (-not (Get-Command $command -ErrorAction SilentlyContinue)) {
            throw "Required command is unavailable: $command"
        }
    }

    Invoke-NativeCommand -Command 'docker' -ArgumentList @('compose', 'up', '-d', 'db', 'wordpress')
    $projectName = Get-ComposeProjectName
    $appVolume = "${projectName}_app_code"
    $uploadsVolume = "${projectName}_local_uploads"

    foreach ($volume in @($appVolume, $uploadsVolume)) {
        & docker volume inspect $volume *> $null
        if ($LASTEXITCODE -ne 0) {
            throw "Docker volume does not exist: $volume"
        }
    }

    New-Item -ItemType Directory -Path $OutputRoot -Force | Out-Null
    $timestamp = (Get-Date).ToUniversalTime().ToString('yyyyMMddTHHmmssZ')
    $stage = Join-Path $OutputRoot ".stage-$timestamp-$([Guid]::NewGuid().ToString('N'))"
    New-Item -ItemType Directory -Path $stage -Force | Out-Null

    & docker compose exec -T wordpress wp maintenance-mode activate --allow-root 2>$null | Out-Null
    $maintenanceEnabled = $LASTEXITCODE -eq 0

    $dbContainer = (& docker compose ps -q db).Trim()
    if ([string]::IsNullOrWhiteSpace($dbContainer)) {
        throw 'Database container is not running.'
    }

    $remoteDump = "/tmp/itsoft-full-$timestamp.sql.gz"
    Invoke-NativeCommand -Command 'docker' -ArgumentList @(
        'compose', 'exec', '-T', 'db', 'sh', '-lc',
        "mariadb-dump --user=root --password=`"`$MARIADB_ROOT_PASSWORD`" --single-transaction --quick --routines --triggers --events --add-drop-table `"`$MARIADB_DATABASE`" | gzip -c > '$remoteDump'"
    )
    Invoke-NativeCommand -Command 'docker' -ArgumentList @('cp', "${dbContainer}:$remoteDump", (Join-Path $stage 'database.sql.gz'))
    Invoke-NativeCommand -Command 'docker' -ArgumentList @('compose', 'exec', '-T', 'db', 'rm', '-f', $remoteDump)

    Export-Volume -Volume $appVolume -OutputName 'app-code.tar.gz' -StageDirectory $stage
    Export-Volume -Volume $uploadsVolume -OutputName 'uploads.tar.gz' -StageDirectory $stage

    $sourceUrl = if ($env:ITSOFT_LOCAL_URL) { $env:ITSOFT_LOCAL_URL } else { 'http://localhost:8080' }
    if (Test-Path -LiteralPath '.env') {
        $urlLine = Get-Content -LiteralPath '.env' | Where-Object { $_ -match '^ITSOFT_LOCAL_URL=' } | Select-Object -Last 1
        if ($urlLine) {
            $sourceUrl = $urlLine.Substring('ITSOFT_LOCAL_URL='.Length).Trim()
        }
    }
    Set-Content -LiteralPath (Join-Path $stage 'source-url.txt') -Value $sourceUrl -Encoding ASCII

    $gitCommit = (& git -c "safe.directory=$ProjectRoot" -C $ProjectRoot rev-parse HEAD 2>$null)
    if ($LASTEXITCODE -ne 0) {
        $gitCommit = 'unknown'
    }

    $payloadFiles = @('database.sql.gz', 'app-code.tar.gz', 'uploads.tar.gz', 'source-url.txt')
    $checksums = foreach ($name in $payloadFiles) {
        $hash = (Get-FileHash -LiteralPath (Join-Path $stage $name) -Algorithm SHA256).Hash.ToLowerInvariant()
        "$hash  $name"
    }
    Set-Content -LiteralPath (Join-Path $stage 'SHA256SUMS') -Value $checksums -Encoding ASCII

    $manifest = [ordered]@{
        format = 'itsoft-full-backup-v1'
        createdAtUtc = (Get-Date).ToUniversalTime().ToString('o')
        gitCommit = $gitCommit
        composeProject = $projectName
        sourceUrl = $sourceUrl
        contains = @('database', 'wordpress-app-code', 'uploads')
        warning = 'Contains private store data. Keep encrypted in transit and restrict access.'
    }
    $manifest | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath (Join-Path $stage 'manifest.json') -Encoding UTF8

    $archive = Join-Path $OutputRoot "itsoft-full-$timestamp.tar.gz"
    Invoke-NativeCommand -Command 'tar' -ArgumentList @('-czf', $archive, '-C', $stage, '.')

    $archiveHash = (Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLowerInvariant()
    Set-Content -LiteralPath "$archive.sha256" -Value "$archiveHash  $([IO.Path]::GetFileName($archive))" -Encoding ASCII

    Write-Host "FULL_BACKUP=$archive" -ForegroundColor Green
    Write-Host "SHA256=$archiveHash" -ForegroundColor Green
    Write-Warning 'The archive contains database records, users, orders, license inventory and uploads. Do not commit it to Git.'
}
finally {
    if ($maintenanceEnabled) {
        & docker compose exec -T wordpress wp maintenance-mode deactivate --allow-root 2>$null | Out-Null
    }
    if ($stage -and (Test-Path -LiteralPath $stage)) {
        $resolvedOutput = [IO.Path]::GetFullPath($OutputRoot).TrimEnd([IO.Path]::DirectorySeparatorChar)
        $resolvedStage = [IO.Path]::GetFullPath($stage)
        if ($resolvedStage.StartsWith($resolvedOutput + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
            Remove-Item -LiteralPath $resolvedStage -Recurse -Force
        }
    }
    Pop-Location
}
