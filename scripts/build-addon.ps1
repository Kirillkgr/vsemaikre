Param(
  [switch]$NoBump
)

$ErrorActionPreference = 'Stop'

$RootDir = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$CsDir = Join-Path $RootDir 'app'
$AddonId = 'branding_text'
$AddonXml = Join-Path $CsDir (Join-Path 'app/addons' (Join-Path $AddonId 'addon.xml'))
$OutDir = Join-Path $RootDir 'addon'
$OutZip = $null

if (!(Test-Path $AddonXml)) {
  throw "addon.xml not found: $AddonXml"
}

New-Item -ItemType Directory -Force -Path $OutDir | Out-Null

function Get-VersionFromXml([string]$Path) {
  $content = Get-Content -Raw -Path $Path
  $m = [regex]::Match($content, '<version>([^<]+)</version>')
  if (!$m.Success) { return '0.0.1' }
  return $m.Groups[1].Value.Trim()
}

function Inc-Patch([string]$v) {
  $m = [regex]::Match($v, '^(\d+)\.(\d+)\.(\d+)$')
  if (!$m.Success) { return '0.0.1' }
  $major = [int]$m.Groups[1].Value
  $minor = [int]$m.Groups[2].Value
  $patch = [int]$m.Groups[3].Value
  $patch++
  return "$major.$minor.$patch"
}

function Set-VersionInXml([string]$Path, [string]$NewVersion) {
  $content = Get-Content -Raw -Path $Path
  $content2 = [regex]::Replace($content, '<version>[^<]*</version>', "<version>$NewVersion</version>", 1)
  Set-Content -NoNewline -Path $Path -Value $content2
}

$curVer = Get-VersionFromXml $AddonXml
$nextVer = if ($NoBump) { $curVer } else { Inc-Patch $curVer }

if (!$NoBump) {
  Set-VersionInXml -Path $AddonXml -NewVersion $nextVer
}

$OutZip = Join-Path $OutDir "$AddonId-$nextVer.zip"

$tmp = Join-Path ([System.IO.Path]::GetTempPath()) ([System.Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Force -Path $tmp | Out-Null

try {
  function Copy-DirIfExists([string]$Source, [string]$Dest) {
    if (Test-Path $Source) {
      New-Item -ItemType Directory -Force -Path $Dest | Out-Null
      Copy-Item -Recurse -Force -Path (Join-Path $Source '*') -Destination $Dest
    }
  }

  $tmpApp = Join-Path $tmp (Join-Path 'app/addons' $AddonId)
  $tmpJs = Join-Path $tmp (Join-Path 'js/addons' $AddonId)

  $tmpTpl = Join-Path $tmp (Join-Path 'design/themes/responsive/templates/addons' $AddonId)
  $tmpCss = Join-Path $tmp (Join-Path 'design/themes/responsive/css/addons' $AddonId)
  $tmpImg = Join-Path $tmp (Join-Path 'design/themes/responsive/media/images/addons' $AddonId)

  New-Item -ItemType Directory -Force -Path $tmpApp | Out-Null
  New-Item -ItemType Directory -Force -Path $tmpJs | Out-Null
  New-Item -ItemType Directory -Force -Path $tmpTpl | Out-Null
  New-Item -ItemType Directory -Force -Path $tmpCss | Out-Null
  New-Item -ItemType Directory -Force -Path $tmpImg | Out-Null

  Copy-DirIfExists -Source (Join-Path $CsDir (Join-Path 'app/addons' $AddonId)) -Dest $tmpApp
  Copy-DirIfExists -Source (Join-Path $CsDir (Join-Path 'js/addons' $AddonId)) -Dest $tmpJs

  Copy-DirIfExists -Source (Join-Path $CsDir (Join-Path 'design/themes/responsive/templates/addons' $AddonId)) -Dest $tmpTpl
  Copy-DirIfExists -Source (Join-Path $CsDir (Join-Path 'design/themes/responsive/css/addons' $AddonId)) -Dest $tmpCss
  Copy-DirIfExists -Source (Join-Path $CsDir (Join-Path 'design/themes/responsive/media/images/addons' $AddonId)) -Dest $tmpImg

  if (Test-Path $OutZip) {
    Remove-Item -Force $OutZip
  }

  Compress-Archive -Path (Join-Path $tmp 'app'), (Join-Path $tmp 'design'), (Join-Path $tmp 'js') -DestinationPath $OutZip

  # Keep only last 3 versioned archives
  $archives = Get-ChildItem -Path $OutDir -Filter "$AddonId-*.zip" -ErrorAction SilentlyContinue | Sort-Object Name
  if ($archives.Count -gt 3) {
    $toRemove = $archives | Select-Object -First ($archives.Count - 3)
    foreach ($f in $toRemove) {
      Remove-Item -Force $f.FullName
    }
  }

  Write-Host "OK: built $OutZip"
  Write-Host "Version: $curVer -> $nextVer"
}
finally {
  Remove-Item -Recurse -Force $tmp
}
