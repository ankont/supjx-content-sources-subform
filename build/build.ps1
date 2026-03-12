Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$pluginRoot = Join-Path $repoRoot 'plugin'
$manifestPath = Join-Path $pluginRoot 'sources.xml'
$buildRoot = Join-Path $repoRoot 'build'
$stageRoot = Join-Path $buildRoot 'stage'
$outputRoot = Join-Path $buildRoot 'output'

function Ensure-CleanDirectory {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Path
    )

    if (Test-Path $Path) {
        Remove-Item -Path $Path -Recurse -Force
    }

    New-Item -ItemType Directory -Path $Path | Out-Null
}

function New-ZipFromDirectoryContents {
    param(
        [Parameter(Mandatory = $true)]
        [string] $SourceDirectory,

        [Parameter(Mandatory = $true)]
        [string] $DestinationZip
    )

    if (Test-Path $DestinationZip) {
        Remove-Item -Path $DestinationZip -Force
    }

    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $destinationStream = [System.IO.File]::Open($DestinationZip, [System.IO.FileMode]::Create)

    try {
        $archive = New-Object System.IO.Compression.ZipArchive(
            $destinationStream,
            [System.IO.Compression.ZipArchiveMode]::Create,
            $false
        )

        try {
            $rootPath = [System.IO.Path]::GetFullPath($SourceDirectory)

            Get-ChildItem -Path $SourceDirectory -Recurse -File | ForEach-Object {
                $filePath = [System.IO.Path]::GetFullPath($_.FullName)
                $entryPath = $filePath.Substring($rootPath.Length).TrimStart('\', '/').Replace('\', '/')
                [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                    $archive,
                    $filePath,
                    $entryPath,
                    [System.IO.Compression.CompressionLevel]::Optimal
                ) | Out-Null
            }
        }
        finally {
            $archive.Dispose()
        }
    }
    finally {
        $destinationStream.Dispose()
    }
}

function Get-ManifestVersion {
    param(
        [Parameter(Mandatory = $true)]
        [string] $ManifestPath
    )

    if (-not (Test-Path $ManifestPath)) {
        throw "Manifest not found: $ManifestPath"
    }

    [xml]$manifest = Get-Content $ManifestPath -Raw
    $versionNode = $manifest.SelectSingleNode('/extension/version')
    $version = if ($null -ne $versionNode) { $versionNode.InnerText.Trim() } else { '' }

    if ([string]::IsNullOrWhiteSpace($version)) {
        throw "Version element not found in $ManifestPath"
    }

    return $version
}

if (-not (Test-Path $pluginRoot)) {
    throw "Plugin source folder not found: $pluginRoot"
}

$version = Get-ManifestVersion -ManifestPath $manifestPath

Ensure-CleanDirectory -Path $stageRoot
New-Item -ItemType Directory -Force -Path $outputRoot | Out-Null

$pluginStage = Join-Path $stageRoot 'plugin'
New-Item -ItemType Directory -Path $pluginStage | Out-Null
Copy-Item -Path (Join-Path $pluginRoot '*') -Destination $pluginStage -Recurse -Force

$zipPath = Join-Path $outputRoot ("plg_content_sources-v{0}.zip" -f $version)
New-ZipFromDirectoryContents -SourceDirectory $pluginStage -DestinationZip $zipPath

Write-Host ('Created: {0}' -f $zipPath)
