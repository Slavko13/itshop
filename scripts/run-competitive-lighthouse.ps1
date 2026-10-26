param(
    [string]$OutputDirectory = "lighthouse-comparison/raw",
    [int]$Runs = 2,
    [int]$DelaySeconds = 12,
    [string[]]$Sites = @("itsoft", "keysoft", "rusoft")
)

$ErrorActionPreference = "Stop"

$pages = @(
    @{ Site = "itsoft";  Page = "home";     Url = "https://test.itsoft.store/" },
    @{ Site = "itsoft";  Page = "category"; Url = "https://test.itsoft.store/product-category/microsoft-office/" },
    @{ Site = "itsoft";  Page = "product";  Url = "https://test.itsoft.store/product/microsoft-office-2021-professional-plus/" },
    @{ Site = "keysoft"; Page = "home";     Url = "https://keysoft.store/" },
    @{ Site = "keysoft"; Page = "category"; Url = "https://keysoft.store/microsoft-office" },
    @{ Site = "keysoft"; Page = "product";  Url = "https://keysoft.store/microsoft-office/buy-office-2021-professional-Plus-key/3226383" },
    @{ Site = "rusoft";  Page = "home";     Url = "https://rusoft.shop/" },
    @{ Site = "rusoft";  Page = "category"; Url = "https://rusoft.shop/product-category/catalog/microsoft-office/" },
    @{ Site = "rusoft";  Page = "product";  Url = "https://rusoft.shop/product/microsoft-office-2021-pro/" }
)

$pages = $pages | Where-Object { $Sites -contains $_.Site }

$profiles = @("mobile", "desktop")
$chrome = "C:\Program Files\Google\Chrome\Application\chrome.exe"
$absoluteOutput = [System.IO.Path]::GetFullPath((Join-Path $PWD $OutputDirectory))
New-Item -ItemType Directory -Force -Path $absoluteOutput | Out-Null

$total = $pages.Count * $profiles.Count * $Runs
$current = 0

foreach ($item in $pages) {
    foreach ($profile in $profiles) {
        for ($run = 1; $run -le $Runs; $run++) {
            $current++
            $name = "$($item.Site)-$($item.Page)-$profile-run$run.json"
            $outputPath = Join-Path $absoluteOutput $name

            if (Test-Path -LiteralPath $outputPath) {
                try {
                    $existing = Get-Content -LiteralPath $outputPath -Raw | ConvertFrom-Json
                    $validScores = $null -ne $existing.categories.performance.score -and
                        $null -ne $existing.categories.accessibility.score -and
                        $null -ne $existing.categories.'best-practices'.score -and
                        $null -ne $existing.categories.seo.score
                    if ($validScores -and -not $existing.runtimeError) {
                        Write-Host "[$current/$total] skip valid $name" -ForegroundColor DarkGray
                        continue
                    }
                }
                catch {
                    # A partial or malformed report is replaced below.
                }
                Remove-Item -LiteralPath $outputPath -Force
            }

            Write-Host "[$current/$total] $($item.Site) / $($item.Page) / $profile / run $run" -ForegroundColor Cyan

            $arguments = @(
                "--no-install", "lighthouse", $item.Url,
                "--quiet",
                "--chrome-path=$chrome",
                "--chrome-flags=--headless=new --no-sandbox --disable-dev-shm-usage",
                "--output=json",
                "--output-path=$outputPath",
                "--only-categories=performance,accessibility,best-practices,seo",
                "--max-wait-for-load=60000",
                "--throttling-method=simulate"
            )

            if ($profile -eq "desktop") {
                $arguments += "--preset=desktop"
            }

            & npx.cmd @arguments
            if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $outputPath)) {
                throw "Lighthouse failed for $($item.Url) ($profile, run $run)"
            }

            if ($DelaySeconds -gt 0) {
                Start-Sleep -Seconds $DelaySeconds
            }
        }
    }
}

Write-Host "Lighthouse audit complete: $absoluteOutput" -ForegroundColor Green
