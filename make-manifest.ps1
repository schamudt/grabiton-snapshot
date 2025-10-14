# make-manifest.ps1
# erzeugt: overview.txt, manifest.json, filelist.txt
# lauf im projektstamm! tested on windows powershell 5/7

$ErrorActionPreference = "Stop"

$OutOverview = "overview.txt"
$OutManifest = "manifest.json"
$TmpIncludes = "includes_raw.txt"

# ordner, die ignoriert werden sollen
$ExcludeDirs = @("\.git\", "node_modules\", "vendor\", "uploads\", "backup\", "backups\", "cache\", "dist\", "build\")

function Should-Exclude($path) {
  foreach ($d in $ExcludeDirs) { if ($path -like "*$d*") { return $true } }
  return $false
}

# 1) datei-liste (relevante typen)
$files = Get-ChildItem -Recurse -File | Where-Object {
  -not (Should-Exclude $_.FullName) -and (
    $_.Name -like "*.php" -or
    $_.Name -like "*.phtml" -or
    $_.Name -like "*.html" -or
    $_.Name -like "*.htm" -or
    $_.Name -like "*.js" -or
    $_.Name -like "*.ts" -or
    $_.Name -like "*.css" -or
    $_.Name -like "*.json" -or
    $_.Name -like "*.env" -or
    $_.Name -like "composer.json" -or
    $_.Name -like "composer.lock" -or
    $_.Name -like "package.json" -or
    $_.Name -like "package-lock.json" -or
    $_.Name -like "vite.config.*" -or
    $_.Name -like "webpack.config.*"
  )
}

$root = (Get-Location).Path
$relFiles = $files |
  ForEach-Object { $_.FullName.Replace($root + [IO.Path]::DirectorySeparatorChar, "") } |
  Sort-Object

$relFiles | Out-File -Encoding UTF8 "filelist.txt"

# 2) include/require aus PHP extrahieren  (robust gegen ":" / $_)
Remove-Item $TmpIncludes -ErrorAction SilentlyContinue
$phpFiles = $relFiles | Where-Object { $_ -like "*.php" -or $_ -like "*.phtml" }
foreach ($pf in $phpFiles) {
  $i = 0
  # -Raw wäre schneller, aber wir brauchen zeilennummern → zeilenweise
  Get-Content -LiteralPath $pf | ForEach-Object {
    $i++
    $line = $_
    if ($line -match "include_once|require_once|include\(|require\(") {
      Add-Content -LiteralPath $TmpIncludes -Value ("{0}:{1}: {2}" -f $pf, $i, $line)
    }
  }
}

# 3) overview.txt schreiben
"Projekt-Overview (automatisch erzeugt)" | Out-File -Encoding UTF8 $OutOverview
"Erstellt am: $(Get-Date).ToUniversalTime().ToString('u') UTC" | Out-File -Encoding UTF8 $OutOverview -Append
"Root: $root" | Out-File -Encoding UTF8 $OutOverview -Append
"" | Out-File -Encoding UTF8 $OutOverview -Append
"Dateien:" | Out-File -Encoding UTF8 $OutOverview -Append
$lineNo = 1
foreach ($f in $relFiles) {
  ("{0,4}  {1}" -f $lineNo, $f) | Out-File -Encoding UTF8 $OutOverview -Append
  $lineNo++
}
"" | Out-File -Encoding UTF8 $OutOverview -Append
"Gefundene include/require Referenzen (Rohdaten):" | Out-File -Encoding UTF8 $OutOverview -Append
if (Test-Path $TmpIncludes) {
  Get-Content -LiteralPath $TmpIncludes | Out-File -Encoding UTF8 $OutOverview -Append
} else {
  "(keine include/require gefunden)" | Out-File -Encoding UTF8 $OutOverview -Append
}

# 4) manifest.json (strukturierte infos)
$inc = @()
if (Test-Path $TmpIncludes) {
  Get-Content -LiteralPath $TmpIncludes | ForEach-Object {
    $parts = $_ -split ":",3
    if ($parts.Count -ge 3) {
      $path = $parts[0]
      $line = [int]$parts[1]
      $code = $parts[2].Trim()
      $m = [Regex]::Match($code, "(include_once|require_once|include|require)\s*\(?\s*['""]([^'""]+)['""]")
      $included = $null
      if ($m.Success) { $included = $m.Groups[2].Value }
      $inc += [PSCustomObject]@{
        file = $path; line = $line; code = $code; included = $included
      }
    }
  }
}

$manifest = [PSCustomObject]@{
  generated_at_utc = (Get-Date).ToUniversalTime().ToString("o")
  root = $root
  files_count = $relFiles.Count
  files = $relFiles
  php_includes = $inc
}

$manifest | ConvertTo-Json -Depth 8 | Out-File -Encoding UTF8 $OutManifest

Write-Host "Fertig: overview.txt, manifest.json, filelist.txt"
