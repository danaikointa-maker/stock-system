[CmdletBinding()]
param(
    [string] $ReleaseRoot = '',
    [string] $Version = (Get-Date -Format 'yyyyMMdd-HHmmss'),
    [switch] $SkipTests,
    [switch] $KeepStage
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = (Resolve-Path (Split-Path -Parent $MyInvocation.MyCommand.Path)).Path
if ([string]::IsNullOrWhiteSpace($ReleaseRoot)) {
    $ReleaseRoot = Join-Path (Split-Path -Parent $ProjectRoot) 'stock-system-releases'
}
$ReleaseRoot = [IO.Path]::GetFullPath($ReleaseRoot)
$PackageName = "stock-system-$Version"
$StageRoot = Join-Path $ReleaseRoot $PackageName
$ZipPath = Join-Path $ReleaseRoot "$PackageName.zip"

function Invoke-Checked {
    param(
        [string] $FilePath,
        [string[]] $Arguments,
        [string] $WorkingDirectory
    )

    & $FilePath @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Command failed ($LASTEXITCODE): $FilePath $($Arguments -join ' ')"
    }
}

function Is-Excluded {
    param([string] $RelativePath)

    $path = $RelativePath.Replace('\', '/').TrimStart('/')
    $segments = $path.Split('/')

    if ($segments -contains '.git' -or
        $segments -contains 'node_modules' -or
        $segments -contains 'tests' -or
        $segments -contains '.agents' -or
        $segments -contains '.claude' -or
        $segments -contains '.codex' -or
        $segments -contains '.cursor' -or
        $path.StartsWith('storage/logs/') -or
        $path.StartsWith('storage/framework/cache/') -or
        $path.StartsWith('storage/framework/sessions/') -or
        $path.StartsWith('storage/framework/testing/') -or
        $path.StartsWith('storage/framework/views/')) {
        return $true
    }

    if ($path -eq '.env' -or
        ($path.StartsWith('.env.') -and $path -ne '.env.example') -or
        $path -eq '.1env' -or
        $path -eq '.phpunit.result.cache' -or
        $path -eq 'database/database.sqlite' -or
        $path -eq 'build-webhosting-release.ps1' -or
        $path -like 'bootstrap/cache/*.php' -or
        $path -like 'storage/app/qr_print_*.csv' -or
        $path -like '*.log') {
        return $true
    }

    return $false
}

function Copy-ProjectFiles {
    param([string] $SourceRoot, [string] $DestinationRoot)

    $sourcePrefix = $SourceRoot.TrimEnd('\') + '\'
    Get-ChildItem -LiteralPath $SourceRoot -Recurse -File -Force | ForEach-Object {
        $relative = $_.FullName.Substring($sourcePrefix.Length)
        if (-not (Is-Excluded $relative)) {
            $destination = Join-Path $DestinationRoot $relative
            $parent = Split-Path -Parent $destination
            New-Item -ItemType Directory -Path $parent -Force | Out-Null
            Copy-Item -LiteralPath $_.FullName -Destination $destination -Force
        }
    }
}

Write-Host "Building $PackageName from $ProjectRoot"

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    throw 'PHP was not found in PATH.'
}
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    throw 'Composer was not found in PATH.'
}
if (-not (Get-Command npm -ErrorAction SilentlyContinue)) {
    throw 'npm was not found in PATH.'
}

Write-Host '[1/7] Installing development Composer dependencies...'
Invoke-Checked 'composer' @('install', '--prefer-dist', '--no-interaction') $ProjectRoot

if (-not $SkipTests) {
    Write-Host '[2/7] Running application tests...'
    Invoke-Checked 'php' @('artisan', 'test') $ProjectRoot
} else {
    Write-Host '[2/7] Tests skipped by request.'
}

Write-Host '[3/7] Installing locked frontend dependencies...'
Invoke-Checked 'npm' @('ci', '--ignore-scripts') $ProjectRoot

Write-Host '[4/7] Building frontend assets...'
Invoke-Checked 'npm' @('run', 'build') $ProjectRoot

if (Test-Path -LiteralPath $StageRoot) {
    throw "Release directory already exists: $StageRoot"
}
New-Item -ItemType Directory -Path $ReleaseRoot -Force | Out-Null
New-Item -ItemType Directory -Path $StageRoot -Force | Out-Null

Write-Host '[5/7] Copying runtime files and including newly-created files automatically...'
Copy-ProjectFiles $ProjectRoot $StageRoot

Write-Host '[6/7] Installing production Composer dependencies into the release...'
Invoke-Checked 'composer' @('install', '--no-dev', '--prefer-dist', '--optimize-autoloader', '--no-interaction') $StageRoot

foreach ($directory in @(
    'storage/app/private',
    'storage/app/public',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/testing',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache'
)) {
    New-Item -ItemType Directory -Path (Join-Path $StageRoot $directory) -Force | Out-Null
}

    Get-ChildItem -LiteralPath (Join-Path $StageRoot 'bootstrap/cache') -Filter '*.php' -File -Force -ErrorAction SilentlyContinue |
        Remove-Item -Force -ErrorAction SilentlyContinue

$files = Get-ChildItem -LiteralPath $StageRoot -Recurse -File -Force | ForEach-Object {
    $relative = $_.FullName.Substring($StageRoot.TrimEnd('\').Length + 1).Replace('\', '/')
    [ordered]@{
        path = $relative
        bytes = $_.Length
        sha256 = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
    }
}

$manifest = [ordered]@{
    package = $PackageName
    created_at = (Get-Date).ToUniversalTime().ToString('o')
    source = $ProjectRoot
    files = @($files)
}
$manifest | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $StageRoot 'release-manifest.json') -Encoding UTF8

Write-Host '[7/7] Creating release ZIP...'
if (Test-Path -LiteralPath $ZipPath) {
    throw "Release ZIP already exists: $ZipPath"
}
Compress-Archive -Path (Join-Path $StageRoot '*') -DestinationPath $ZipPath -CompressionLevel Optimal

$fileCount = (Get-ChildItem -LiteralPath $StageRoot -Recurse -File -Force).Count
$zipSizeMb = [math]::Round((Get-Item -LiteralPath $ZipPath).Length / 1MB, 2)

Write-Host ''
Write-Host 'RELEASE READY'
Write-Host "Stage   : $StageRoot"
Write-Host "ZIP     : $ZipPath"
Write-Host "Files   : $fileCount"
Write-Host "ZIP MB  : $zipSizeMb"
Write-Host 'Excluded: .env, node_modules, tests, .git, development tool folders, logs, caches, SQLite, and generated QR CSV files.'

if (-not $KeepStage) {
    Remove-Item -LiteralPath $StageRoot -Recurse -Force
    Write-Host 'Stage   : removed after ZIP verification.'
}
