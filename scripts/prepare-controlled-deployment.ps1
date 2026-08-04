[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string] $SourceRoot,

    [Parameter(Mandatory = $true)]
    [string] $ProductionRoot,

    [Parameter(Mandatory = $true)]
    [string] $OutputRoot,

    [Parameter(Mandatory = $true)]
    [string[]] $ApprovedFiles,

    [Parameter(Mandatory = $true)]
    [string] $BackupRoot
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$resolvedSourceRoot = (Resolve-Path -LiteralPath $SourceRoot).Path
$resolvedProductionRoot = (Resolve-Path -LiteralPath $ProductionRoot).Path

if ($resolvedSourceRoot -eq $resolvedProductionRoot) {
    throw 'SourceRoot and ProductionRoot must be different directories.'
}

$excludedPattern = '(?i)(^|/)(\.env(?:\..*)?|\.git|node_modules|vendor|tests|storage/app/public/products|storage/app/backups)(/|$)|^backups(/|$)|(^|/)(design-qa\.md|docs/superpowers/plans/2026-07-29-final-pos-hold-bill\.md)$'

function Get-ManifestFileRecord {
    param(
        [Parameter(Mandatory = $true)] [string] $Root,
        [Parameter(Mandatory = $true)] [string] $RelativePath,
        [Parameter(Mandatory = $true)] [string] $BackupDirectory,
        [Parameter(Mandatory = $true)] [ValidateSet('source', 'production')] [string] $ManifestType
    )

    $normalizedRelativePath = $RelativePath.Replace('\', '/').TrimStart('/')
    $absolutePath = Join-Path $Root ($normalizedRelativePath.Replace('/', '\'))
    $exists = Test-Path -LiteralPath $absolutePath -PathType Leaf
    $fileType = [System.IO.Path]::GetExtension($absolutePath).TrimStart('.')

    if ($ManifestType -eq 'source' -and -not $exists) {
        throw "Approved Source file does not exist: $normalizedRelativePath"
    }

    $sizeBytes = $null
    $sha256 = $null

    if ($exists) {
        $file = Get-Item -LiteralPath $absolutePath
        $sizeBytes = [int64] $file.Length
        $sha256 = (Get-FileHash -LiteralPath $absolutePath -Algorithm SHA256).Hash.ToLowerInvariant()
    }

    if ($ManifestType -eq 'source') {
        return [pscustomobject] [ordered] @{
            relative_path = $normalizedRelativePath
            source_absolute_path = $absolutePath
            size_bytes = $sizeBytes
            sha256 = $sha256
            file_type = $fileType
            deploy = 'yes'
        }
    }

    return [pscustomobject] [ordered] @{
        relative_path = $normalizedRelativePath
        target_absolute_path = $absolutePath
        exists = $exists
        existing_size_bytes = $sizeBytes
        existing_sha256 = $sha256
        file_type = $fileType
        backup_destination = Join-Path $BackupDirectory ($normalizedRelativePath.Replace('/', '\'))
        deploy = 'yes'
    }
}

$normalizedApprovedFiles = @(
    $ApprovedFiles |
        ForEach-Object { $_.Replace('\', '/').TrimStart('/') } |
        Sort-Object -Unique
)

if ($normalizedApprovedFiles.Count -eq 0) {
    throw 'At least one approved runtime file is required.'
}

foreach ($relativePath in $normalizedApprovedFiles) {
    if ($relativePath -match $excludedPattern) {
        throw "Excluded path was supplied as an approved deployment file: $relativePath"
    }
}

New-Item -ItemType Directory -Force -Path $OutputRoot | Out-Null
$resolvedOutputRoot = (Resolve-Path -LiteralPath $OutputRoot).Path

$sourceManifest = @(
    $normalizedApprovedFiles | ForEach-Object {
        Get-ManifestFileRecord -Root $resolvedSourceRoot -RelativePath $_ -BackupDirectory $BackupRoot -ManifestType 'source'
    }
)

$productionManifest = @(
    $normalizedApprovedFiles | ForEach-Object {
        Get-ManifestFileRecord -Root $resolvedProductionRoot -RelativePath $_ -BackupDirectory $BackupRoot -ManifestType 'production'
    }
)

$sourceManifest | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $resolvedOutputRoot 'source-manifest.json') -Encoding utf8
$productionManifest | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $resolvedOutputRoot 'production-predeploy-manifest.json') -Encoding utf8

[pscustomobject] [ordered] @{
    generated_at = (Get-Date).ToString('o')
    source_root = $resolvedSourceRoot
    production_root = $resolvedProductionRoot
    backup_root = $BackupRoot
    source_file_count = $sourceManifest.Count
    production_file_count = $productionManifest.Count
    deploy_performed = $false
    excluded_paths = @('.env', '.git', 'node_modules', 'vendor', 'tests', 'test fixtures', 'local uploads', 'backups', 'design-qa.md', 'old Hold Bill plan')
} | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $resolvedOutputRoot 'deployment-summary.json') -Encoding utf8

Write-Output "Prepared read-only manifests for $($sourceManifest.Count) approved runtime file(s). No files were copied or changed in Production."
