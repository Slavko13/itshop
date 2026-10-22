[CmdletBinding()]
param(
    [ValidatePattern('^[A-Za-z0-9_-]+$')]
    [string]$Target = 'production',

    [switch]$BuildOnly,

    [switch]$SkipChecks,

    [switch]$AllowDirty,

    [ValidatePattern('^[A-Za-z0-9._-]+$')]
    [string]$Rollback
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = $PSScriptRoot
$DeployRoot = Join-Path $ProjectRoot 'deploy'
$BuildRoot = Join-Path $ProjectRoot '.build'
$ConfigPath = Join-Path $DeployRoot "$Target.local.json"
$RemoteScript = Join-Path $DeployRoot 'remote-deploy.sh'

function Invoke-NativeCommand {
    param(
        [Parameter(Mandatory)]
        [string]$Command,

        [string[]]$ArgumentList = @()
    )

    & $Command @ArgumentList
    if ($LASTEXITCODE -ne 0) {
        throw "Command failed with exit code ${LASTEXITCODE}: $Command $($ArgumentList -join ' ')"
    }
}

function Assert-Command {
    param([Parameter(Mandatory)][string]$Name)

    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Required command is unavailable: $Name"
    }
}

function Read-DeployConfig {
    if (-not (Test-Path -LiteralPath $ConfigPath -PathType Leaf)) {
        throw "Deployment config is missing: $ConfigPath. Copy deploy/$Target.example.json to deploy/$Target.local.json and fill in the server values."
    }

    $config = Get-Content -LiteralPath $ConfigPath -Raw | ConvertFrom-Json
    foreach ($property in @('host', 'user', 'port', 'wordpressPath', 'url')) {
        if (-not ($config.PSObject.Properties.Name -contains $property) -or [string]::IsNullOrWhiteSpace([string]$config.$property)) {
            throw "Missing required deployment setting: $property"
        }
    }

    if ([string]$config.host -notmatch '^[A-Za-z0-9.-]+$') {
        throw 'Deployment host contains unsupported characters.'
    }
    if ([string]$config.user -notmatch '^[A-Za-z0-9._-]+$') {
        throw 'Deployment user contains unsupported characters.'
    }
    if ([string]$config.wordpressPath -notmatch '^/[A-Za-z0-9._/-]+$' -or [string]$config.wordpressPath -eq '/') {
        throw 'wordpressPath must be a safe absolute Unix path and cannot be /.'
    }
    if ([int]$config.port -lt 1 -or [int]$config.port -gt 65535) {
        throw 'Deployment port must be between 1 and 65535.'
    }

    return $config
}

function Get-SshArguments {
    param(
        [Parameter(Mandatory)]$Config,
        [switch]$ForScp
    )

    $arguments = @()
    if ($ForScp) {
        $arguments += @('-P', [string]$Config.port)
    }
    else {
        $arguments += @('-p', [string]$Config.port)
    }

    if (($Config.PSObject.Properties.Name -contains 'identityFile') -and -not [string]::IsNullOrWhiteSpace([string]$Config.identityFile)) {
        $identityFile = [Environment]::ExpandEnvironmentVariables([string]$Config.identityFile)
        if (-not (Test-Path -LiteralPath $identityFile -PathType Leaf)) {
            throw "SSH identity file not found: $identityFile"
        }
        $arguments += @('-i', $identityFile)
    }

    $arguments += @('-o', 'BatchMode=yes', '-o', 'ConnectTimeout=15')
    return $arguments
}

function Invoke-LocalChecks {
    Write-Host 'Running local deployment checks...' -ForegroundColor Cyan
    Assert-Command 'docker'
    Invoke-NativeCommand -Command 'docker' -ArgumentList @('compose', 'config', '--quiet')
    Invoke-NativeCommand -Command 'docker' -ArgumentList @('compose', 'exec', '-T', 'wordpress', 'sh', '-lc', 'find /var/www/html/wp-content/themes/itsoft /var/www/html/wp-content/plugins/itsoft-license-fulfillment -type f -name "*.php" -print0 | xargs -0 -n1 php -l >/tmp/itsoft-deploy-lint.log && echo "PHP lint: OK"')

    foreach ($path in @('/', '/shop/', '/cart/', '/my-account/', '/download/')) {
        $response = Invoke-WebRequest -Uri "http://localhost:8080$path" -UseBasicParsing -TimeoutSec 20
        if ($response.StatusCode -ne 200) {
            throw "Local smoke test failed for $path with HTTP $($response.StatusCode)."
        }
    }
    Write-Host 'Local checks passed.' -ForegroundColor Green
}

function Get-GitIdentity {
    $commit = 'no-git'
    $status = ''
    if (Get-Command git -ErrorAction SilentlyContinue) {
        $inside = (& git -c "safe.directory=$ProjectRoot" -C $ProjectRoot rev-parse --is-inside-work-tree 2>$null)
        if ($LASTEXITCODE -eq 0 -and $inside -eq 'true') {
            $commit = (& git -c "safe.directory=$ProjectRoot" -C $ProjectRoot rev-parse --short=12 HEAD 2>$null)
            if ($LASTEXITCODE -ne 0) {
                $commit = 'uncommitted'
            }
            $status = (& git -c "safe.directory=$ProjectRoot" -C $ProjectRoot status --porcelain)
            if (-not $AllowDirty -and $status) {
                throw 'The Git working tree is not clean. Commit the release first or explicitly use -AllowDirty.'
            }
        }
    }

    return [PSCustomObject]@{
        Commit = $commit
        Dirty = [bool]$status
    }
}

function New-ReleaseArchive {
    Assert-Command 'tar'
    $gitIdentity = Get-GitIdentity
    $timestamp = (Get-Date).ToUniversalTime().ToString('yyyyMMddTHHmmssZ')
    $releaseId = "${timestamp}-$($gitIdentity.Commit)"
    if ($gitIdentity.Dirty) {
        $releaseId += '-dirty'
    }

    New-Item -ItemType Directory -Path $BuildRoot -Force | Out-Null
    $stage = Join-Path $BuildRoot "stage-$releaseId"
    $artifact = Join-Path $BuildRoot "itsoft-$releaseId.tar.gz"
    New-Item -ItemType Directory -Path (Join-Path $stage 'wp-content/themes') -Force | Out-Null
    New-Item -ItemType Directory -Path (Join-Path $stage 'wp-content/plugins') -Force | Out-Null

    try {
        Copy-Item -LiteralPath (Join-Path $ProjectRoot 'www/itsoft.store/wp-content/themes/itsoft') -Destination (Join-Path $stage 'wp-content/themes/itsoft') -Recurse
        Copy-Item -LiteralPath (Join-Path $ProjectRoot 'www/itsoft.store/wp-content/plugins/itsoft-license-fulfillment') -Destination (Join-Path $stage 'wp-content/plugins/itsoft-license-fulfillment') -Recurse

        $manifest = [ordered]@{
            releaseId = $releaseId
            commit = $gitIdentity.Commit
            dirty = $gitIdentity.Dirty
            builtAtUtc = (Get-Date).ToUniversalTime().ToString('o')
            contents = @(
                'wp-content/themes/itsoft',
                'wp-content/plugins/itsoft-license-fulfillment'
            )
        }
        $manifest | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath (Join-Path $stage 'deploy-manifest.json') -Encoding UTF8

        Invoke-NativeCommand -Command 'tar' -ArgumentList @('-czf', $artifact, '-C', $stage, '.')
    }
    finally {
        if (Test-Path -LiteralPath $stage) {
            Remove-Item -LiteralPath $stage -Recurse -Force
        }
    }

    if (-not (Test-Path -LiteralPath $artifact -PathType Leaf)) {
        throw 'Release archive was not created.'
    }

    return [PSCustomObject]@{
        ReleaseId = $releaseId
        Artifact = $artifact
    }
}

function Send-RemoteScript {
    param(
        [Parameter(Mandatory)]$Config,
        [Parameter(Mandatory)][string[]]$ScpArguments,
        [Parameter(Mandatory)][string[]]$SshArguments
    )

    $remote = "$($Config.user)@$($Config.host)"
    Invoke-NativeCommand -Command 'ssh' -ArgumentList ($SshArguments + @($remote, 'mkdir -p ~/.itsoft-deploy-upload && chmod 700 ~/.itsoft-deploy-upload'))
    Invoke-NativeCommand -Command 'scp' -ArgumentList ($ScpArguments + @($RemoteScript, "${remote}:.itsoft-deploy-upload/remote-deploy.sh"))
    Invoke-NativeCommand -Command 'ssh' -ArgumentList ($SshArguments + @($remote, 'chmod 700 ~/.itsoft-deploy-upload/remote-deploy.sh'))
}

Push-Location $ProjectRoot
try {
    if ($Rollback) {
        Assert-Command 'ssh'
        Assert-Command 'scp'
        $config = Read-DeployConfig
        $sshArguments = Get-SshArguments -Config $config
        $scpArguments = Get-SshArguments -Config $config -ForScp
        Send-RemoteScript -Config $config -ScpArguments $scpArguments -SshArguments $sshArguments
        $remote = "$($config.user)@$($config.host)"
        Invoke-NativeCommand -Command 'ssh' -ArgumentList ($sshArguments + @($remote, "bash ~/.itsoft-deploy-upload/remote-deploy.sh rollback '$($config.wordpressPath)' '$Rollback'"))
        Write-Host "Rollback completed: $Rollback" -ForegroundColor Green
        exit 0
    }

    if (-not $SkipChecks) {
        Invoke-LocalChecks
    }

    $release = New-ReleaseArchive
    Write-Host "Release built: $($release.Artifact)" -ForegroundColor Green
    Write-Host "Release id: $($release.ReleaseId)" -ForegroundColor Green

    if ($BuildOnly) {
        exit 0
    }

    Assert-Command 'ssh'
    Assert-Command 'scp'
    $config = Read-DeployConfig
    $sshArguments = Get-SshArguments -Config $config
    $scpArguments = Get-SshArguments -Config $config -ForScp
    Send-RemoteScript -Config $config -ScpArguments $scpArguments -SshArguments $sshArguments

    $remote = "$($config.user)@$($config.host)"
    $remoteArchive = ".itsoft-deploy-upload/$([IO.Path]::GetFileName($release.Artifact))"
    Invoke-NativeCommand -Command 'scp' -ArgumentList ($scpArguments + @($release.Artifact, "${remote}:$remoteArchive"))
    Invoke-NativeCommand -Command 'ssh' -ArgumentList ($sshArguments + @($remote, "bash ~/.itsoft-deploy-upload/remote-deploy.sh deploy '$remoteArchive' '$($config.wordpressPath)' '$($release.ReleaseId)'"))

    foreach ($suffix in @('/', '/shop/')) {
        $uri = ([string]$config.url).TrimEnd('/') + $suffix
        $response = Invoke-WebRequest -Uri $uri -UseBasicParsing -TimeoutSec 30
        if ($response.StatusCode -lt 200 -or $response.StatusCode -ge 400) {
            throw "Production smoke test failed for $uri with HTTP $($response.StatusCode)."
        }
    }

    Write-Host "Deployment completed: $($release.ReleaseId)" -ForegroundColor Green
    Write-Host "Site: $($config.url)" -ForegroundColor Green
    Write-Host "Rollback: .\deploy.ps1 -Target $Target -Rollback $($release.ReleaseId)" -ForegroundColor Yellow
}
finally {
    Pop-Location
}
