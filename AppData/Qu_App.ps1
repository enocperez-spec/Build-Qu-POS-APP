# ============================================================
# Qu App Tool - Windows Forms Version
# Purpose: Windows UI So we can Download the Version we have In production
# Version: 3.0
# Created by William Cralle and Enoc Perez
# For any changes, please reach out to us.
# ============================================================

param(
    [string]$BaseUrl = "https://qu-releases.qubeyond.com/pos/builds",
    [string]$KioskBaseUrl = "https://qu-releases.qubeyond.com/kiosk/builds",
    [string]$OutputFile,
    [string]$CacheFile,
    [string[]]$ReleaseTrains = @("3.5.224", "3.5.225", "3.5.226"),
    [string]$CsvPath,
    [string]$PreviousCsvPath,
    [string]$LogoPath,
    [string]$ProgressFile,
    [switch]$ShowUi,
    [switch]$TimestampedOutput,
    [switch]$SkipOpen
)

if (-not $OutputFile) {
    $OutputFile = Join-Path $PSScriptRoot ($(if ($CsvPath -or $ShowUi) { "QuPOS_CurrentVersions.html" } else { "QuPOS_Builds.html" }))
if (-not $CacheFile) {
    $CacheFile = Join-Path $PSScriptRoot "QuPOS_Builds.json"
}
}

# Versioning rule: bump by 0.1 for each update, rolling 0.9 to the next whole value (for example 0.9 -> 1.0).
$ToolVersion = "3.0"

function ConvertTo-Hashtable {
    param([Parameter(ValueFromPipeline = $true)]$InputObject)
    process {
        if ($null -eq $InputObject) { return $null }
        if ($InputObject -is [System.Collections.IDictionary]) {
            $hash = @{}
            foreach ($key in $InputObject.Keys) { $hash[$key] = ConvertTo-Hashtable $InputObject[$key] }
            return $hash
        }
        if ($InputObject -is [pscustomobject]) {
            $hash = @{}
            foreach ($property in $InputObject.PSObject.Properties) { $hash[$property.Name] = ConvertTo-Hashtable $property.Value }
            return $hash
        }
        if ($InputObject -is [System.Collections.IEnumerable] -and $InputObject -isnot [string]) {
            return @($InputObject | ForEach-Object { ConvertTo-Hashtable $_ })
        }
        return $InputObject
    }
}

function ConvertTo-HtmlText { param([object]$Value) if ($null -eq $Value) { return "" }; return [System.Net.WebUtility]::HtmlEncode([string]$Value) }
function Resolve-FilePath { param([string]$Path) if ([string]::IsNullOrWhiteSpace($Path)) { return $Path }; return $ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($Path) }
function Resolve-AppDataLogoPath {
    param([string]$PreferredPath,[string]$SearchFolder)
    if (-not [string]::IsNullOrWhiteSpace($PreferredPath)) {
        $candidatePaths = [System.Collections.Generic.List[string]]::new()
        [void]$candidatePaths.Add($PreferredPath)
        if ($SearchFolder -and -not [System.IO.Path]::IsPathRooted($PreferredPath)) {
            [void]$candidatePaths.Add((Join-Path $SearchFolder $PreferredPath))
        }
        foreach ($candidatePath in ($candidatePaths | Select-Object -Unique)) {
            if (-not [string]::IsNullOrWhiteSpace($candidatePath) -and (Test-Path $candidatePath)) {
                return Resolve-FilePath $candidatePath
            }
        }
    }
    if ($SearchFolder -and (Test-Path $SearchFolder)) {
        $preferredMatch = Get-ChildItem -Path $SearchFolder -File -ErrorAction SilentlyContinue | Where-Object { $_.Extension -match '(?i)^\.(png|jpg|jpeg|gif|svg)$' -and $_.Name -match '(?i)(goto.*logo|logo.*goto)' } | Sort-Object LastWriteTime -Descending | Select-Object -First 1
        if ($preferredMatch) { return $preferredMatch.FullName }
        $fallbackMatch = Get-ChildItem -Path $SearchFolder -File -ErrorAction SilentlyContinue | Where-Object { $_.Extension -match '(?i)^\.(png|jpg|jpeg|gif|svg)$' -and $_.Name -match '(?i)logo' } | Sort-Object LastWriteTime -Descending | Select-Object -First 1
        if ($fallbackMatch) { return $fallbackMatch.FullName }
    }
    return $PreferredPath
}
$LogoPath = Resolve-AppDataLogoPath -PreferredPath $LogoPath -SearchFolder $PSScriptRoot
function Get-ReleaseTrainFromVersion { param([string]$Version) if ($Version -match "^(\d+\.\d+\.\d+)") { return $Matches[1] }; return $Version }
function Get-QuPosDownloadUrl { param([string]$Version,[string]$BaseUrl) return "$BaseUrl/Qu.POS_$Version.zip" }
function Test-IsQuKioskAppVersion { param([string]$Version) return ($Version -match "^4\.1\.\d+-\d+$") }
function Get-QuKioskDownloadUrl { param([string]$Version,[string]$KioskBaseUrl) if (Test-IsQuKioskAppVersion $Version) { return "$KioskBaseUrl/Kiosk-Setup-$Version.exe" }; return "" }
function Test-IsQuBoxAppVersion { param([string]$Version) return ($Version -match "^3\.6\.\d+-\d+$") }
function Quote-CommandLineArgument { param([string]$Value) if ($null -eq $Value) { return '""' }; return '"' + $Value.Replace('"', '\"') + '"' }
function Test-IsQuPosAppVersion { param([string]$Version) return ($Version -match "^\d+\.\d+\.\d+\.\d+$") }
function ConvertTo-ParsedLastSeen {
    param([string]$Value)
    if ([string]::IsNullOrWhiteSpace($Value)) { return $null }
    $match = [regex]::Match($Value, '^(?<stamp>\d{2}/\d{2}/\d{4}\s+\d{2}:\d{2}\s+[AP]M)')
    if (-not $match.Success) { return $null }
    $parsed = [datetime]::MinValue
    if ([datetime]::TryParseExact($match.Groups["stamp"].Value, "MM/dd/yyyy hh:mm tt", [System.Globalization.CultureInfo]::InvariantCulture, [System.Globalization.DateTimeStyles]::AssumeLocal, [ref]$parsed)) {
        return $parsed
    }
    if ([datetime]::TryParse($match.Groups["stamp"].Value, [ref]$parsed)) {
        return $parsed
    }
    return $null
}
function Get-TimestampedOutputPath {
    param([string]$OutputFile,[datetime]$GeneratedOn)
    $resolvedOutputFile = Resolve-FilePath $OutputFile
    $leaf = Split-Path $resolvedOutputFile -Leaf
    $parent = Split-Path $resolvedOutputFile -Parent
    $reportsParent = Split-Path $parent -Parent
    $baseParent = $parent
    if ((Split-Path $parent -Leaf) -match '^\d{4}-\d{2}-\d{2}$' -and (Split-Path $reportsParent -Leaf) -eq 'Reports') {
        $baseParent = Split-Path $reportsParent -Parent
    }
    $datedDirectory = Join-Path (Join-Path $baseParent "Reports") ($GeneratedOn.ToString("yyyy-MM-dd"))
    return Join-Path $datedDirectory $leaf
}
function Resolve-ProgressFilePath {
    param([string]$Path)
    if ([string]::IsNullOrWhiteSpace($Path)) { return $null }
    if ([System.IO.Path]::IsPathRooted($Path)) {
        return [System.IO.Path]::GetFullPath($Path)
    }
    $basePath = if ($PSScriptRoot) { $PSScriptRoot } else { (Get-Location).Path }
    return [System.IO.Path]::GetFullPath((Join-Path $basePath $Path))
}
function Write-StageMessage {
    param([string]$Message)
    if ([string]::IsNullOrWhiteSpace($Message)) { return }
    Write-Host $Message
    if (-not [string]::IsNullOrWhiteSpace($script:ProgressFile)) {
        [System.IO.File]::AppendAllText($script:ProgressFile, $Message + [Environment]::NewLine, [System.Text.UTF8Encoding]::new($false))
    }
}

$script:ProgressFile = $null
$requestedProgressFile = if ($PSBoundParameters.ContainsKey("ProgressFile")) { [string]$PSBoundParameters["ProgressFile"] } else { $null }
if (-not [string]::IsNullOrWhiteSpace($requestedProgressFile)) {
    $script:ProgressFile = Resolve-ProgressFilePath $requestedProgressFile
    $progressDirectory = Split-Path $script:ProgressFile -Parent
    if ($progressDirectory -and -not (Test-Path $progressDirectory)) {
        [void](New-Item -ItemType Directory -Path $progressDirectory -Force)
    }
    [System.IO.File]::WriteAllText($script:ProgressFile, "", [System.Text.UTF8Encoding]::new($false))
}

function Get-TerminalKey {
    param($Row)
    $storeId = [string]$Row."Store ID"
    $terminalId = [string]$Row."Terminal ID"
    $terminalType = [string]$Row."Terminal Type"
    $computerName = [string]$Row."Computer Name"
    if ([string]::IsNullOrWhiteSpace($terminalId)) { $terminalId = $computerName }
    return "$storeId|$terminalId|$terminalType"
}
function Get-TerminalNumberFromNetworkAddress {
    param([string]$NetworkAddress)
    if ([string]::IsNullOrWhiteSpace($NetworkAddress)) { return $null }
    switch ($NetworkAddress) {
        '192.168.22.111' { return 1 }
        '192.168.22.112' { return 2 }
        '192.168.22.10'  { return 'QuBox' }
        default { return $null }
    }
}
function Get-TerminalSortRank {
    param($Row)
    $computerName = [string]$Row."Computer Name"
    if ($computerName -match 'T(\d+)$') { return [int]$Matches[1] }
    $networkTerminal = Get-TerminalNumberFromNetworkAddress -NetworkAddress ([string]$Row."Network Address")
    if ($networkTerminal -eq 'QuBox') { return 99 }
    if ($networkTerminal -is [int]) {
        return $networkTerminal
    }
    return 500
}
function Get-TerminalDisplayName {
    param($Row)
    $computerName = [string]$Row."Computer Name"
    $networkAddress = [string]$Row."Network Address"
    $terminalId = [string]$Row."Terminal ID"
    if ($computerName -match 'T(\d+)$') { return "Terminal $($Matches[1])" }
    $networkTerminal = Get-TerminalNumberFromNetworkAddress -NetworkAddress $networkAddress
    if ($networkTerminal -eq 'QuBox') { return 'QuBox' }
    if ($networkTerminal -is [int]) {
        return "Terminal $networkTerminal"
    }
    if (-not [string]::IsNullOrWhiteSpace($computerName)) { return $computerName }
    if (-not [string]::IsNullOrWhiteSpace($terminalId)) { return "Terminal $terminalId" }
    if (-not [string]::IsNullOrWhiteSpace($networkAddress)) { return $networkAddress }
    return 'Unknown Terminal'
}
function Get-TerminalReference {
    param($Row)
    $computerName = [string]$Row."Computer Name"
    $networkAddress = [string]$Row."Network Address"
    $terminalId = [string]$Row."Terminal ID"
    if (-not [string]::IsNullOrWhiteSpace($computerName)) { return $computerName }
    if (-not [string]::IsNullOrWhiteSpace($networkAddress)) { return $networkAddress }
    if (-not [string]::IsNullOrWhiteSpace($terminalId)) { return "Terminal ID $terminalId" }
    return ''
}
function Get-VersionChangeType {
    param([string]$PreviousVersion,[string]$CurrentVersion)
    if ([string]::IsNullOrWhiteSpace($PreviousVersion) -and -not [string]::IsNullOrWhiteSpace($CurrentVersion)) { return "New" }
    if (-not [string]::IsNullOrWhiteSpace($PreviousVersion) -and [string]::IsNullOrWhiteSpace($CurrentVersion)) { return "Removed" }
    if ($PreviousVersion -eq $CurrentVersion) { return "Unchanged" }
    if ((Test-IsQuPosAppVersion $PreviousVersion) -and (Test-IsQuPosAppVersion $CurrentVersion)) {
        if ([version]$CurrentVersion -gt [version]$PreviousVersion) { return "Upgraded" }
        if ([version]$CurrentVersion -lt [version]$PreviousVersion) { return "Downgraded" }
    }
    return "Changed"
}
function Get-HtmlSearchText {
    param([object[]]$Values)
    return ConvertTo-HtmlText ((@($Values | ForEach-Object { [string]$_ }) | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }) -join " ")
}
function Get-StoreBrandNames {
    param([string]$StoreName)
    $name = [string]$StoreName
    if ([string]::IsNullOrWhiteSpace($name)) { return @() }
    $brandNames = [System.Collections.Generic.List[string]]::new()
    if ($name -match "(?i)auntie\s*anne" -or $name -match "(?i)(^|[\s/|])AA-") { if ($brandNames -notcontains "Auntie Anne's") { [void]$brandNames.Add("Auntie Anne's") } }
    if ($name -match "(?i)moe['ΓÇÖ]?s" -or $name -match "(?i)(^|[\s/|])MOES?-") { if ($brandNames -notcontains "Moe's") { [void]$brandNames.Add("Moe's") } }
    if ($name -match "(?i)schlotzsky['ΓÇÖ]?s" -or $name -match "(?i)(^|[\s/|])SCH-") { if ($brandNames -notcontains "Schlotzsky's") { [void]$brandNames.Add("Schlotzsky's") } }
    if ($name -match "(?i)cinnabon" -or $name -match "(?i)(^|[\s/|])CB-") { if ($brandNames -notcontains "Cinnabon") { [void]$brandNames.Add("Cinnabon") } }
    if ($name -match "(?i)carvel" -or $name -match "(?i)(^|[\s/|])CV-") { if ($brandNames -notcontains "Carvel") { [void]$brandNames.Add("Carvel") } }
    if ($name -match "(?i)jamba" -or $name -match "(?i)(^|[\s/|])JA-") { if ($brandNames -notcontains "Jamba") { [void]$brandNames.Add("Jamba") } }
    return @($brandNames)
}
function Get-StoreBrandSearchText {
    param([string]$StoreName)
    $searchTerms = [System.Collections.Generic.List[string]]::new()
    foreach ($brandName in @(Get-StoreBrandNames -StoreName $StoreName)) {
        if ($searchTerms -notcontains $brandName) { [void]$searchTerms.Add($brandName) }
        switch ($brandName) {
            "Auntie Anne's" { foreach ($alias in @("Auntie Annes", "AA")) { if ($searchTerms -notcontains $alias) { [void]$searchTerms.Add($alias) } } }
            "Moe's" { foreach ($alias in @("Moes", "MOES")) { if ($searchTerms -notcontains $alias) { [void]$searchTerms.Add($alias) } } }
            "Schlotzsky's" { foreach ($alias in @("Schlotzskys", "SCH")) { if ($searchTerms -notcontains $alias) { [void]$searchTerms.Add($alias) } } }
            "Cinnabon" { if ($searchTerms -notcontains "CB") { [void]$searchTerms.Add("CB") } }
            "Carvel" { if ($searchTerms -notcontains "CV") { [void]$searchTerms.Add("CV") } }
            "Jamba" { if ($searchTerms -notcontains "JA") { [void]$searchTerms.Add("JA") } }
        }
    }
    return ($searchTerms -join " ")
}
function Get-HtmlCell {
    param([string]$Html,[string]$SortValue)
    $sortAttribute = if ([string]::IsNullOrWhiteSpace($SortValue)) { "" } else { " data-sort-value='$(ConvertTo-HtmlText $SortValue)'" }
    return "<td$sortAttribute>$Html</td>"
}
function Get-HtmlTableStart {
    param([object[]]$Columns,[string]$Id,[string]$CssClass = "report-table sortable searchable")
    $idAttribute = if ([string]::IsNullOrWhiteSpace($Id)) { "" } else { " id='$(ConvertTo-HtmlText $Id)'" }
    $builder = [System.Text.StringBuilder]::new()
    [void]$builder.AppendLine("<table$idAttribute class='$CssClass'>")
    [void]$builder.AppendLine("<thead><tr>")
    foreach ($column in $Columns) {
        $sortAttribute = if ($column.Sort) { " data-sort='$(ConvertTo-HtmlText $column.Sort)'" } else { "" }
        [void]$builder.AppendLine("<th$sortAttribute>$(ConvertTo-HtmlText $column.Label)</th>")
    }
    [void]$builder.AppendLine("</tr></thead><tbody>")
    return $builder.ToString()
}
function Get-HtmlTableEnd { return "</tbody></table>" }
function Get-ImageMimeType {
    param([string]$Path)
    switch ([System.IO.Path]::GetExtension($Path).ToLowerInvariant()) {
        ".png" { return "image/png" }
        ".jpg" { return "image/jpeg" }
        ".jpeg" { return "image/jpeg" }
        ".gif" { return "image/gif" }
        ".svg" { return "image/svg+xml" }
        default { return "application/octet-stream" }
    }
}
function Get-ImageDataUri {
    param([string]$Path)
    if ([string]::IsNullOrWhiteSpace($Path) -or -not (Test-Path $Path)) { return "" }
    $resolvedPath = (Resolve-Path $Path).Path
    $bytes = [System.IO.File]::ReadAllBytes($resolvedPath)
    $mimeType = Get-ImageMimeType -Path $resolvedPath
    return "data:$mimeType;base64,$([Convert]::ToBase64String($bytes))"
}
function Get-SafeHtmlId {
    param([string]$Value)
    if ([string]::IsNullOrWhiteSpace($Value)) { return 'item' }
    $safe = [regex]::Replace($Value, '[^A-Za-z0-9_-]', '-')
    $safe = [regex]::Replace($safe, '-{2,}', '-').Trim('-')
    if ([string]::IsNullOrWhiteSpace($safe)) { return 'item' }
    return $safe
}
function Get-VersionStatus {
    param([string]$Version,[string]$CurrentStableVersion,[string]$MostCurrentVersion)
    if (-not (Test-IsQuPosAppVersion $Version)) { return 'neutral' }
    if (-not (Test-IsQuPosAppVersion $CurrentStableVersion)) { return 'neutral' }
    if ($Version -eq $CurrentStableVersion) { return 'stable' }
    if ((Test-IsQuPosAppVersion $MostCurrentVersion) -and $Version -eq $MostCurrentVersion) { return 'current' }
    if ([version]$Version -gt [version]$CurrentStableVersion) { return 'higher' }
    return 'outdated'
}
function Get-VersionBadgeHtml {
    param([string]$Version,[string]$CurrentStableVersion,[string]$MostCurrentVersion,[string]$FallbackStatus = 'neutral')
    if ([string]::IsNullOrWhiteSpace($Version)) { return '' }
    $status = if (Test-IsQuPosAppVersion $Version) { Get-VersionStatus -Version $Version -CurrentStableVersion $CurrentStableVersion -MostCurrentVersion $MostCurrentVersion } else { $FallbackStatus }
    $tooltip = switch ($status) {
        'stable' { 'Current Stable Version' }
        'current' { 'Most Current Version' }
        'higher' { 'Above Stable Version' }
        'outdated' { 'Out-Of-Date Version' }
        default { 'Other Version' }
    }
    return "<span class='version-badge $status' data-version-status='$status' title='$(ConvertTo-HtmlText $tooltip)'>$(ConvertTo-HtmlText $Version)</span>"
}
function Get-VersionBadgeListHtml {
    param([object[]]$Versions,[string]$CurrentStableVersion,[string]$MostCurrentVersion,[string]$FallbackStatus = 'neutral')
    $items = @($Versions | Where-Object { -not [string]::IsNullOrWhiteSpace([string]$_) })
    if ($items.Count -eq 0) { return '' }
    return (($items | ForEach-Object { Get-VersionBadgeHtml -Version ([string]$_) -CurrentStableVersion $CurrentStableVersion -MostCurrentVersion $MostCurrentVersion -FallbackStatus $FallbackStatus }) -join ' ')
}
function Get-StoreLookupValue {
    param([hashtable]$Lookup,[string]$StoreId)
    if ($null -eq $Lookup) { return $null }
    $key = [string]$StoreId
    if ($Lookup.ContainsKey($key)) { return $Lookup[$key] }
    return $null
}
function Get-StoreButtonHtml {
    param([string]$StoreId,[string]$StoreName,[string]$DetailKey)
    $display = if ([string]::IsNullOrWhiteSpace($StoreName)) { $StoreId } else { $StoreName }
    if ([string]::IsNullOrWhiteSpace($display)) { return '' }
    if ([string]::IsNullOrWhiteSpace($DetailKey)) { return ConvertTo-HtmlText $display }
    $label = if ([string]::IsNullOrWhiteSpace($StoreId)) { $display } else { "Store $StoreId - $display" }
    return "<button type='button' class='store-link' data-store-detail-target='store-detail-$(ConvertTo-HtmlText $DetailKey)' data-store-detail-label='$(ConvertTo-HtmlText $label)'>$(ConvertTo-HtmlText $display)</button>"
}
function Get-TerminalVersionMapBadgeHtml {
    param([pscustomobject]$Store,[string]$CurrentStableVersion,[string]$MostCurrentVersion)
    if ($null -eq $Store) { return '' }
    $entries = @($Store.TerminalDetails)
    if ($entries.Count -eq 0) { return ConvertTo-HtmlText $Store.TerminalVersionMapText }
    return (($entries | ForEach-Object {
        $label = if ([string]::IsNullOrWhiteSpace($_.TerminalReference) -or $_.TerminalReference -eq $_.TerminalLabel) {
            ConvertTo-HtmlText $_.TerminalLabel
        } else {
            "$(ConvertTo-HtmlText $_.TerminalLabel) ($(ConvertTo-HtmlText $_.TerminalReference))"
        }
        "${label}: $(Get-VersionBadgeHtml -Version $_.CurrentVersion -CurrentStableVersion $CurrentStableVersion -MostCurrentVersion $MostCurrentVersion)"
    }) -join '<br />')
}
function New-StoreDetailTemplateHtml {
    param([pscustomobject]$Store,[string]$CurrentStableVersion,[string]$MostCurrentVersion)
    $builder = [System.Text.StringBuilder]::new()
    $versionsHtml = Get-VersionBadgeListHtml -Versions @($Store.VersionsDetectedList) -CurrentStableVersion $CurrentStableVersion -MostCurrentVersion $MostCurrentVersion
    $mostCommonVersionHtml = Get-VersionBadgeHtml -Version $Store.MostCommonVersion -CurrentStableVersion $CurrentStableVersion -MostCurrentVersion $MostCurrentVersion
    [void]$builder.AppendLine("<div class='detail-summary'>")
    [void]$builder.AppendLine("<div class='detail-card'><span class='label'>Store ID</span><span class='value-small'>$(ConvertTo-HtmlText $Store.StoreId)</span></div>")
    [void]$builder.AppendLine("<div class='detail-card'><span class='label'>POS Terminals</span><span class='value-small'>$(ConvertTo-HtmlText $Store.TotalPosTerminals)</span></div>")
    [void]$builder.AppendLine("<div class='detail-card'><span class='label'>Most Common Version</span><span class='value-small'>$mostCommonVersionHtml</span></div>")
    [void]$builder.AppendLine("<div class='detail-card'><span class='label'>Out-Of-Date Terminals</span><span class='value-small'>$(ConvertTo-HtmlText $Store.OutOfDateTerminalCount)</span></div>")
    [void]$builder.AppendLine("<div class='detail-card detail-card-wide'><span class='label'>Versions Detected</span><span class='value-small version-line'>$versionsHtml</span></div>")
    [void]$builder.AppendLine("<div class='detail-card detail-card-wide'><span class='label'>Latest Seen</span><span class='value-small'>$(ConvertTo-HtmlText $Store.LatestSeen)</span></div>")
    [void]$builder.AppendLine("</div>")
    [void]$builder.AppendLine("<table class='detail-table'><thead><tr><th>Terminal</th><th>Version</th><th>Type</th><th>Last Seen</th><th>Computer Name</th><th>Network Address</th><th>Terminal ID</th></tr></thead><tbody>")
    foreach ($terminal in @($Store.TerminalDetails)) {
        $terminalHtml = if ([string]::IsNullOrWhiteSpace($terminal.TerminalReference) -or $terminal.TerminalReference -eq $terminal.TerminalLabel) {
            ConvertTo-HtmlText $terminal.TerminalLabel
        } else {
            "<strong>$(ConvertTo-HtmlText $terminal.TerminalLabel)</strong><div class='terminal-ref'>$(ConvertTo-HtmlText $terminal.TerminalReference)</div>"
        }
        [void]$builder.AppendLine('<tr>')
        [void]$builder.AppendLine("<td>$terminalHtml</td>")
        [void]$builder.AppendLine("<td>$(Get-VersionBadgeHtml -Version $terminal.CurrentVersion -CurrentStableVersion $CurrentStableVersion -MostCurrentVersion $MostCurrentVersion)</td>")
        [void]$builder.AppendLine("<td>$(ConvertTo-HtmlText $terminal.TerminalType)</td>")
        [void]$builder.AppendLine("<td>$(ConvertTo-HtmlText $terminal.LastSeen)</td>")
        [void]$builder.AppendLine("<td>$(ConvertTo-HtmlText $terminal.ComputerName)</td>")
        [void]$builder.AppendLine("<td>$(ConvertTo-HtmlText $terminal.NetworkAddress)</td>")
        [void]$builder.AppendLine("<td>$(ConvertTo-HtmlText $terminal.TerminalId)</td>")
        [void]$builder.AppendLine('</tr>')
    }
    [void]$builder.AppendLine('</tbody></table>')
    return $builder.ToString()
}
function Set-ConsoleWindowVisibility {
    param([bool]$Visible)

    if (-not ("QuApp.NativeMethods" -as [type])) {
        Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;

namespace QuApp {
    public static class NativeMethods {
        [DllImport("kernel32.dll")]
        public static extern IntPtr GetConsoleWindow();

        [DllImport("user32.dll")]
        [return: MarshalAs(UnmanagedType.Bool)]
        public static extern bool ShowWindow(IntPtr hWnd, int nCmdShow);
    }
}
"@
    }

    $handle = [QuApp.NativeMethods]::GetConsoleWindow()
    if ($handle -ne [IntPtr]::Zero) {
        $showCommand = if ($Visible) { 5 } else { 0 }
        [QuApp.NativeMethods]::ShowWindow($handle, $showCommand) | Out-Null
    }
}

function Hide-ConsoleWindow {
    Set-ConsoleWindowVisibility -Visible:$false
}

function Show-ConsoleWindow {
    Set-ConsoleWindowVisibility -Visible:$true
}

function Load-CachedResults {
    param([string]$CacheFile)
    if (-not (Test-Path $CacheFile)) { return @{} }
    Write-Host "Loading cache..." -ForegroundColor Cyan
    return ConvertTo-Hashtable (Get-Content $CacheFile -Raw | ConvertFrom-Json)
}

function Save-CachedResults {
    param([hashtable]$Results,[string]$CacheFile)
    $Results | ConvertTo-Json -Depth 6 | Out-File $CacheFile -Encoding UTF8
}

function Get-BuildResultsFromScan {
    param([string[]]$ReleaseTrains,[string]$BaseUrl,[hashtable]$CachedResults)
    foreach ($train in $ReleaseTrains) {
        Write-Host "Scanning $train..." -ForegroundColor Yellow
        $results = @()
        for ($build = 1; $build -le 9999; $build++) {
            $version = "$train.$build"
            $url = Get-QuPosDownloadUrl -Version $version -BaseUrl $BaseUrl
            try {
                $response = Invoke-WebRequest -Uri $url -Method Head -TimeoutSec 3 -ErrorAction Stop
                if ($response.StatusCode -eq 200) {
                    Write-Host "FOUND: $version" -ForegroundColor Green
                    $results += [pscustomobject]@{ Version = $version; Url = $url }
                }
            } catch {}
            Start-Sleep -Milliseconds 120
        }
        if ($results.Count -gt 0) { $CachedResults[$train] = @($results | Sort-Object { [version]$_.Version }) }
    }
    return $CachedResults
}

function Get-CsvComparisonData {
    param([object[]]$CurrentRows,[string]$PreviousCsvPath)

    if ([string]::IsNullOrWhiteSpace($PreviousCsvPath)) { return $null }
    if (-not (Test-Path $PreviousCsvPath)) { throw "Previous CSV file not found: $PreviousCsvPath" }

    Write-StageMessage "Loading comparison CSV"
    $previousRows = @(
        Import-Csv $PreviousCsvPath |
        Where-Object { -not [string]::IsNullOrWhiteSpace($_."Current Version") }
    )

    $currentMap = @{}
    foreach ($row in $CurrentRows) { $currentMap[(Get-TerminalKey $row)] = $row }

    $previousMap = @{}
    foreach ($row in $previousRows) { $previousMap[(Get-TerminalKey $row)] = $row }

    $changedTerminals = @()
    $newTerminals = @()
    $removedTerminals = @()

    $allKeys = @($currentMap.Keys + $previousMap.Keys | Sort-Object -Unique)
    foreach ($key in $allKeys) {
        $currentRow = if ($currentMap.ContainsKey($key)) { $currentMap[$key] } else { $null }
        $previousRow = if ($previousMap.ContainsKey($key)) { $previousMap[$key] } else { $null }

        $currentVersion = if ($currentRow) { [string]$currentRow."Current Version" } else { "" }
        $previousVersion = if ($previousRow) { [string]$previousRow."Current Version" } else { "" }

        if ($currentRow -and $previousRow) {
            if ($currentVersion -ne $previousVersion) {
                $changeType = Get-VersionChangeType -PreviousVersion $previousVersion -CurrentVersion $currentVersion
                $changedTerminals += [pscustomobject]@{
                    StoreId         = [string]$currentRow."Store ID"
                    StoreName       = [string]$currentRow."Store Name"
                    TerminalId      = [string]$currentRow."Terminal ID"
                    ComputerName    = [string]$currentRow."Computer Name"
                    TerminalType    = [string]$currentRow."Terminal Type"
                    PreviousVersion = $previousVersion
                    CurrentVersion  = $currentVersion
                    ChangeType      = $changeType
                    LastSeenOnline  = [string]$currentRow."Last Seen Online"
                }
            }
            continue
        }

        if ($currentRow) {
            $newTerminals += [pscustomobject]@{
                StoreId        = [string]$currentRow."Store ID"
                StoreName      = [string]$currentRow."Store Name"
                TerminalId     = [string]$currentRow."Terminal ID"
                ComputerName   = [string]$currentRow."Computer Name"
                TerminalType   = [string]$currentRow."Terminal Type"
                CurrentVersion = $currentVersion
                LastSeenOnline = [string]$currentRow."Last Seen Online"
            }
            continue
        }

        if ($previousRow) {
            $removedTerminals += [pscustomobject]@{
                StoreId         = [string]$previousRow."Store ID"
                StoreName       = [string]$previousRow."Store Name"
                TerminalId      = [string]$previousRow."Terminal ID"
                ComputerName    = [string]$previousRow."Computer Name"
                TerminalType    = [string]$previousRow."Terminal Type"
                PreviousVersion = $previousVersion
                LastSeenOnline  = [string]$previousRow."Last Seen Online"
            }
        }
    }

    $changedTerminals = @($changedTerminals | Sort-Object StoreName, StoreId, TerminalType, TerminalId)
    $newTerminals = @($newTerminals | Sort-Object StoreName, StoreId, TerminalType, TerminalId)
    $removedTerminals = @($removedTerminals | Sort-Object StoreName, StoreId, TerminalType, TerminalId)

    $versionTransitions = @(
        $changedTerminals |
        Group-Object { "$($_.PreviousVersion) -> $($_.CurrentVersion)" } |
        ForEach-Object {
            $first = $_.Group[0]
            [pscustomobject]@{
                Transition = $_.Name
                PreviousVersion = $first.PreviousVersion
                CurrentVersion  = $first.CurrentVersion
                ChangeType      = $first.ChangeType
                TerminalCount   = $_.Count
            }
        } |
        Sort-Object @{ Expression = "TerminalCount"; Descending = $true }, @{ Expression = "CurrentVersion"; Descending = $true }, @{ Expression = "PreviousVersion"; Descending = $true }
    )

    $impactedStores = @(
        @($changedTerminals + $newTerminals + $removedTerminals) |
        Group-Object StoreId |
        ForEach-Object {
            $storeRows = @($_.Group)
            $first = $storeRows[0]
            [pscustomobject]@{
                StoreId              = $first.StoreId
                StoreName            = $first.StoreName
                ChangedTerminalCount = @($storeRows | Where-Object { $_.PSObject.Properties.Name -contains "ChangeType" }).Count
                NewTerminalCount     = @($storeRows | Where-Object { $_.PSObject.Properties.Name -contains "CurrentVersion" -and $_.PSObject.Properties.Name -notcontains "PreviousVersion" }).Count
                RemovedTerminalCount = @($storeRows | Where-Object { $_.PSObject.Properties.Name -contains "PreviousVersion" -and $_.PSObject.Properties.Name -notcontains "CurrentVersion" }).Count
            }
        } |
        Sort-Object @{ Expression = "ChangedTerminalCount"; Descending = $true }, @{ Expression = "NewTerminalCount"; Descending = $true }, @{ Expression = "RemovedTerminalCount"; Descending = $true }, StoreName
    )

    return [pscustomobject]@{
        PreviousSourceCsv      = (Resolve-Path $PreviousCsvPath).Path
        ChangedTerminalCount   = $changedTerminals.Count
        NewTerminalCount       = $newTerminals.Count
        RemovedTerminalCount   = $removedTerminals.Count
        UpgradedTerminalCount  = @($changedTerminals | Where-Object { $_.ChangeType -eq "Upgraded" }).Count
        DowngradedTerminalCount = @($changedTerminals | Where-Object { $_.ChangeType -eq "Downgraded" }).Count
        ImpactedStoreCount     = $impactedStores.Count
        VersionTransitions     = $versionTransitions
        ChangedTerminals       = $changedTerminals
        NewTerminals           = $newTerminals
        RemovedTerminals       = $removedTerminals
        ImpactedStores         = $impactedStores
    }
}

function Get-CurrentVersionsFromCsv {
    param([string]$CsvPath,[string]$BaseUrl,[string]$KioskBaseUrl,[string]$PreviousCsvPath)
    if (-not (Test-Path $CsvPath)) { throw "CSV file not found: $CsvPath" }
    Write-StageMessage "Loading CSV"
    $rows = @(Import-Csv $CsvPath)
    if ($rows.Count -eq 0) { throw "The CSV does not contain any rows." }
    $rowsWithVersions = @($rows | Where-Object { -not [string]::IsNullOrWhiteSpace($_."Current Version") })
    if ($rowsWithVersions.Count -eq 0) { throw "The CSV does not contain any values in the 'Current Version' column." }
    Write-StageMessage "Building report"
    $downloadableRows = @($rowsWithVersions | Where-Object { Test-IsQuPosAppVersion $_."Current Version" })
    $otherRows = @($rowsWithVersions | Where-Object { -not (Test-IsQuPosAppVersion $_."Current Version") })
    $kioskRows = @($otherRows | Where-Object { $_."Terminal Type" -eq "Kiosk" -or (Test-IsQuKioskAppVersion $_."Current Version") })
    $quBoxRows = @($otherRows | Where-Object { $_."Terminal Type" -eq "QuBox" -or (Test-IsQuBoxAppVersion $_."Current Version") })
    $remainingOtherRows = @($otherRows | Where-Object { -not ($kioskRows -contains $_) -and -not ($quBoxRows -contains $_) })
    $downloadableVersions = @(
        $downloadableRows |
        Group-Object "Current Version" |
        Sort-Object { [version]$_.Name } -Descending |
        ForEach-Object {
            $groupRows = @($_.Group)
            $storeGroups = @($groupRows | Group-Object "Store ID")
            [pscustomobject]@{
                Version       = $_.Name
                ReleaseTrain  = Get-ReleaseTrainFromVersion $_.Name
                TerminalCount = $groupRows.Count
                StoreCount    = $storeGroups.Count
                TerminalTypes = (($groupRows."Terminal Type" | Sort-Object -Unique) -join ", ")
                Url           = Get-QuPosDownloadUrl -Version $_.Name -BaseUrl $BaseUrl
                StoreRows     = @(
                    $storeGroups |
                    ForEach-Object {
                        $storeRows = @($_.Group)
                        $first = $storeRows[0]
                        [pscustomobject]@{
                            StoreId       = $first."Store ID"
                            StoreName     = $first."Store Name"
                            TerminalCount = $storeRows.Count
                            TerminalTypes = (($storeRows."Terminal Type" | Sort-Object -Unique) -join ", ")
                            LatestSeen    = ($storeRows."Last Seen Online" | Where-Object { $_ } | Sort-Object -Descending | Select-Object -First 1)
                        }
                    } | Sort-Object StoreName, StoreId
                )
            }
        }
    )
    $kioskVersions = @(
        $kioskRows |
        Group-Object "Current Version" |
        ForEach-Object {
            $groupRows = @($_.Group)
            $storeGroups = @($groupRows | Group-Object "Store ID")
            [pscustomobject]@{
                Version       = $_.Name
                TerminalCount = $groupRows.Count
                StoreCount    = $storeGroups.Count
                TerminalTypes = (($groupRows."Terminal Type" | Sort-Object -Unique) -join ", ")
                Url           = Get-QuKioskDownloadUrl -Version $_.Name -KioskBaseUrl $KioskBaseUrl
                StoreRows     = @(
                    $storeGroups |
                    ForEach-Object {
                        $storeRows = @($_.Group)
                        $first = $storeRows[0]
                        [pscustomobject]@{
                            StoreId       = $first."Store ID"
                            StoreName     = $first."Store Name"
                            TerminalCount = $storeRows.Count
                            TerminalTypes = (($storeRows."Terminal Type" | Sort-Object -Unique) -join ", ")
                            LatestSeen    = ($storeRows."Last Seen Online" | Where-Object { $_ } | Sort-Object -Descending | Select-Object -First 1)
                        }
                    } | Sort-Object StoreName, StoreId
                )
            }
        } | Sort-Object @{ Expression = "TerminalCount"; Descending = $true }, Version
    )
    $quBoxVersions = @(
        $quBoxRows |
        Group-Object "Current Version" |
        ForEach-Object {
            $groupRows = @($_.Group)
            $storeGroups = @($groupRows | Group-Object "Store ID")
            [pscustomobject]@{
                Version       = $_.Name
                TerminalCount = $groupRows.Count
                StoreCount    = $storeGroups.Count
                TerminalTypes = (($groupRows."Terminal Type" | Sort-Object -Unique) -join ", ")
                Url           = ""
                StoreRows     = @(
                    $storeGroups |
                    ForEach-Object {
                        $storeRows = @($_.Group)
                        $first = $storeRows[0]
                        [pscustomobject]@{
                            StoreId       = $first."Store ID"
                            StoreName     = $first."Store Name"
                            TerminalCount = $storeRows.Count
                            TerminalTypes = (($storeRows."Terminal Type" | Sort-Object -Unique) -join ", ")
                            LatestSeen    = ($storeRows."Last Seen Online" | Where-Object { $_ } | Sort-Object -Descending | Select-Object -First 1)
                        }
                    } | Sort-Object StoreName, StoreId
                )
            }
        } | Sort-Object @{ Expression = "TerminalCount"; Descending = $true }, Version
    )
    $otherVersions = @(
        $remainingOtherRows |
        Group-Object "Current Version" |
        ForEach-Object {
            $groupRows = @($_.Group)
            $storeGroups = @($groupRows | Group-Object "Store ID")
            [pscustomobject]@{
                Version       = $_.Name
                TerminalCount = $groupRows.Count
                StoreCount    = $storeGroups.Count
                TerminalTypes = (($groupRows."Terminal Type" | Sort-Object -Unique) -join ", ")
                Url           = ""
                StoreRows     = @(
                    $storeGroups |
                    ForEach-Object {
                        $storeRows = @($_.Group)
                        $first = $storeRows[0]
                        [pscustomobject]@{
                            StoreId       = $first."Store ID"
                            StoreName     = $first."Store Name"
                            TerminalCount = $storeRows.Count
                            TerminalTypes = (($storeRows."Terminal Type" | Sort-Object -Unique) -join ", ")
                            LatestSeen    = ($storeRows."Last Seen Online" | Where-Object { $_ } | Sort-Object -Descending | Select-Object -First 1)
                        }
                    } | Sort-Object StoreName, StoreId
                )
            }
        } | Sort-Object @{ Expression = "TerminalCount"; Descending = $true }, Version
    )
    $mostCurrentVersion = if ($downloadableVersions.Count -gt 0) { $downloadableVersions[0].Version } else { "N/A" }
    $currentStableVersionInfo = if ($downloadableVersions.Count -gt 0) {
        $downloadableVersions |
        Sort-Object @{ Expression = "TerminalCount"; Descending = $true }, @{ Expression = { [version]$_.Version }; Descending = $true } |
        Select-Object -First 1
    } else {
        $null
    }
    $currentStableVersion = if ($currentStableVersionInfo) { $currentStableVersionInfo.Version } else { "N/A" }
    $currentStableVersionCount = if ($currentStableVersionInfo) { $currentStableVersionInfo.TerminalCount } else { 0 }
    $orderedDownloadableVersions = @($downloadableVersions.Version)
    $versionRank = @{}
    for ($i = 0; $i -lt $orderedDownloadableVersions.Count; $i++) {
        $versionRank[$orderedDownloadableVersions[$i]] = $i
    }
    $stableVersionRank = if ($versionRank.ContainsKey($currentStableVersion)) { $versionRank[$currentStableVersion] } else { $orderedDownloadableVersions.Count }
    $storeGroups = @($downloadableRows | Group-Object "Store ID")
    $outOfDateTerminalRows = @()
    if ($currentStableVersion -ne "N/A") {
        $outOfDateTerminalRows = @($downloadableRows | Where-Object { [version]$_."Current Version" -lt [version]$currentStableVersion })
    }
    $outOfDateStoreCount = @($outOfDateTerminalRows | Group-Object "Store ID").Count
    $outOfDateVersionSummary = @(
        $outOfDateTerminalRows |
        Group-Object "Current Version" |
        ForEach-Object {
            $versionRows = @($_.Group)
            [pscustomobject]@{
                Version       = $_.Name
                TerminalCount = $versionRows.Count
                StoreCount    = (@($versionRows | Group-Object "Store ID")).Count
            }
        } |
        Sort-Object @{ Expression = { [version]$_.Version }; Descending = $true }
    )
    $storeVersionReport = @(
        $storeGroups |
        ForEach-Object {
            $storeRows = @($_.Group)
            $outdatedRows = if ($currentStableVersion -ne "N/A") {
                @($storeRows | Where-Object { [version]$_."Current Version" -lt [version]$currentStableVersion })
            } else {
                @()
            }
            $first = $storeRows[0]
            $sortedStoreVersions = @($storeRows."Current Version" | Sort-Object { [version]$_ } -Unique -Descending)
            $versionCounts = @(
                $storeRows |
                Group-Object "Current Version" |
                Sort-Object @{ Expression = "Count"; Descending = $true }, @{ Expression = "Name"; Descending = $false } |
                Select-Object -First 1
            )
            $highestVersion = if ($sortedStoreVersions.Count -gt 0) { $sortedStoreVersions[0] } else { "" }
            $lowestVersion = if ($sortedStoreVersions.Count -gt 0) { $sortedStoreVersions[-1] } else { "" }
            $highestRank = if ($versionRank.ContainsKey($highestVersion)) { $versionRank[$highestVersion] } else { $orderedDownloadableVersions.Count }
            $lowestRank = if ($versionRank.ContainsKey($lowestVersion)) { $versionRank[$lowestVersion] } else { $highestRank }
            $versionGapFromStable = if ($highestRank -gt $stableVersionRank) { $highestRank - $stableVersionRank } else { 0 }
            $terminalDetails = @(
                $storeRows |
                ForEach-Object {
                    [pscustomobject]@{
                        TerminalLabel     = (Get-TerminalDisplayName -Row $_)
                        TerminalReference = (Get-TerminalReference -Row $_)
                        CurrentVersion    = [string]$_."Current Version"
                        TerminalType      = [string]$_."Terminal Type"
                        LastSeen          = [string]$_."Last Seen Online"
                        ComputerName      = [string]$_."Computer Name"
                        NetworkAddress    = [string]$_."Network Address"
                        TerminalId        = [string]$_."Terminal ID"
                        SortRank          = (Get-TerminalSortRank -Row $_)
                    }
                } |
                Sort-Object SortRank, TerminalLabel, TerminalReference, CurrentVersion
            )
            $terminalVersionMapText = (($terminalDetails | ForEach-Object {
                if ([string]::IsNullOrWhiteSpace($_.TerminalReference) -or $_.TerminalReference -eq $_.TerminalLabel) {
                    "$($_.TerminalLabel): $($_.CurrentVersion)"
                } else {
                    "$($_.TerminalLabel) ($($_.TerminalReference)): $($_.CurrentVersion)"
                }
            }) -join '; ')
            $terminalVersionMapHtml = (($terminalDetails | ForEach-Object {
                if ([string]::IsNullOrWhiteSpace($_.TerminalReference) -or $_.TerminalReference -eq $_.TerminalLabel) {
                    "$(ConvertTo-HtmlText $_.TerminalLabel): $(ConvertTo-HtmlText $_.CurrentVersion)"
                } else {
                    "$(ConvertTo-HtmlText $_.TerminalLabel) ($(ConvertTo-HtmlText $_.TerminalReference)): $(ConvertTo-HtmlText $_.CurrentVersion)"
                }
            }) -join '<br />')
            [pscustomobject]@{
                StoreId                = [string]$first."Store ID"
                StoreName              = [string]$first."Store Name"
                StoreDetailKey         = Get-SafeHtmlId ("store-" + [string]$first."Store ID")
                VersionsDetected       = (($sortedStoreVersions) -join ", ")
                VersionsDetectedList   = @($sortedStoreVersions)
                MostCommonVersion      = if ($versionCounts.Count -gt 0) { $versionCounts[0].Name } else { "" }
                OutOfDateTerminalCount = $outdatedRows.Count
                TotalPosTerminals      = $storeRows.Count
                LatestSeen             = ($storeRows."Last Seen Online" | Where-Object { $_ } | Sort-Object -Descending | Select-Object -First 1)
                HighestVersion         = $highestVersion
                LowestVersion          = $lowestVersion
                UniqueVersionCount     = $sortedStoreVersions.Count
                TerminalDetails        = $terminalDetails
                TerminalVersionMapText = $terminalVersionMapText
                TerminalVersionMapHtml = $terminalVersionMapHtml
                VersionRankFromCurrent = $versionGapFromStable
                VersionGapFromStable   = $versionGapFromStable
                VersionDriftCount      = [math]::Abs($lowestRank - $highestRank)
            }
        } |
        Sort-Object @{ Expression = "OutOfDateTerminalCount"; Descending = $true }, @{ Expression = "TotalPosTerminals"; Descending = $true }, StoreName
    )
    $outOfDateStores = @($storeVersionReport | Where-Object { $_.OutOfDateTerminalCount -gt 0 })
    Write-StageMessage "Building alerts"
    $staleThresholdDays = 7
    $farBehindGapThreshold = 2
    $staleCutoff = (Get-Date).AddDays(-$staleThresholdDays)
    $mixedVersionStores = @(
        $storeVersionReport |
        Where-Object { $_.UniqueVersionCount -gt 1 } |
        Sort-Object @{ Expression = "UniqueVersionCount"; Descending = $true }, @{ Expression = "VersionDriftCount"; Descending = $true }, @{ Expression = "OutOfDateTerminalCount"; Descending = $true }, StoreName
    )
    $staleTerminals = @(
        $downloadableRows |
        ForEach-Object {
            $parsedLastSeen = ConvertTo-ParsedLastSeen $_."Last Seen Online"
            if (($null -eq $parsedLastSeen) -or ($parsedLastSeen -lt $staleCutoff)) {
                $ageDays = if ($parsedLastSeen) { [math]::Round(((Get-Date) - $parsedLastSeen).TotalDays, 1) } else { $null }
                [pscustomobject]@{
                    StoreId          = [string]$_."Store ID"
                    StoreName        = [string]$_."Store Name"
                    TerminalId       = [string]$_."Terminal ID"
                    ComputerName     = [string]$_."Computer Name"
                    TerminalType     = [string]$_."Terminal Type"
                    CurrentVersion   = [string]$_."Current Version"
                    LastSeenOnline   = [string]$_."Last Seen Online"
                    LastSeenAgeDays  = if ($ageDays -ne $null) { $ageDays } else { 99999 }
                    DaysSinceLastSeen = if ($ageDays -ne $null) { "$ageDays days" } else { "Unknown" }
                }
            }
        } |
        Sort-Object @{ Expression = "LastSeenAgeDays"; Descending = $true }, StoreName, TerminalId
    )
    $farBehindStores = @(
        $storeVersionReport |
        Where-Object { $_.VersionGapFromStable -ge $farBehindGapThreshold } |
        Sort-Object @{ Expression = "VersionGapFromStable"; Descending = $true }, @{ Expression = "OutOfDateTerminalCount"; Descending = $true }, StoreName
    )
    $biggestVersionDriftStores = @(
        $storeVersionReport |
        Where-Object { $_.VersionDriftCount -gt 0 } |
        Sort-Object @{ Expression = "VersionDriftCount"; Descending = $true }, @{ Expression = "UniqueVersionCount"; Descending = $true }, @{ Expression = "OutOfDateTerminalCount"; Descending = $true }, StoreName
    )
    $comparison = Get-CsvComparisonData -CurrentRows $rowsWithVersions -PreviousCsvPath $PreviousCsvPath
    return [pscustomobject]@{
        SourceCsv               = (Resolve-Path $CsvPath).Path
        GeneratedOn             = Get-Date
        TotalTerminals          = $rows.Count
        RowsWithVersions        = $rowsWithVersions.Count
        UniqueVersionCount      = @($rowsWithVersions."Current Version" | Sort-Object -Unique).Count
        UniqueQuPosAppVersionCount = $downloadableVersions.Count
        DownloadableRowCount    = $downloadableRows.Count
        NonDownloadableRowCount = $otherRows.Count
        MostCurrentVersion      = $mostCurrentVersion
        CurrentStableVersion    = $currentStableVersion
        CurrentStableVersionCount = $currentStableVersionCount
        MostUsedVersion         = $currentStableVersion
        MostUsedVersionCount    = $currentStableVersionCount
        OutOfDateStoreCount     = $outOfDateStoreCount
        OutOfDateTerminalCount  = $outOfDateTerminalRows.Count
        CurrentStoreCount       = (@($storeGroups).Count - $outOfDateStoreCount)
        StoreVersionReport      = $storeVersionReport
        DownloadableVersions    = $downloadableVersions
        KioskVersions           = $kioskVersions
        QuBoxVersions           = $quBoxVersions
        OutOfDateVersionSummary = $outOfDateVersionSummary
        OutOfDateStores         = $outOfDateStores
        AlertSettings           = [pscustomobject]@{
            StaleThresholdDays     = $staleThresholdDays
            FarBehindGapThreshold  = $farBehindGapThreshold
            MostCurrentVersion     = $mostCurrentVersion
            CurrentStableVersion   = $currentStableVersion
        }
        MixedVersionStores      = $mixedVersionStores
        StaleTerminals          = $staleTerminals
        FarBehindStores         = $farBehindStores
        BiggestVersionDriftStores = $biggestVersionDriftStores
        OtherVersions           = $otherVersions
        Comparison              = $comparison
    }
}

function New-ScanHtmlReport {
    param([hashtable]$AllResults,[string[]]$ReleaseTrains,[string]$LogoDataUri)
    $builder = [System.Text.StringBuilder]::new()
    [void]$builder.AppendLine('<html><head><title>QU POS Builds</title><style>body{font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;margin:32px}.page-header{display:flex;align-items:center;gap:18px;margin-bottom:18px}.page-header img{width:92px;height:auto;display:block}h1{color:#38bdf8;margin:0 0 6px}h2{color:#facc15;margin-top:32px}.latest{color:#22c55e;font-weight:bold}table{border-collapse:collapse;width:100%;margin-bottom:30px}th,td{padding:10px;border-bottom:1px solid #334155;text-align:left}th{color:#93c5fd}a{color:#22c55e}p{color:#cbd5e1;margin:0}.subhead{margin-top:6px}</style></head><body>')
    [void]$builder.AppendLine("<div class='page-header'>")
    if ($LogoDataUri) {
        [void]$builder.AppendLine("<img src='$(ConvertTo-HtmlText $LogoDataUri)' alt='GoTo Foods logo' />")
    }
    [void]$builder.AppendLine("<div><h1>QU POS Build Index</h1><p class='subhead'>Generated on $(ConvertTo-HtmlText (Get-Date))</p><p class='subhead'>Tool Version: $(ConvertTo-HtmlText $ToolVersion)</p></div></div>")
    foreach ($train in $ReleaseTrains | Where-Object { $AllResults.ContainsKey($_) }) {
        $trainResults = @($AllResults[$train]); if ($trainResults.Count -eq 0) { continue }
        $latest = $trainResults | Sort-Object { [version]$_.Version } -Descending | Select-Object -First 1
        [void]$builder.AppendLine("<h2>$(ConvertTo-HtmlText $train)</h2><p class='latest'>Latest: $(ConvertTo-HtmlText $latest.Version)</p><table><tr><th>Version</th><th>Download</th></tr>")
        foreach ($item in $trainResults) {
            $rowClass = if ($item.Version -eq $latest.Version) { " class='latest'" } else { "" }
            [void]$builder.AppendLine("<tr$rowClass><td>$(ConvertTo-HtmlText $item.Version)</td><td><a href='$(ConvertTo-HtmlText $item.Url)' target='_blank'>Download</a></td></tr>")
        }
        [void]$builder.AppendLine("</table>")
    }
    [void]$builder.AppendLine("</body></html>")
    return $builder.ToString()
}

function New-CsvHtmlReport {
    param([pscustomobject]$Report,[string]$LogoDataUri)
    $comparison = $Report.Comparison
    $hasComparison = ($null -ne $comparison)
    $currentStableVersion = [string]$Report.CurrentStableVersion
    $mostCurrentVersion = [string]$Report.MostCurrentVersion
    $searchScript = @'
<script>
(function () {
    const input = document.getElementById('reportSearch');
    function compareVersions(a, b) {
        const left = String(a || '').split(/[^0-9]+/).filter(Boolean).map(Number);
        const right = String(b || '').split(/[^0-9]+/).filter(Boolean).map(Number);
        const max = Math.max(left.length, right.length);
        for (let i = 0; i < max; i += 1) {
            const diff = (left[i] || 0) - (right[i] || 0);
            if (diff !== 0) { return diff; }
        }
        return String(a || '').localeCompare(String(b || ''), undefined, { numeric: true, sensitivity: 'base' });
    }
    function compareValues(a, b, type) {
        if (type === 'number') { return (parseFloat(a) || 0) - (parseFloat(b) || 0); }
        if (type === 'version') { return compareVersions(a, b); }
        return String(a || '').localeCompare(String(b || ''), undefined, { numeric: true, sensitivity: 'base' });
    }
    function normalizeSearchText(value) {
        const lower = String(value || '').toLowerCase();
        const normalized = typeof lower.normalize === 'function' ? lower.normalize('NFD') : lower;
        return normalized
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[ΓÇÖ']/g, '')
            .replace(/[^a-z0-9]+/g, ' ')
            .trim();
    }
    function applySearchToContainer(container, query) {
        const normalizedQuery = normalizeSearchText(query);
        if (!container) { return; }
        document.querySelectorAll('table.report-table').forEach((table) => {
            if (!container.contains(table)) { return; }
            Array.from(table.tBodies).forEach((tbody) => {
                Array.from(tbody.rows).forEach((row) => {
                    const haystack = normalizeSearchText(row.dataset.search || row.innerText || '');
                    row.style.display = (!normalizedQuery || haystack.includes(normalizedQuery)) ? '' : 'none';
                });
            });
        });
        container.querySelectorAll('details.report-detail').forEach((detail) => {
            const detailText = normalizeSearchText(detail.dataset.search || detail.querySelector('summary')?.innerText || '');
            const hasVisibleRows = Array.from(detail.querySelectorAll('tbody tr')).some((row) => row.style.display !== 'none');
            const match = !normalizedQuery || detailText.includes(normalizedQuery) || hasVisibleRows;
            detail.style.display = match ? '' : 'none';
            if (query && hasVisibleRows) { detail.open = true; }
        });
    }
    document.querySelectorAll('table.report-table').forEach((table) => {
        table.querySelectorAll('th[data-sort]').forEach((header, index) => {
            header.addEventListener('click', () => {
                const tbody = table.tBodies[0];
                if (!tbody) { return; }
                const direction = header.dataset.direction === 'asc' ? 'desc' : 'asc';
                const sortType = header.dataset.sort || 'string';
                const rows = Array.from(tbody.rows);
                table.querySelectorAll('th[data-sort]').forEach((th) => {
                    if (th !== header) {
                        th.dataset.direction = '';
                        th.classList.remove('sort-asc', 'sort-desc');
                    }
                });
                header.dataset.direction = direction;
                header.classList.toggle('sort-asc', direction === 'asc');
                header.classList.toggle('sort-desc', direction === 'desc');
                rows.sort((rowA, rowB) => {
                    const cellA = rowA.cells[index];
                    const cellB = rowB.cells[index];
                    const valueA = (cellA?.dataset.sortValue || cellA?.innerText || '').trim();
                    const valueB = (cellB?.dataset.sortValue || cellB?.innerText || '').trim();
                    const comparison = compareValues(valueA, valueB, sortType);
                    return direction === 'asc' ? comparison : comparison * -1;
                });
                rows.forEach((row) => tbody.appendChild(row));
                const panel = header.closest('.tab-panel') || document.body;
                const searchInput = panel.querySelector('.report-search');
                const query = searchInput?.value || '';
                applySearchToContainer(panel, query);
            });
        });
    });
    document.querySelectorAll('.report-search').forEach((searchInput) => {
        const targetId = searchInput.dataset.target;
        const container = document.getElementById(targetId);
        const apply = () => applySearchToContainer(container, searchInput.value || '');
        searchInput.addEventListener('input', apply);
        apply();
    });
    const tabButtons = Array.from(document.querySelectorAll('.tab-btn'));
    const tabPanels = Array.from(document.querySelectorAll('.tab-panel'));
    function activateTab(name) {
        tabButtons.forEach((button) => {
            const isActive = button.dataset.tab === name;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        tabPanels.forEach((panel) => {
            panel.classList.toggle('active', panel.id === name);
        });
    }
    tabButtons.forEach((button) => {
        button.addEventListener('click', () => activateTab(button.dataset.tab));
    });
    if (tabButtons.length > 0) {
        activateTab(tabButtons[0].dataset.tab);
    }
    const modal = document.getElementById('storeDetailModal');
    const modalTitle = document.getElementById('storeDetailTitle');
    const modalBody = document.getElementById('storeDetailBody');
    function closeStoreDetail() {
        if (!modal) { return; }
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        if (modalBody) { modalBody.innerHTML = ''; }
    }
    function openStoreDetail(targetId, label) {
        if (!modal || !modalBody || !modalTitle || !targetId) { return; }
        const template = document.getElementById(targetId);
        if (!template) { return; }
        modalTitle.textContent = label || 'Store Details';
        modalBody.innerHTML = template.innerHTML;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        const closeButton = modal.querySelector('[data-store-detail-close]');
        if (closeButton) { closeButton.focus(); }
    }
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-store-detail-target]');
        if (trigger) {
            event.preventDefault();
            openStoreDetail(trigger.dataset.storeDetailTarget, trigger.dataset.storeDetailLabel || trigger.innerText.trim());
            return;
        }
        if (!modal) { return; }
        if (event.target === modal || event.target.closest('[data-store-detail-close]')) {
            closeStoreDetail();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeStoreDetail();
        }
    });
})();
</script>
'@
    $builder = [System.Text.StringBuilder]::new()
    $comparisonTabButton = if ($hasComparison) { "<button type='button' class='tab-btn' data-tab='comparison-tab' aria-selected='false'>Comparison</button>" } else { "" }
    [void]$builder.AppendLine('<html><head><title>QU POS Current Versions</title><style>body{font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;margin:32px;line-height:1.45}.page-header{display:flex;align-items:center;gap:18px;margin-bottom:18px}.page-header img{width:98px;height:auto;display:block}.title-block p{margin:2px 0 0;color:#cbd5e1}h1{color:#38bdf8;margin:0 0 8px}h2{color:#facc15;margin-top:36px}p{color:#cbd5e1}.summary{display:flex;flex-wrap:wrap;gap:16px;margin:24px 0}.card{background:#111827;border:1px solid #334155;border-radius:10px;padding:16px 18px;min-width:180px;flex:1 1 180px}.label{display:block;color:#93c5fd;font-size:13px;text-transform:uppercase;letter-spacing:.06em}.value{display:block;color:#f8fafc;font-size:28px;font-weight:bold;margin-top:4px}.meta{display:block;color:#94a3b8;font-size:12px;margin-top:6px}.toolbar{display:flex;flex-wrap:wrap;gap:16px;align-items:end;margin:8px 0 24px}.toolbar label{display:block;color:#93c5fd;font-size:13px;margin-bottom:6px}.toolbar input{width:340px;max-width:100%;padding:12px 14px;border-radius:10px;border:1px solid #334155;background:#111827;color:#f8fafc}.toolbar-note{color:#94a3b8;font-size:13px;padding-bottom:12px}.tabbar{display:flex;gap:12px;align-items:center;margin:8px 0 24px;padding-bottom:12px;border-bottom:1px solid #334155}.tab-btn{background:#111827;border:1px solid #334155;color:#cbd5e1;padding:10px 16px;border-radius:999px;cursor:pointer;font-weight:bold}.tab-btn.active{background:#082f49;border-color:#38bdf8;color:#f8fafc}.tab-panel{display:none}.tab-panel.active{display:block}.report-table{border-collapse:collapse;width:100%;margin:18px 0 28px;background:#111827}.report-table th,.report-table td{padding:10px;border-bottom:1px solid #334155;text-align:left;vertical-align:top}.report-table th{color:#93c5fd}.report-table tbody tr:hover td{background:#172033}th[data-sort]{cursor:pointer;padding-right:20px;position:relative}th.sort-asc::after{content:"â–²";position:absolute;right:8px;color:#38bdf8;font-size:10px}th.sort-desc::after{content:"â–¼";position:absolute;right:8px;color:#38bdf8;font-size:10px}a{color:#22c55e}details.report-detail{margin:14px 0;border:1px solid #334155;border-radius:10px;background:#111827;overflow:hidden}summary{cursor:pointer;padding:14px 16px;font-weight:bold;color:#f8fafc}summary span{color:#94a3b8;font-weight:normal;margin-left:10px}.detail-body{padding:0 16px 16px}.note{background:#111827;border-left:4px solid #38bdf8;padding:12px 14px;margin:12px 0 24px}.badge-legend{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 24px}.legend-label{color:#94a3b8;font-size:13px;font-weight:bold;text-transform:uppercase;letter-spacing:.06em}.version-badge{display:inline-flex;align-items:center;justify-content:center;padding:4px 10px;border-radius:999px;border:1px solid #334155;background:#0b1220;color:#e2e8f0;font-weight:bold;font-size:12px;line-height:1.4;white-space:nowrap;margin:2px 6px 2px 0}.version-badge.stable{background:#052e16;border-color:#16a34a;color:#dcfce7}.version-badge.current{background:#082f49;border-color:#38bdf8;color:#e0f2fe}.version-badge.higher{background:#3f2d09;border-color:#f59e0b;color:#fde68a}.version-badge.outdated{background:#3f1015;border-color:#f87171;color:#fecaca}.version-badge.neutral{background:#1e293b;border-color:#475569;color:#e2e8f0}.store-link{background:none;border:none;color:#7dd3fc;padding:0;cursor:pointer;font:inherit;font-weight:600;text-decoration:underline;box-shadow:none;border-radius:0}.store-link:hover{color:#bae6fd;background:none}.store-link:focus-visible{outline:2px solid #38bdf8;outline-offset:2px}.modal-overlay{position:fixed;inset:0;background:rgba(2,6,23,.78);display:none;align-items:center;justify-content:center;padding:24px;z-index:9999}.modal-overlay.open{display:flex}.modal-panel{width:min(1080px,100%);max-height:88vh;overflow:auto;background:#0b1220;border:1px solid #334155;border-radius:18px;box-shadow:0 24px 80px rgba(2,6,23,.55)}.modal-header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding:22px 24px 14px;border-bottom:1px solid #1e293b;position:sticky;top:0;background:#0b1220;z-index:2}.modal-kicker{color:#93c5fd;font-size:12px;text-transform:uppercase;letter-spacing:.08em;font-weight:bold;margin-bottom:6px}.modal-header h2{margin:0;color:#f8fafc}.modal-close{background:#111827;border:1px solid #334155;color:#f8fafc;padding:10px 16px;border-radius:999px;cursor:pointer;font-weight:bold}.modal-close:hover{background:#172033}.modal-body{padding:20px 24px 28px}.detail-summary{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px}.detail-card{background:#111827;border:1px solid #334155;border-radius:12px;padding:14px 16px;flex:1 1 180px;min-width:180px}.detail-card-wide{flex-basis:280px}.value-small{display:block;color:#f8fafc;font-weight:bold;margin-top:6px}.version-line .version-badge{margin-top:6px}.detail-table{width:100%;border-collapse:collapse;background:#111827;border:1px solid #334155;border-radius:12px;overflow:hidden}.detail-table th,.detail-table td{padding:10px;border-bottom:1px solid #334155;text-align:left;vertical-align:top}.detail-table th{color:#93c5fd;background:#111827}.terminal-ref{color:#94a3b8;font-size:12px;margin-top:4px}.store-detail-templates{display:none}</style></head><body>')
    [void]$builder.AppendLine("<div class='page-header'>")
    if ($LogoDataUri) { [void]$builder.AppendLine("<img src='$(ConvertTo-HtmlText $LogoDataUri)' alt='GoTo Foods logo' />") }
    [void]$builder.AppendLine("<div class='title-block'><h1>QU POS Current Versions</h1><p>Generated on $(ConvertTo-HtmlText $Report.GeneratedOn)</p><p>Tool Version: $(ConvertTo-HtmlText $ToolVersion)</p><p>Source CSV: $(ConvertTo-HtmlText $Report.SourceCsv)</p></div></div>")
    [void]$builder.AppendLine("<div class='tabbar'><button type='button' class='tab-btn active' data-tab='current-tab' aria-selected='true'>Current Versions</button><button type='button' class='tab-btn' data-tab='stores-tab' aria-selected='false'>Stores Version Report</button><button type='button' class='tab-btn' data-tab='alerts-tab' aria-selected='false'>Alerts</button>$comparisonTabButton</div>")
    [void]$builder.AppendLine("<div class='badge-legend'><span class='legend-label'>Version Colors</span><span class='version-badge stable'>Stable</span><span class='version-badge current'>Most Current</span><span class='version-badge higher'>Above Stable</span><span class='version-badge outdated'>Out-Of-Date</span></div>")
    [void]$builder.AppendLine("<div id='current-tab' class='tab-panel active'>")
    [void]$builder.AppendLine("<div class='toolbar'><div><label for='currentReportSearch'>Search The Report</label><input id='currentReportSearch' class='report-search' data-target='current-tab' type='text' placeholder='Search brands, versions, stores, terminals, and current rollout data' /></div><div class='toolbar-note'>Click any table header to sort.</div></div>")
    [void]$builder.AppendLine("<div class='summary'><div class='card'><span class='label'>Unique Qu POS APP Versions</span><span class='value'>$(ConvertTo-HtmlText $Report.UniqueQuPosAppVersionCount)</span></div><div class='card'><span class='label'>POS App Terminals</span><span class='value'>$(ConvertTo-HtmlText $Report.DownloadableRowCount)</span></div><div class='card'><span class='label'>Most Current Version</span><span class='value'>$(ConvertTo-HtmlText $Report.MostCurrentVersion)</span></div><div class='card'><span class='label'>Current Stable Version</span><span class='value'>$(ConvertTo-HtmlText $Report.CurrentStableVersion)</span><span class='meta'>$(ConvertTo-HtmlText "$($Report.CurrentStableVersionCount) terminals")</span></div><div class='card'><span class='label'>Out-Of-Date Stores</span><span class='value'>$(ConvertTo-HtmlText $Report.OutOfDateStoreCount)</span></div><div class='card'><span class='label'>Out-Of-Date POS Terminals</span><span class='value'>$(ConvertTo-HtmlText $Report.OutOfDateTerminalCount)</span></div></div>")
    [void]$builder.AppendLine("<h2>Downloadable QU POS Versions</h2><div class='note'>This section includes versions that match the standard <code>Qu.POS_x.x.x.x.zip</code> download pattern.</div>")
    [void]$builder.AppendLine((Get-HtmlTableStart -Id "downloadable-versions" -Columns @([pscustomobject]@{ Label = "Version"; Sort = "version" },[pscustomobject]@{ Label = "Release Train"; Sort = "version" },[pscustomobject]@{ Label = "Terminals"; Sort = "number" },[pscustomobject]@{ Label = "Stores"; Sort = "number" },[pscustomobject]@{ Label = "Types"; Sort = "string" },[pscustomobject]@{ Label = "Download"; Sort = "string" })))
    foreach ($versionInfo in $Report.DownloadableVersions) {
        $rowSearch = Get-HtmlSearchText @($versionInfo.Version, $versionInfo.ReleaseTrain, $versionInfo.TerminalCount, $versionInfo.StoreCount, $versionInfo.TerminalTypes, $versionInfo.Url)
        [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
        [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeHtml -Version $versionInfo.Version -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $versionInfo.Version))
        [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $versionInfo.ReleaseTrain) -SortValue $versionInfo.ReleaseTrain))
        [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $versionInfo.TerminalCount) -SortValue $versionInfo.TerminalCount))
        [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $versionInfo.StoreCount) -SortValue $versionInfo.StoreCount))
        [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $versionInfo.TerminalTypes) -SortValue $versionInfo.TerminalTypes))
        [void]$builder.AppendLine((Get-HtmlCell -Html "<a href='$(ConvertTo-HtmlText $versionInfo.Url)' target='_blank'>Download</a>" -SortValue $versionInfo.Url))
        [void]$builder.AppendLine("</tr>")
    }
    [void]$builder.AppendLine((Get-HtmlTableEnd))
    foreach ($versionInfo in $Report.DownloadableVersions) {
        $detailSearch = Get-HtmlSearchText @($versionInfo.Version, $versionInfo.ReleaseTrain, $versionInfo.TerminalCount, $versionInfo.StoreCount, $versionInfo.TerminalTypes, (@($versionInfo.StoreRows | ForEach-Object { Get-StoreBrandSearchText -StoreName $_.StoreName } | Sort-Object -Unique) -join ' '))
        [void]$builder.AppendLine("<details class='report-detail' data-search='$detailSearch'><summary>$(ConvertTo-HtmlText $versionInfo.Version)<span>$(ConvertTo-HtmlText "$($versionInfo.TerminalCount) terminals across $($versionInfo.StoreCount) stores")</span></summary><div class='detail-body'><p><a href='$(ConvertTo-HtmlText $versionInfo.Url)' target='_blank'>$(ConvertTo-HtmlText $versionInfo.Url)</a></p>")
        [void]$builder.AppendLine((Get-HtmlTableStart -Id "stores-$($versionInfo.Version.Replace('.', '-'))" -Columns @([pscustomobject]@{ Label = "Store ID"; Sort = "number" },[pscustomobject]@{ Label = "Store Name"; Sort = "string" },[pscustomobject]@{ Label = "Terminals"; Sort = "number" },[pscustomobject]@{ Label = "Types"; Sort = "string" },[pscustomobject]@{ Label = "Latest Seen"; Sort = "string" })))
        foreach ($storeInfo in $versionInfo.StoreRows) {
            $rowSearch = Get-HtmlSearchText @($storeInfo.StoreId, $storeInfo.StoreName, (Get-StoreBrandSearchText -StoreName $storeInfo.StoreName), $storeInfo.TerminalCount, $storeInfo.TerminalTypes, $storeInfo.LatestSeen, $versionInfo.Version)
            [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.StoreId) -SortValue $storeInfo.StoreId))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.StoreName) -SortValue $storeInfo.StoreName))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.TerminalCount) -SortValue $storeInfo.TerminalCount))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.TerminalTypes) -SortValue $storeInfo.TerminalTypes))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.LatestSeen) -SortValue $storeInfo.LatestSeen))
            [void]$builder.AppendLine("</tr>")
        }
        [void]$builder.AppendLine((Get-HtmlTableEnd))
        [void]$builder.AppendLine("</div></details>")
    }
    [void]$builder.AppendLine("<h2>Out-Of-Date Stores</h2>")
    if ($Report.OutOfDateStoreCount -gt 0) {
        [void]$builder.AppendLine("<div class='note'>The current stable version is <strong>$(ConvertTo-HtmlText $Report.CurrentStableVersion)</strong>. Any QU POS app version below that is counted as out-of-date.</div>")
        [void]$builder.AppendLine((Get-HtmlTableStart -Id "out-of-date-versions" -Columns @([pscustomobject]@{ Label = "Version"; Sort = "version" },[pscustomobject]@{ Label = "Out-Of-Date Terminals"; Sort = "number" },[pscustomobject]@{ Label = "Stores"; Sort = "number" })))
        foreach ($item in $Report.OutOfDateVersionSummary) {
            $rowSearch = Get-HtmlSearchText @($item.Version, $item.TerminalCount, $item.StoreCount, $Report.CurrentStableVersion)
            [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeHtml -Version $item.Version -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $item.Version))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.TerminalCount) -SortValue $item.TerminalCount))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.StoreCount) -SortValue $item.StoreCount))
            [void]$builder.AppendLine("</tr>")
        }
        [void]$builder.AppendLine((Get-HtmlTableEnd))
    } else {
        [void]$builder.AppendLine("<div class='note'>All QU POS app stores in this CSV are on or above the current stable version.</div>")
    }
    if ($Report.KioskVersions.Count -gt 0) {
        [void]$builder.AppendLine("<h2>Downloadable Kiosk Versions</h2><div class='note'>This section includes Kiosk versions that match the <code>Kiosk-Setup-version.exe</code> download pattern.</div>")
        [void]$builder.AppendLine((Get-HtmlTableStart -Id "downloadable-kiosk-versions" -Columns @([pscustomobject]@{ Label = "Version"; Sort = "string" },[pscustomobject]@{ Label = "Terminals"; Sort = "number" },[pscustomobject]@{ Label = "Stores"; Sort = "number" },[pscustomobject]@{ Label = "Types"; Sort = "string" },[pscustomobject]@{ Label = "Download"; Sort = "string" })))
        foreach ($versionInfo in $Report.KioskVersions) {
            $rowSearch = Get-HtmlSearchText @($versionInfo.Version, $versionInfo.TerminalCount, $versionInfo.StoreCount, $versionInfo.TerminalTypes, $versionInfo.Url)
            [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeHtml -Version $versionInfo.Version -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $versionInfo.Version))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $versionInfo.TerminalCount) -SortValue $versionInfo.TerminalCount))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $versionInfo.StoreCount) -SortValue $versionInfo.StoreCount))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $versionInfo.TerminalTypes) -SortValue $versionInfo.TerminalTypes))
            $downloadHtml = if ([string]::IsNullOrWhiteSpace($versionInfo.Url)) { "" } else { "<a href='$(ConvertTo-HtmlText $versionInfo.Url)' target='_blank'>Download</a>" }
            [void]$builder.AppendLine((Get-HtmlCell -Html $downloadHtml -SortValue $versionInfo.Url))
            [void]$builder.AppendLine("</tr>")
        }
        [void]$builder.AppendLine((Get-HtmlTableEnd))
        foreach ($versionInfo in $Report.KioskVersions) {
            $detailSearch = Get-HtmlSearchText @($versionInfo.Version, $versionInfo.TerminalCount, $versionInfo.StoreCount, $versionInfo.TerminalTypes, (@($versionInfo.StoreRows | ForEach-Object { Get-StoreBrandSearchText -StoreName $_.StoreName } | Sort-Object -Unique) -join ' '))
            [void]$builder.AppendLine("<details class='report-detail' data-search='$detailSearch'><summary>$(ConvertTo-HtmlText $versionInfo.Version)<span>$(ConvertTo-HtmlText "$($versionInfo.TerminalCount) terminals across $($versionInfo.StoreCount) stores")</span></summary><div class='detail-body'>")
            if (-not [string]::IsNullOrWhiteSpace($versionInfo.Url)) {
                [void]$builder.AppendLine("<p><a href='$(ConvertTo-HtmlText $versionInfo.Url)' target='_blank'>$(ConvertTo-HtmlText $versionInfo.Url)</a></p>")
            }
            [void]$builder.AppendLine((Get-HtmlTableStart -Id "kiosk-stores-$($versionInfo.Version.Replace('.', '-').Replace('-', '-'))" -Columns @([pscustomobject]@{ Label = "Store ID"; Sort = "number" },[pscustomobject]@{ Label = "Store Name"; Sort = "string" },[pscustomobject]@{ Label = "Terminals"; Sort = "number" },[pscustomobject]@{ Label = "Types"; Sort = "string" },[pscustomobject]@{ Label = "Latest Seen"; Sort = "string" })))
            foreach ($storeInfo in $versionInfo.StoreRows) {
                $rowSearch = Get-HtmlSearchText @($storeInfo.StoreId, $storeInfo.StoreName, (Get-StoreBrandSearchText -StoreName $storeInfo.StoreName), $storeInfo.TerminalCount, $storeInfo.TerminalTypes, $storeInfo.LatestSeen, $versionInfo.Version)
                [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.StoreId) -SortValue $storeInfo.StoreId))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.StoreName) -SortValue $storeInfo.StoreName))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.TerminalCount) -SortValue $storeInfo.TerminalCount))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.TerminalTypes) -SortValue $storeInfo.TerminalTypes))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.LatestSeen) -SortValue $storeInfo.LatestSeen))
                [void]$builder.AppendLine("</tr>")
            }
            [void]$builder.AppendLine((Get-HtmlTableEnd))
            [void]$builder.AppendLine("</div></details>")
        }
    }
    if ($Report.QuBoxVersions.Count -gt 0) {
        [void]$builder.AppendLine("<h2>QuBox Versions</h2><div class='note'>This section lists QuBox versions found in the CSV. The exact QuBox download URL pattern is not confirmed yet, so direct download links are left blank.</div>")
        [void]$builder.AppendLine((Get-HtmlTableStart -Id "qubox-versions" -Columns @([pscustomobject]@{ Label = "Version"; Sort = "string" },[pscustomobject]@{ Label = "Terminals"; Sort = "number" },[pscustomobject]@{ Label = "Stores"; Sort = "number" },[pscustomobject]@{ Label = "Types"; Sort = "string" },[pscustomobject]@{ Label = "Download"; Sort = "string" })))
        foreach ($versionInfo in $Report.QuBoxVersions) {
            $rowSearch = Get-HtmlSearchText @($versionInfo.Version, $versionInfo.TerminalCount, $versionInfo.StoreCount, $versionInfo.TerminalTypes)
            [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeHtml -Version $versionInfo.Version -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $versionInfo.Version))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $versionInfo.TerminalCount) -SortValue $versionInfo.TerminalCount))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $versionInfo.StoreCount) -SortValue $versionInfo.StoreCount))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $versionInfo.TerminalTypes) -SortValue $versionInfo.TerminalTypes))
            [void]$builder.AppendLine((Get-HtmlCell -Html "" -SortValue ""))
            [void]$builder.AppendLine("</tr>")
        }
        [void]$builder.AppendLine((Get-HtmlTableEnd))
        foreach ($versionInfo in $Report.QuBoxVersions) {
            $detailSearch = Get-HtmlSearchText @($versionInfo.Version, $versionInfo.TerminalCount, $versionInfo.StoreCount, $versionInfo.TerminalTypes, (@($versionInfo.StoreRows | ForEach-Object { Get-StoreBrandSearchText -StoreName $_.StoreName } | Sort-Object -Unique) -join ' '))
            [void]$builder.AppendLine("<details class='report-detail' data-search='$detailSearch'><summary>$(ConvertTo-HtmlText $versionInfo.Version)<span>$(ConvertTo-HtmlText "$($versionInfo.TerminalCount) terminals across $($versionInfo.StoreCount) stores")</span></summary><div class='detail-body'>")
            [void]$builder.AppendLine((Get-HtmlTableStart -Id "qubox-stores-$($versionInfo.Version.Replace('.', '-').Replace('-', '-'))" -Columns @([pscustomobject]@{ Label = "Store ID"; Sort = "number" },[pscustomobject]@{ Label = "Store Name"; Sort = "string" },[pscustomobject]@{ Label = "Terminals"; Sort = "number" },[pscustomobject]@{ Label = "Types"; Sort = "string" },[pscustomobject]@{ Label = "Latest Seen"; Sort = "string" })))
            foreach ($storeInfo in $versionInfo.StoreRows) {
                $rowSearch = Get-HtmlSearchText @($storeInfo.StoreId, $storeInfo.StoreName, (Get-StoreBrandSearchText -StoreName $storeInfo.StoreName), $storeInfo.TerminalCount, $storeInfo.TerminalTypes, $storeInfo.LatestSeen, $versionInfo.Version)
                [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.StoreId) -SortValue $storeInfo.StoreId))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.StoreName) -SortValue $storeInfo.StoreName))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.TerminalCount) -SortValue $storeInfo.TerminalCount))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.TerminalTypes) -SortValue $storeInfo.TerminalTypes))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.LatestSeen) -SortValue $storeInfo.LatestSeen))
                [void]$builder.AppendLine("</tr>")
            }
            [void]$builder.AppendLine((Get-HtmlTableEnd))
            [void]$builder.AppendLine("</div></details>")
        }
    }
    if ($Report.OtherVersions.Count -gt 0) {
        [void]$builder.AppendLine("<h2>Other Terminal Versions</h2><div class='note'>These versions came from the CSV but do not match the QU POS, Kiosk, or QuBox report sections, so no direct ZIP/EXE link is shown.</div>")
        [void]$builder.AppendLine((Get-HtmlTableStart -Id "other-terminal-versions" -Columns @([pscustomobject]@{ Label = "Version"; Sort = "string" },[pscustomobject]@{ Label = "Terminals"; Sort = "number" },[pscustomobject]@{ Label = "Stores"; Sort = "number" },[pscustomobject]@{ Label = "Types"; Sort = "string" },[pscustomobject]@{ Label = "Download"; Sort = "string" })))
        foreach ($versionInfo in $Report.OtherVersions) {
            $rowSearch = Get-HtmlSearchText @($versionInfo.Version, $versionInfo.TerminalCount, $versionInfo.StoreCount, $versionInfo.TerminalTypes, $versionInfo.Url)
            [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeHtml -Version $versionInfo.Version -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $versionInfo.Version))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $versionInfo.TerminalCount) -SortValue $versionInfo.TerminalCount))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $versionInfo.StoreCount) -SortValue $versionInfo.StoreCount))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $versionInfo.TerminalTypes) -SortValue $versionInfo.TerminalTypes))
            $downloadHtml = if ([string]::IsNullOrWhiteSpace($versionInfo.Url)) { "" } else { "<a href='$(ConvertTo-HtmlText $versionInfo.Url)' target='_blank'>Download</a>" }
            [void]$builder.AppendLine((Get-HtmlCell -Html $downloadHtml -SortValue $versionInfo.Url))
            [void]$builder.AppendLine("</tr>")
        }
        [void]$builder.AppendLine((Get-HtmlTableEnd))
        foreach ($versionInfo in $Report.OtherVersions) {
            $detailSearch = Get-HtmlSearchText @($versionInfo.Version, $versionInfo.TerminalCount, $versionInfo.StoreCount, $versionInfo.TerminalTypes, (@($versionInfo.StoreRows | ForEach-Object { Get-StoreBrandSearchText -StoreName $_.StoreName } | Sort-Object -Unique) -join ' '))
            [void]$builder.AppendLine("<details class='report-detail' data-search='$detailSearch'><summary>$(ConvertTo-HtmlText $versionInfo.Version)<span>$(ConvertTo-HtmlText "$($versionInfo.TerminalCount) terminals across $($versionInfo.StoreCount) stores")</span></summary><div class='detail-body'>")
            [void]$builder.AppendLine((Get-HtmlTableStart -Id "other-stores-$($versionInfo.Version.Replace('.', '-').Replace('-', '-'))" -Columns @([pscustomobject]@{ Label = "Store ID"; Sort = "number" },[pscustomobject]@{ Label = "Store Name"; Sort = "string" },[pscustomobject]@{ Label = "Terminals"; Sort = "number" },[pscustomobject]@{ Label = "Types"; Sort = "string" },[pscustomobject]@{ Label = "Latest Seen"; Sort = "string" })))
            foreach ($storeInfo in $versionInfo.StoreRows) {
                $rowSearch = Get-HtmlSearchText @($storeInfo.StoreId, $storeInfo.StoreName, (Get-StoreBrandSearchText -StoreName $storeInfo.StoreName), $storeInfo.TerminalCount, $storeInfo.TerminalTypes, $storeInfo.LatestSeen, $versionInfo.Version)
                [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.StoreId) -SortValue $storeInfo.StoreId))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.StoreName) -SortValue $storeInfo.StoreName))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.TerminalCount) -SortValue $storeInfo.TerminalCount))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.TerminalTypes) -SortValue $storeInfo.TerminalTypes))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $storeInfo.LatestSeen) -SortValue $storeInfo.LatestSeen))
                [void]$builder.AppendLine("</tr>")
            }
            [void]$builder.AppendLine((Get-HtmlTableEnd))
            [void]$builder.AppendLine("</div></details>")
        }
    }
    [void]$builder.AppendLine("</div>")
    [void]$builder.AppendLine("<div id='stores-tab' class='tab-panel'>")
    [void]$builder.AppendLine("<div class='toolbar'><div><label for='storesReportSearch'>Search Stores Version Report</label><input id='storesReportSearch' class='report-search' data-target='stores-tab' type='text' placeholder='Search brands, store IDs, store names, versions, and latest seen times' /></div><div class='toolbar-note'>Click any table header to sort.</div></div>")
    [void]$builder.AppendLine("<h2>Stores Version Report</h2>")
    [void]$builder.AppendLine("<div class='note'>This tab shows one row per store across QU POS app terminals in the current CSV. Click a store name to open the terminal drill-down.</div>")
    if ($Report.StoreVersionReport.Count -gt 0) {
        [void]$builder.AppendLine((Get-HtmlTableStart -Id "stores-version-report" -Columns @([pscustomobject]@{ Label = "Store ID"; Sort = "number" },[pscustomobject]@{ Label = "Store Name"; Sort = "string" },[pscustomobject]@{ Label = "Versions Detected"; Sort = "string" },[pscustomobject]@{ Label = "Most Common Version"; Sort = "version" },[pscustomobject]@{ Label = "Out-Of-Date Terminals"; Sort = "number" },[pscustomobject]@{ Label = "Total POS Terminals"; Sort = "number" },[pscustomobject]@{ Label = "Latest Seen"; Sort = "string" })))
        foreach ($store in $Report.StoreVersionReport) {
            $rowSearch = Get-HtmlSearchText @($store.StoreId, $store.StoreName, (Get-StoreBrandSearchText -StoreName $store.StoreName), $store.VersionsDetected, $store.MostCommonVersion, $store.OutOfDateTerminalCount, $store.TotalPosTerminals, $store.LatestSeen)
            [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.StoreId) -SortValue $store.StoreId))
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-StoreButtonHtml -StoreId $store.StoreId -StoreName $store.StoreName -DetailKey $store.StoreDetailKey) -SortValue $store.StoreName))
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeListHtml -Versions @($store.VersionsDetectedList) -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $store.VersionsDetected))
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeHtml -Version $store.MostCommonVersion -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $store.MostCommonVersion))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.OutOfDateTerminalCount) -SortValue $store.OutOfDateTerminalCount))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.TotalPosTerminals) -SortValue $store.TotalPosTerminals))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.LatestSeen) -SortValue $store.LatestSeen))
            [void]$builder.AppendLine("</tr>")
        }
        [void]$builder.AppendLine((Get-HtmlTableEnd))
    } else {
        [void]$builder.AppendLine("<div class='note'>No QU POS app store rows were found in this CSV.</div>")
    }
    [void]$builder.AppendLine("</div>")
    [void]$builder.AppendLine("<div id='alerts-tab' class='tab-panel'>")
    [void]$builder.AppendLine("<div class='toolbar'><div><label for='alertsReportSearch'>Search Alerts</label><input id='alertsReportSearch' class='report-search' data-target='alerts-tab' type='text' placeholder='Search brands, alert stores, terminals, versions, and drift' /></div><div class='toolbar-note'>Click any table header to sort.</div></div>")
    [void]$builder.AppendLine("<div class='summary'><div class='card'><span class='label'>Mixed-Version Stores</span><span class='value'>$(ConvertTo-HtmlText $Report.MixedVersionStores.Count)</span></div><div class='card'><span class='label'>Stale Terminals</span><span class='value'>$(ConvertTo-HtmlText $Report.StaleTerminals.Count)</span><span class='meta'>Older than $(ConvertTo-HtmlText $Report.AlertSettings.StaleThresholdDays) days</span></div><div class='card'><span class='label'>Far Behind Stores</span><span class='value'>$(ConvertTo-HtmlText $Report.FarBehindStores.Count)</span><span class='meta'>$(ConvertTo-HtmlText "$($Report.AlertSettings.FarBehindGapThreshold)+ version steps behind")</span></div><div class='card'><span class='label'>Stores With Drift</span><span class='value'>$(ConvertTo-HtmlText $Report.BiggestVersionDriftStores.Count)</span></div></div>")
    [void]$builder.AppendLine("<h2>Alerts</h2>")
    [void]$builder.AppendLine("<div class='note'>These alert sections call out mixed-version stores, stale POS terminals, stores far behind the current stable version <strong>$(ConvertTo-HtmlText $Report.AlertSettings.CurrentStableVersion)</strong>, and the biggest version drift inside a single store.</div>")
    if ($Report.MixedVersionStores.Count -gt 0) {
        [void]$builder.AppendLine("<h2>Mixed-Version Stores</h2>")
        [void]$builder.AppendLine("<div class='note'>Terminal Versions uses the computer name first for any T# pattern, then falls back to the known network mappings for Terminal 1 (.111), Terminal 2 (.112), and QuBox (.10). Click a store name to open the full drill-down.</div>")
        [void]$builder.AppendLine((Get-HtmlTableStart -Id "mixed-version-stores" -Columns @([pscustomobject]@{ Label = "Store ID"; Sort = "number" },[pscustomobject]@{ Label = "Store Name"; Sort = "string" },[pscustomobject]@{ Label = "Versions Detected"; Sort = "string" },[pscustomobject]@{ Label = "Terminal Versions"; Sort = "string" },[pscustomobject]@{ Label = "Unique Versions"; Sort = "number" },[pscustomobject]@{ Label = "Most Common Version"; Sort = "version" },[pscustomobject]@{ Label = "Out-Of-Date Terminals"; Sort = "number" })))
        foreach ($store in $Report.MixedVersionStores) {
            $rowSearch = Get-HtmlSearchText @($store.StoreId, $store.StoreName, (Get-StoreBrandSearchText -StoreName $store.StoreName), $store.VersionsDetected, $store.TerminalVersionMapText, $store.UniqueVersionCount, $store.MostCommonVersion, $store.OutOfDateTerminalCount)
            [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.StoreId) -SortValue $store.StoreId))
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-StoreButtonHtml -StoreId $store.StoreId -StoreName $store.StoreName -DetailKey $store.StoreDetailKey) -SortValue $store.StoreName))
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeListHtml -Versions @($store.VersionsDetectedList) -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $store.VersionsDetected))
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-TerminalVersionMapBadgeHtml -Store $store -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $store.TerminalVersionMapText))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.UniqueVersionCount) -SortValue $store.UniqueVersionCount))
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeHtml -Version $store.MostCommonVersion -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $store.MostCommonVersion))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.OutOfDateTerminalCount) -SortValue $store.OutOfDateTerminalCount))
            [void]$builder.AppendLine("</tr>")
        }
        [void]$builder.AppendLine((Get-HtmlTableEnd))
    } else {
        [void]$builder.AppendLine("<div class='note'>No mixed-version QU POS app stores were detected.</div>")
    }
    if ($Report.StaleTerminals.Count -gt 0) {
        [void]$builder.AppendLine("<h2>Stale Terminals</h2>")
        [void]$builder.AppendLine((Get-HtmlTableStart -Id "stale-terminals" -Columns @([pscustomobject]@{ Label = "Store ID"; Sort = "number" },[pscustomobject]@{ Label = "Store Name"; Sort = "string" },[pscustomobject]@{ Label = "Terminal ID"; Sort = "string" },[pscustomobject]@{ Label = "Computer Name"; Sort = "string" },[pscustomobject]@{ Label = "Type"; Sort = "string" },[pscustomobject]@{ Label = "Current Version"; Sort = "version" },[pscustomobject]@{ Label = "Last Seen"; Sort = "string" },[pscustomobject]@{ Label = "Age"; Sort = "number" })))
        foreach ($item in $Report.StaleTerminals) {
            $rowSearch = Get-HtmlSearchText @($item.StoreId, $item.StoreName, (Get-StoreBrandSearchText -StoreName $item.StoreName), $item.TerminalId, $item.ComputerName, $item.TerminalType, $item.CurrentVersion, $item.LastSeenOnline, $item.DaysSinceLastSeen)
            [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.StoreId) -SortValue $item.StoreId))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.StoreName) -SortValue $item.StoreName))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.TerminalId) -SortValue $item.TerminalId))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.ComputerName) -SortValue $item.ComputerName))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.TerminalType) -SortValue $item.TerminalType))
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeHtml -Version $item.CurrentVersion -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $item.CurrentVersion))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.LastSeenOnline) -SortValue $item.LastSeenOnline))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.DaysSinceLastSeen) -SortValue $item.LastSeenAgeDays))
            [void]$builder.AppendLine("</tr>")
        }
        [void]$builder.AppendLine((Get-HtmlTableEnd))
    } else {
        [void]$builder.AppendLine("<div class='note'>No stale QU POS app terminals were detected using the current threshold.</div>")
    }
    if ($Report.FarBehindStores.Count -gt 0) {
        [void]$builder.AppendLine("<h2>Stores Far Behind Current</h2>")
        [void]$builder.AppendLine((Get-HtmlTableStart -Id "far-behind-stores" -Columns @([pscustomobject]@{ Label = "Store ID"; Sort = "number" },[pscustomobject]@{ Label = "Store Name"; Sort = "string" },[pscustomobject]@{ Label = "Highest Version"; Sort = "version" },[pscustomobject]@{ Label = "Current Stable Version"; Sort = "version" },[pscustomobject]@{ Label = "Version Gap"; Sort = "number" },[pscustomobject]@{ Label = "Out-Of-Date Terminals"; Sort = "number" },[pscustomobject]@{ Label = "Total POS Terminals"; Sort = "number" })))
        foreach ($store in $Report.FarBehindStores) {
            $rowSearch = Get-HtmlSearchText @($store.StoreId, $store.StoreName, (Get-StoreBrandSearchText -StoreName $store.StoreName), $store.HighestVersion, $Report.AlertSettings.CurrentStableVersion, $store.VersionGapFromStable, $store.OutOfDateTerminalCount, $store.TotalPosTerminals)
            [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.StoreId) -SortValue $store.StoreId))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.StoreName) -SortValue $store.StoreName))
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeHtml -Version $store.HighestVersion -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $store.HighestVersion))
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeHtml -Version $Report.AlertSettings.CurrentStableVersion -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $Report.AlertSettings.CurrentStableVersion))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.VersionGapFromStable) -SortValue $store.VersionGapFromStable))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.OutOfDateTerminalCount) -SortValue $store.OutOfDateTerminalCount))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.TotalPosTerminals) -SortValue $store.TotalPosTerminals))
            [void]$builder.AppendLine("</tr>")
        }
        [void]$builder.AppendLine((Get-HtmlTableEnd))
    } else {
        [void]$builder.AppendLine("<div class='note'>No stores are currently far behind the current stable QU POS app version.</div>")
    }
    if ($Report.BiggestVersionDriftStores.Count -gt 0) {
        [void]$builder.AppendLine("<h2>Biggest Version Drift</h2>")
        [void]$builder.AppendLine((Get-HtmlTableStart -Id "biggest-version-drift" -Columns @([pscustomobject]@{ Label = "Store ID"; Sort = "number" },[pscustomobject]@{ Label = "Store Name"; Sort = "string" },[pscustomobject]@{ Label = "Lowest Version"; Sort = "version" },[pscustomobject]@{ Label = "Highest Version"; Sort = "version" },[pscustomobject]@{ Label = "Drift"; Sort = "number" },[pscustomobject]@{ Label = "Unique Versions"; Sort = "number" },[pscustomobject]@{ Label = "POS Terminals"; Sort = "number" })))
        foreach ($store in $Report.BiggestVersionDriftStores) {
            $rowSearch = Get-HtmlSearchText @($store.StoreId, $store.StoreName, (Get-StoreBrandSearchText -StoreName $store.StoreName), $store.LowestVersion, $store.HighestVersion, $store.VersionDriftCount, $store.UniqueVersionCount, $store.TotalPosTerminals)
            [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.StoreId) -SortValue $store.StoreId))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.StoreName) -SortValue $store.StoreName))
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeHtml -Version $store.LowestVersion -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $store.LowestVersion))
            [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeHtml -Version $store.HighestVersion -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $store.HighestVersion))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.VersionDriftCount) -SortValue $store.VersionDriftCount))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.UniqueVersionCount) -SortValue $store.UniqueVersionCount))
            [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.TotalPosTerminals) -SortValue $store.TotalPosTerminals))
            [void]$builder.AppendLine("</tr>")
        }
        [void]$builder.AppendLine((Get-HtmlTableEnd))
    } else {
        [void]$builder.AppendLine("<div class='note'>No version drift was detected inside any single store.</div>")
    }
    [void]$builder.AppendLine("</div>")
    if ($hasComparison) {
        [void]$builder.AppendLine("<div id='comparison-tab' class='tab-panel'>")
        [void]$builder.AppendLine("<div class='toolbar'><div><label for='comparisonReportSearch'>Search The Comparison</label><input id='comparisonReportSearch' class='report-search' data-target='comparison-tab' type='text' placeholder='Search brands, changed stores, terminals, and version transitions' /></div><div class='toolbar-note'>Click any table header to sort.</div></div>")
        [void]$builder.AppendLine("<h2>Comparison Snapshot</h2>")
        [void]$builder.AppendLine("<div class='note'>Comparing current CSV <strong>$(ConvertTo-HtmlText $Report.SourceCsv)</strong> against previous CSV <strong>$(ConvertTo-HtmlText $comparison.PreviousSourceCsv)</strong>.</div>")
        [void]$builder.AppendLine("<div class='summary'><div class='card'><span class='label'>Changed Terminals</span><span class='value'>$(ConvertTo-HtmlText $comparison.ChangedTerminalCount)</span><span class='meta'>$(ConvertTo-HtmlText "$($comparison.UpgradedTerminalCount) upgraded / $($comparison.DowngradedTerminalCount) downgraded")</span></div><div class='card'><span class='label'>New Terminals</span><span class='value'>$(ConvertTo-HtmlText $comparison.NewTerminalCount)</span></div><div class='card'><span class='label'>Removed Terminals</span><span class='value'>$(ConvertTo-HtmlText $comparison.RemovedTerminalCount)</span></div><div class='card'><span class='label'>Impacted Stores</span><span class='value'>$(ConvertTo-HtmlText $comparison.ImpactedStoreCount)</span></div></div>")
        if (($comparison.ChangedTerminalCount + $comparison.NewTerminalCount + $comparison.RemovedTerminalCount) -eq 0) {
            [void]$builder.AppendLine("<div class='note'>No terminal changes were detected between the two CSV files.</div>")
        }
        if ($comparison.VersionTransitions.Count -gt 0) {
            [void]$builder.AppendLine("<h2>Version Transition Summary</h2>")
            [void]$builder.AppendLine((Get-HtmlTableStart -Id "version-transitions" -Columns @([pscustomobject]@{ Label = "Previous Version"; Sort = "version" },[pscustomobject]@{ Label = "Current Version"; Sort = "version" },[pscustomobject]@{ Label = "Change Type"; Sort = "string" },[pscustomobject]@{ Label = "Terminals"; Sort = "number" })))
            foreach ($transition in $comparison.VersionTransitions) {
                $rowSearch = Get-HtmlSearchText @($transition.PreviousVersion, $transition.CurrentVersion, $transition.ChangeType, $transition.TerminalCount)
                [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $transition.PreviousVersion) -SortValue $transition.PreviousVersion))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $transition.CurrentVersion) -SortValue $transition.CurrentVersion))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $transition.ChangeType) -SortValue $transition.ChangeType))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $transition.TerminalCount) -SortValue $transition.TerminalCount))
                [void]$builder.AppendLine("</tr>")
            }
            [void]$builder.AppendLine((Get-HtmlTableEnd))
        }
        if ($comparison.ImpactedStores.Count -gt 0) {
            [void]$builder.AppendLine("<h2>Impacted Stores</h2>")
            [void]$builder.AppendLine((Get-HtmlTableStart -Id "impacted-stores" -Columns @([pscustomobject]@{ Label = "Store ID"; Sort = "number" },[pscustomobject]@{ Label = "Store Name"; Sort = "string" },[pscustomobject]@{ Label = "Changed"; Sort = "number" },[pscustomobject]@{ Label = "New"; Sort = "number" },[pscustomobject]@{ Label = "Removed"; Sort = "number" })))
            foreach ($store in $comparison.ImpactedStores) {
                $rowSearch = Get-HtmlSearchText @($store.StoreId, $store.StoreName, (Get-StoreBrandSearchText -StoreName $store.StoreName), $store.ChangedTerminalCount, $store.NewTerminalCount, $store.RemovedTerminalCount)
                [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.StoreId) -SortValue $store.StoreId))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.StoreName) -SortValue $store.StoreName))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.ChangedTerminalCount) -SortValue $store.ChangedTerminalCount))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.NewTerminalCount) -SortValue $store.NewTerminalCount))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $store.RemovedTerminalCount) -SortValue $store.RemovedTerminalCount))
                [void]$builder.AppendLine("</tr>")
            }
            [void]$builder.AppendLine((Get-HtmlTableEnd))
        }
        if ($comparison.ChangedTerminals.Count -gt 0) {
            [void]$builder.AppendLine("<h2>Changed Terminals</h2>")
            [void]$builder.AppendLine((Get-HtmlTableStart -Id "changed-terminals" -Columns @([pscustomobject]@{ Label = "Store ID"; Sort = "number" },[pscustomobject]@{ Label = "Store Name"; Sort = "string" },[pscustomobject]@{ Label = "Terminal ID"; Sort = "string" },[pscustomobject]@{ Label = "Computer Name"; Sort = "string" },[pscustomobject]@{ Label = "Type"; Sort = "string" },[pscustomobject]@{ Label = "Previous Version"; Sort = "version" },[pscustomobject]@{ Label = "Current Version"; Sort = "version" },[pscustomobject]@{ Label = "Change Type"; Sort = "string" },[pscustomobject]@{ Label = "Last Seen Online"; Sort = "string" })))
            foreach ($item in $comparison.ChangedTerminals) {
                $rowSearch = Get-HtmlSearchText @($item.StoreId, $item.StoreName, (Get-StoreBrandSearchText -StoreName $item.StoreName), $item.TerminalId, $item.ComputerName, $item.TerminalType, $item.PreviousVersion, $item.CurrentVersion, $item.ChangeType, $item.LastSeenOnline)
                [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.StoreId) -SortValue $item.StoreId))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.StoreName) -SortValue $item.StoreName))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.TerminalId) -SortValue $item.TerminalId))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.ComputerName) -SortValue $item.ComputerName))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.TerminalType) -SortValue $item.TerminalType))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.PreviousVersion) -SortValue $item.PreviousVersion))
                [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeHtml -Version $item.CurrentVersion -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $item.CurrentVersion))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.ChangeType) -SortValue $item.ChangeType))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.LastSeenOnline) -SortValue $item.LastSeenOnline))
                [void]$builder.AppendLine("</tr>")
            }
            [void]$builder.AppendLine((Get-HtmlTableEnd))
        }
        if ($comparison.NewTerminals.Count -gt 0) {
            [void]$builder.AppendLine("<h2>New Terminals</h2>")
            [void]$builder.AppendLine((Get-HtmlTableStart -Id "new-terminals" -Columns @([pscustomobject]@{ Label = "Store ID"; Sort = "number" },[pscustomobject]@{ Label = "Store Name"; Sort = "string" },[pscustomobject]@{ Label = "Terminal ID"; Sort = "string" },[pscustomobject]@{ Label = "Computer Name"; Sort = "string" },[pscustomobject]@{ Label = "Type"; Sort = "string" },[pscustomobject]@{ Label = "Current Version"; Sort = "version" },[pscustomobject]@{ Label = "Last Seen Online"; Sort = "string" })))
            foreach ($item in $comparison.NewTerminals) {
                $rowSearch = Get-HtmlSearchText @($item.StoreId, $item.StoreName, (Get-StoreBrandSearchText -StoreName $item.StoreName), $item.TerminalId, $item.ComputerName, $item.TerminalType, $item.CurrentVersion, $item.LastSeenOnline)
                [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.StoreId) -SortValue $item.StoreId))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.StoreName) -SortValue $item.StoreName))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.TerminalId) -SortValue $item.TerminalId))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.ComputerName) -SortValue $item.ComputerName))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.TerminalType) -SortValue $item.TerminalType))
                [void]$builder.AppendLine((Get-HtmlCell -Html (Get-VersionBadgeHtml -Version $item.CurrentVersion -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion) -SortValue $item.CurrentVersion))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.LastSeenOnline) -SortValue $item.LastSeenOnline))
                [void]$builder.AppendLine("</tr>")
            }
            [void]$builder.AppendLine((Get-HtmlTableEnd))
        }
        if ($comparison.RemovedTerminals.Count -gt 0) {
            [void]$builder.AppendLine("<h2>Removed Terminals</h2>")
            [void]$builder.AppendLine((Get-HtmlTableStart -Id "removed-terminals" -Columns @([pscustomobject]@{ Label = "Store ID"; Sort = "number" },[pscustomobject]@{ Label = "Store Name"; Sort = "string" },[pscustomobject]@{ Label = "Terminal ID"; Sort = "string" },[pscustomobject]@{ Label = "Computer Name"; Sort = "string" },[pscustomobject]@{ Label = "Type"; Sort = "string" },[pscustomobject]@{ Label = "Previous Version"; Sort = "version" },[pscustomobject]@{ Label = "Last Seen Online"; Sort = "string" })))
            foreach ($item in $comparison.RemovedTerminals) {
                $rowSearch = Get-HtmlSearchText @($item.StoreId, $item.StoreName, (Get-StoreBrandSearchText -StoreName $item.StoreName), $item.TerminalId, $item.ComputerName, $item.TerminalType, $item.PreviousVersion, $item.LastSeenOnline)
                [void]$builder.AppendLine("<tr data-search='$rowSearch'>")
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.StoreId) -SortValue $item.StoreId))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.StoreName) -SortValue $item.StoreName))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.TerminalId) -SortValue $item.TerminalId))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.ComputerName) -SortValue $item.ComputerName))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.TerminalType) -SortValue $item.TerminalType))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.PreviousVersion) -SortValue $item.PreviousVersion))
                [void]$builder.AppendLine((Get-HtmlCell -Html (ConvertTo-HtmlText $item.LastSeenOnline) -SortValue $item.LastSeenOnline))
                [void]$builder.AppendLine("</tr>")
            }
            [void]$builder.AppendLine((Get-HtmlTableEnd))
        }
        [void]$builder.AppendLine("</div>")
    }
    [void]$builder.AppendLine("<div id='storeDetailModal' class='modal-overlay' aria-hidden='true'><div class='modal-panel'><div class='modal-header'><div><div class='modal-kicker'>Store Drill-Down</div><h2 id='storeDetailTitle'>Store Details</h2></div><button type='button' class='modal-close' data-store-detail-close='true'>Close</button></div><div id='storeDetailBody' class='modal-body'></div></div></div>")
    [void]$builder.AppendLine("<div class='store-detail-templates'>")
    foreach ($store in $Report.StoreVersionReport) {
        [void]$builder.AppendLine("<template id='store-detail-$($store.StoreDetailKey)'>$(New-StoreDetailTemplateHtml -Store $store -CurrentStableVersion $currentStableVersion -MostCurrentVersion $mostCurrentVersion)</template>")
    }
    [void]$builder.AppendLine("</div>")
    [void]$builder.AppendLine($searchScript)
    [void]$builder.AppendLine("</body></html>")
    return $builder.ToString()
}

function Export-CsvHtmlReport {
    param([string]$CsvPath,[string]$PreviousCsvPath,[string]$OutputFile,[string]$BaseUrl,[string]$KioskBaseUrl,[string]$LogoPath,[switch]$TimestampedOutput,[switch]$OpenReport)
    $report = Get-CurrentVersionsFromCsv -CsvPath $CsvPath -BaseUrl $BaseUrl -KioskBaseUrl $KioskBaseUrl -PreviousCsvPath $PreviousCsvPath
    $resolvedOutputFile = if ($TimestampedOutput) { Get-TimestampedOutputPath -OutputFile $OutputFile -GeneratedOn $report.GeneratedOn } else { Resolve-FilePath $OutputFile }
    $outputDirectory = Split-Path $resolvedOutputFile -Parent
    if ($outputDirectory -and -not (Test-Path $outputDirectory)) {
        [void](New-Item -ItemType Directory -Path $outputDirectory -Force)
    }
    $logoDataUri = Get-ImageDataUri -Path $LogoPath
    Write-StageMessage "Writing HTML"
    (New-CsvHtmlReport -Report $report -LogoDataUri $logoDataUri) | Out-File $resolvedOutputFile -Encoding UTF8
    Write-StageMessage "Done"
    if ($OpenReport) { Start-Process $resolvedOutputFile }
    return [pscustomobject]@{ OutputFile = $resolvedOutputFile; Report = $report }
}

function Export-ScanHtmlReport {
    param([string[]]$ReleaseTrains,[string]$BaseUrl,[string]$CacheFile,[string]$OutputFile,[string]$LogoPath,[switch]$OpenReport)
    $resolvedOutputFile = Resolve-FilePath $OutputFile
    $resolvedCacheFile = Resolve-FilePath $CacheFile
    $allResults = Load-CachedResults -CacheFile $resolvedCacheFile
    $allResults = Get-BuildResultsFromScan -ReleaseTrains $ReleaseTrains -BaseUrl $BaseUrl -CachedResults $allResults
    Save-CachedResults -Results $allResults -CacheFile $resolvedCacheFile
    $logoDataUri = Get-ImageDataUri -Path $LogoPath
    (New-ScanHtmlReport -AllResults $allResults -ReleaseTrains $ReleaseTrains -LogoDataUri $logoDataUri) | Out-File $resolvedOutputFile -Encoding UTF8
    if ($OpenReport) { Start-Process $resolvedOutputFile }
    return [pscustomobject]@{ OutputFile = $resolvedOutputFile; Results = $allResults }
}

function Invoke-QuApp {
    param([string]$BaseUrl,[string]$KioskBaseUrl,[string]$OutputFile,[string]$CacheFile,[string[]]$ReleaseTrains,[string]$CsvPath,[string]$PreviousCsvPath,[string]$LogoPath,[switch]$TimestampedOutput,[switch]$SkipOpen)
    if ($CsvPath) { return Export-CsvHtmlReport -CsvPath $CsvPath -PreviousCsvPath $PreviousCsvPath -OutputFile $OutputFile -BaseUrl $BaseUrl -KioskBaseUrl $KioskBaseUrl -LogoPath $LogoPath -TimestampedOutput:$TimestampedOutput -OpenReport:(-not $SkipOpen) }
    return Export-ScanHtmlReport -ReleaseTrains $ReleaseTrains -BaseUrl $BaseUrl -CacheFile $CacheFile -OutputFile $OutputFile -LogoPath $LogoPath -OpenReport:(-not $SkipOpen)
}

function Show-QuAppUi {
    param([string]$BaseUrl,[string]$KioskBaseUrl,[string]$InitialCsvPath,[string]$InitialPreviousCsvPath,[string]$InitialOutputFile,[string]$LogoPath,[bool]$OpenAfterGenerate = $true,[bool]$InitialTimestampedOutput = $false)
    if ([System.Threading.Thread]::CurrentThread.ApartmentState -ne [System.Threading.ApartmentState]::STA) {
        $hostPath = (Get-Process -Id $PID).Path
        $staSwitch = if ([System.IO.Path]::GetFileName($hostPath) -ieq "powershell.exe") { "-STA" } else { "-Sta" }
        $arguments = @("-NoProfile", $staSwitch, "-File", (Quote-CommandLineArgument $PSCommandPath), "-ShowUi", "-BaseUrl", (Quote-CommandLineArgument $BaseUrl), "-KioskBaseUrl", (Quote-CommandLineArgument $KioskBaseUrl))
        if ($InitialCsvPath) { $arguments += @("-CsvPath", (Quote-CommandLineArgument $InitialCsvPath)) }
        if ($InitialPreviousCsvPath) { $arguments += @("-PreviousCsvPath", (Quote-CommandLineArgument $InitialPreviousCsvPath)) }
        if ($InitialOutputFile) { $arguments += @("-OutputFile", (Quote-CommandLineArgument $InitialOutputFile)) }
        if ($LogoPath) { $arguments += @("-LogoPath", (Quote-CommandLineArgument $LogoPath)) }
        if ($InitialTimestampedOutput) { $arguments += "-TimestampedOutput" }
        if (-not $OpenAfterGenerate) { $arguments += "-SkipOpen" }
        Hide-ConsoleWindow
        try {
            $uiProcess = Start-Process -FilePath $hostPath -ArgumentList $arguments -WindowStyle Hidden -PassThru
            $uiProcess.WaitForExit()
        } finally {
            Show-ConsoleWindow
        }
        return
    }

    Hide-ConsoleWindow
    Add-Type -AssemblyName System.Windows.Forms
    Add-Type -AssemblyName System.Drawing
    [System.Windows.Forms.Application]::EnableVisualStyles()

    $form = New-Object System.Windows.Forms.Form
    $form.Text = "Build QU POS APP HTML TOOL v$ToolVersion"
    $form.StartPosition = "CenterScreen"
    $form.Size = New-Object System.Drawing.Size(760, 660)
    $form.MinimumSize = New-Object System.Drawing.Size(760, 660)
    $form.BackColor = [System.Drawing.Color]::FromArgb(15, 23, 42)
    $form.ForeColor = [System.Drawing.Color]::White
    $resolvedLogoPath = if ($LogoPath -and (Test-Path $LogoPath)) { Resolve-FilePath $LogoPath } else { $null }

    $logoBox = New-Object System.Windows.Forms.PictureBox
    $logoBox.Location = New-Object System.Drawing.Point(20, 18)
    $logoBox.Size = New-Object System.Drawing.Size(92, 78)
    $logoBox.SizeMode = [System.Windows.Forms.PictureBoxSizeMode]::Zoom
    $logoBox.BackColor = [System.Drawing.Color]::Transparent
    if ($resolvedLogoPath) {
        $logoBox.Image = [System.Drawing.Image]::FromFile($resolvedLogoPath)
    }

    $versionLabel = New-Object System.Windows.Forms.Label
    $versionLabel.Location = New-Object System.Drawing.Point(132, 78)
    $versionLabel.Size = New-Object System.Drawing.Size(160, 22)
    $versionLabel.TextAlign = [System.Drawing.ContentAlignment]::MiddleLeft
    $versionLabel.ForeColor = [System.Drawing.Color]::FromArgb(148, 163, 184)
    $versionLabel.Text = "Version $ToolVersion"

    $title = New-Object System.Windows.Forms.Label
    $title.Location = New-Object System.Drawing.Point(132, 16)
    $title.Size = New-Object System.Drawing.Size(560, 30)
    $title.Font = New-Object System.Drawing.Font("Segoe UI", 16, [System.Drawing.FontStyle]::Bold)
    $title.ForeColor = [System.Drawing.Color]::FromArgb(56, 189, 248)
    $title.Text = "Build QU POS APP HTML TOOL"

    $desc = New-Object System.Windows.Forms.Label
    $desc.Location = New-Object System.Drawing.Point(132, 50)
    $desc.Size = New-Object System.Drawing.Size(520, 24)
    $desc.Text = "Generate The QU POS HTML Report."

    $csvLabel = New-Object System.Windows.Forms.Label
    $csvLabel.Location = New-Object System.Drawing.Point(20, 118)
    $csvLabel.Size = New-Object System.Drawing.Size(120, 20)
    $csvLabel.Text = "Terminal CSV"

    $csvText = New-Object System.Windows.Forms.TextBox
    $csvText.Location = New-Object System.Drawing.Point(20, 140)
    $csvText.Size = New-Object System.Drawing.Size(580, 24)
    $csvText.Text = $InitialCsvPath

    $browseCsv = New-Object System.Windows.Forms.Button
    $browseCsv.Location = New-Object System.Drawing.Point(615, 138)
    $browseCsv.Size = New-Object System.Drawing.Size(105, 28)
    $browseCsv.Text = "Browse CSV"

    $previousCsvLabel = New-Object System.Windows.Forms.Label
    $previousCsvLabel.Location = New-Object System.Drawing.Point(20, 180)
    $previousCsvLabel.Size = New-Object System.Drawing.Size(180, 20)
    $previousCsvLabel.Text = "Previous CSV (Optional)"

    $previousCsvText = New-Object System.Windows.Forms.TextBox
    $previousCsvText.Location = New-Object System.Drawing.Point(20, 202)
    $previousCsvText.Size = New-Object System.Drawing.Size(580, 24)
    $previousCsvText.Text = $InitialPreviousCsvPath

    $browsePreviousCsv = New-Object System.Windows.Forms.Button
    $browsePreviousCsv.Location = New-Object System.Drawing.Point(615, 200)
    $browsePreviousCsv.Size = New-Object System.Drawing.Size(105, 28)
    $browsePreviousCsv.Text = "Browse CSV"

    $outLabel = New-Object System.Windows.Forms.Label
    $outLabel.Location = New-Object System.Drawing.Point(20, 242)
    $outLabel.Size = New-Object System.Drawing.Size(120, 20)
    $outLabel.Text = "Output HTML"

    $outText = New-Object System.Windows.Forms.TextBox
    $outText.Location = New-Object System.Drawing.Point(20, 264)
    $outText.Size = New-Object System.Drawing.Size(580, 24)
    $outText.Text = $InitialOutputFile

    $browseOut = New-Object System.Windows.Forms.Button
    $browseOut.Location = New-Object System.Drawing.Point(615, 262)
    $browseOut.Size = New-Object System.Drawing.Size(105, 28)
    $browseOut.Text = "Save As"

    $chkOpen = New-Object System.Windows.Forms.CheckBox
    $chkOpen.Location = New-Object System.Drawing.Point(20, 302)
    $chkOpen.Size = New-Object System.Drawing.Size(220, 24)
    $chkOpen.Text = "Open HTML after generate"
    $chkOpen.Checked = $OpenAfterGenerate
    $chkOpen.ForeColor = [System.Drawing.Color]::White

    $chkTimestamped = New-Object System.Windows.Forms.CheckBox
    $chkTimestamped.Location = New-Object System.Drawing.Point(260, 302)
    $chkTimestamped.Size = New-Object System.Drawing.Size(280, 24)
    $chkTimestamped.Text = "Save in Reports\YYYY-MM-DD folder"
    $chkTimestamped.Checked = $InitialTimestampedOutput
    $chkTimestamped.ForeColor = [System.Drawing.Color]::White

    $btnGenerate = New-Object System.Windows.Forms.Button
    $btnGenerate.Location = New-Object System.Drawing.Point(20, 346)
    $btnGenerate.Size = New-Object System.Drawing.Size(140, 34)
    $btnGenerate.Text = "Generate HTML"

    $btnOpen = New-Object System.Windows.Forms.Button
    $btnOpen.Location = New-Object System.Drawing.Point(172, 346)
    $btnOpen.Size = New-Object System.Drawing.Size(140, 34)
    $btnOpen.Text = "Open Report"
    $btnOpen.Enabled = ($InitialOutputFile -and (Test-Path $InitialOutputFile))

    $btnClose = New-Object System.Windows.Forms.Button
    $btnClose.Location = New-Object System.Drawing.Point(324, 346)
    $btnClose.Size = New-Object System.Drawing.Size(100, 34)
    $btnClose.Text = "Close"

    $summary = New-Object System.Windows.Forms.Label
    $summary.Location = New-Object System.Drawing.Point(20, 398)
    $summary.Size = New-Object System.Drawing.Size(700, 42)
    $summary.Font = New-Object System.Drawing.Font("Segoe UI", 9, [System.Drawing.FontStyle]::Bold)
    $summary.Text = "Choose a CSV file to get started."

    $statusBox = New-Object System.Windows.Forms.TextBox
    $statusBox.Location = New-Object System.Drawing.Point(20, 444)
    $statusBox.Size = New-Object System.Drawing.Size(700, 166)
    $statusBox.Multiline = $true
    $statusBox.ReadOnly = $true
    $statusBox.ScrollBars = "Vertical"
    $statusBox.BackColor = [System.Drawing.Color]::FromArgb(17, 24, 39)
    $statusBox.ForeColor = [System.Drawing.Color]::White

    $openDialog = New-Object System.Windows.Forms.OpenFileDialog
    $openDialog.Filter = "CSV Files (*.csv)|*.csv|All Files (*.*)|*.*"
    $saveDialog = New-Object System.Windows.Forms.SaveFileDialog
    $saveDialog.Filter = "HTML Files (*.html)|*.html|All Files (*.*)|*.*"
    $saveDialog.OverwritePrompt = $true
    if ($InitialOutputFile) { $saveDialog.FileName = Split-Path $InitialOutputFile -Leaf }

    $appendStatus = {
        param([string]$Message)
        $statusBox.AppendText("[$(Get-Date -Format 'hh:mm:ss tt')] $Message`r`n")
    }

    if ($InitialCsvPath) { & $appendStatus "CSV preloaded: $InitialCsvPath" }
    if ($InitialPreviousCsvPath) { & $appendStatus "Previous CSV preloaded: $InitialPreviousCsvPath" }
    if ($InitialOutputFile) { & $appendStatus "Output path: $InitialOutputFile" }
    if ($resolvedLogoPath) { & $appendStatus "Logo loaded: $resolvedLogoPath" }
    if ($InitialTimestampedOutput) { & $appendStatus "Timestamped output is enabled." }
    & $appendStatus "Tool version: $ToolVersion"

    $browseCsv.Add_Click({
        if ($csvText.Text -and (Test-Path $csvText.Text)) {
            $openDialog.InitialDirectory = Split-Path $csvText.Text -Parent
            $openDialog.FileName = Split-Path $csvText.Text -Leaf
        }
        if ($openDialog.ShowDialog() -eq [System.Windows.Forms.DialogResult]::OK) {
            $csvText.Text = $openDialog.FileName
            if (-not $outText.Text) { $outText.Text = Join-Path (Split-Path $openDialog.FileName -Parent) "QuPOS_CurrentVersions.html" }
            & $appendStatus "Selected CSV: $($openDialog.FileName)"
        }
    })

    $browsePreviousCsv.Add_Click({
        if ($previousCsvText.Text -and (Test-Path $previousCsvText.Text)) {
            $openDialog.InitialDirectory = Split-Path $previousCsvText.Text -Parent
            $openDialog.FileName = Split-Path $previousCsvText.Text -Leaf
        }
        if ($openDialog.ShowDialog() -eq [System.Windows.Forms.DialogResult]::OK) {
            $previousCsvText.Text = $openDialog.FileName
            & $appendStatus "Selected previous CSV: $($openDialog.FileName)"
        }
    })

    $browseOut.Add_Click({
        if ($outText.Text) {
            $saveDialog.InitialDirectory = Split-Path $outText.Text -Parent
            $saveDialog.FileName = Split-Path $outText.Text -Leaf
        }
        if ($saveDialog.ShowDialog() -eq [System.Windows.Forms.DialogResult]::OK) {
            $outText.Text = $saveDialog.FileName
            & $appendStatus "Output HTML set to: $($saveDialog.FileName)"
        }
    })

    $btnGenerate.Add_Click({
        $csvPathValue = $csvText.Text.Trim()
        $previousCsvPathValue = $previousCsvText.Text.Trim()
        $outputPathValue = $outText.Text.Trim()
        if (-not $csvPathValue -or -not (Test-Path $csvPathValue)) {
            [System.Windows.Forms.MessageBox]::Show("Select a valid CSV file before generating the report.", "CSV Required", [System.Windows.Forms.MessageBoxButtons]::OK, [System.Windows.Forms.MessageBoxIcon]::Warning) | Out-Null
            return
        }
        if ($previousCsvPathValue -and -not (Test-Path $previousCsvPathValue)) {
            [System.Windows.Forms.MessageBox]::Show("Select a valid previous CSV file or clear that field before generating the report.", "Previous CSV Not Found", [System.Windows.Forms.MessageBoxButtons]::OK, [System.Windows.Forms.MessageBoxIcon]::Warning) | Out-Null
            return
        }
        if (-not $outputPathValue) {
            [System.Windows.Forms.MessageBox]::Show("Choose where the HTML report should be saved.", "Output Required", [System.Windows.Forms.MessageBoxButtons]::OK, [System.Windows.Forms.MessageBoxIcon]::Warning) | Out-Null
            return
        }
        $btnGenerate.Enabled = $false
        $btnOpen.Enabled = $false
        $form.UseWaitCursor = $true
        [System.Windows.Forms.Application]::DoEvents()
        try {
            & $appendStatus "Generating HTML from CSV..."
            if ($previousCsvPathValue) {
                & $appendStatus "Comparison enabled with previous CSV: $previousCsvPathValue"
            }
            if ($chkTimestamped.Checked) {
                & $appendStatus "Timestamped output enabled."
            }
            $result = Export-CsvHtmlReport -CsvPath $csvPathValue -PreviousCsvPath $previousCsvPathValue -OutputFile $outputPathValue -BaseUrl $BaseUrl -KioskBaseUrl $KioskBaseUrl -LogoPath $LogoPath -TimestampedOutput:$chkTimestamped.Checked -OpenReport:$chkOpen.Checked
            $report = $result.Report
            $summaryText = "Created $($result.OutputFile) | $($report.DownloadableRowCount) POS terminals | $($report.UniqueQuPosAppVersionCount) unique app versions | stable $($report.CurrentStableVersion) | $($report.OutOfDateStoreCount) out-of-date stores"
            if ($report.Comparison) {
                $summaryText += " | $($report.Comparison.ChangedTerminalCount) changed | $($report.Comparison.NewTerminalCount) new | $($report.Comparison.RemovedTerminalCount) removed"
            }
            $summary.Text = $summaryText
            $outText.Text = $result.OutputFile
            $btnOpen.Enabled = (Test-Path $result.OutputFile)
            & $appendStatus "HTML created: $($result.OutputFile)"
            & $appendStatus "Summary: $($report.DownloadableRowCount) POS terminals, $($report.UniqueQuPosAppVersionCount) unique app versions, most current version $($report.MostCurrentVersion), current stable version $($report.CurrentStableVersion)."
            & $appendStatus "Out-of-date: $($report.OutOfDateStoreCount) stores and $($report.OutOfDateTerminalCount) POS terminals below the current stable version."
            if ($report.Comparison) {
                & $appendStatus "Comparison: $($report.Comparison.ChangedTerminalCount) changed, $($report.Comparison.NewTerminalCount) new, $($report.Comparison.RemovedTerminalCount) removed across $($report.Comparison.ImpactedStoreCount) stores."
            }
            [System.Windows.Forms.MessageBox]::Show("HTML report created successfully.`r`n`r`n$($result.OutputFile)", "Report Created", [System.Windows.Forms.MessageBoxButtons]::OK, [System.Windows.Forms.MessageBoxIcon]::Information) | Out-Null
        } catch {
            $summary.Text = "Generation failed. See the status box for details."
            & $appendStatus "Error: $($_.Exception.Message)"
            [System.Windows.Forms.MessageBox]::Show($_.Exception.Message, "Generation Failed", [System.Windows.Forms.MessageBoxButtons]::OK, [System.Windows.Forms.MessageBoxIcon]::Error) | Out-Null
        } finally {
            $form.UseWaitCursor = $false
            $btnGenerate.Enabled = $true
        }
    })

    $btnOpen.Add_Click({
        $outputPathValue = $outText.Text.Trim()
        if ($outputPathValue -and (Test-Path $outputPathValue)) {
            Start-Process $outputPathValue
            & $appendStatus "Opened report: $outputPathValue"
        } else {
            [System.Windows.Forms.MessageBox]::Show("The selected HTML file does not exist yet.", "File Not Found", [System.Windows.Forms.MessageBoxButtons]::OK, [System.Windows.Forms.MessageBoxIcon]::Warning) | Out-Null
        }
    })

    $btnClose.Add_Click({ $form.Close() })
    $form.AcceptButton = $btnGenerate
    $form.CancelButton = $btnClose
    $form.Controls.AddRange(@($logoBox, $versionLabel, $title, $desc, $csvLabel, $csvText, $browseCsv, $previousCsvLabel, $previousCsvText, $browsePreviousCsv, $outLabel, $outText, $browseOut, $chkOpen, $chkTimestamped, $btnGenerate, $btnOpen, $btnClose, $summary, $statusBox))
    [void]$form.ShowDialog()
}

if ($ShowUi) {
    Show-QuAppUi -BaseUrl $BaseUrl -KioskBaseUrl $KioskBaseUrl -InitialCsvPath $CsvPath -InitialPreviousCsvPath $PreviousCsvPath -InitialOutputFile $OutputFile -LogoPath $LogoPath -OpenAfterGenerate:(-not $SkipOpen) -InitialTimestampedOutput:$TimestampedOutput
    return
}

try {
    $result = Invoke-QuApp -BaseUrl $BaseUrl -KioskBaseUrl $KioskBaseUrl -OutputFile $OutputFile -CacheFile $CacheFile -ReleaseTrains $ReleaseTrains -CsvPath $CsvPath -PreviousCsvPath $PreviousCsvPath -LogoPath $LogoPath -TimestampedOutput:$TimestampedOutput -SkipOpen:$SkipOpen
    Write-Host "Done. HTML created: $($result.OutputFile)" -ForegroundColor Cyan
} catch {
    Write-StageMessage "Error: $($_.Exception.Message)"
    throw
}








