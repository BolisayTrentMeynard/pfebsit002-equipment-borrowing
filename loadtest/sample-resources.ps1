<#
Week 16, chunk 3: resource sampler (Windows PowerShell 5.1 or newer). Writes one CSV line per process name every 5 s.
Run it in its OWN PowerShell window, start it just BEFORE k6, stop it with Ctrl+C after k6 ends.

  powershell -NoProfile -ExecutionPolicy Bypass -File loadtest\sample-resources.ps1 -Label ordinary

What it records: for php, mysqld, caddy and k6 (all processes with that name added together): CPU in percent of ONE core
(about 100 = one full core busy), working-set memory in MB, process count. It also prints the size of laravel.log at the
start and at the end. Nothing secret is read: no command lines, no environment, no file contents.
Output: loadtest\results\<Label>-resources.csv (git-ignored).

Limits (state them next to the numbers): the sampler's own cost is tiny but not zero; "php" adds the artisan serve process and
the PHP server it starts, plus any other php.exe running (dev API, a queue worker), so close those first; CPU is computed from
the total CPU time of the processes and can dip when a process starts or ends; k6 and the system share one PC.
#>
param(
    [Parameter(Mandatory = $true)][string]$Label,
    [int]$IntervalSeconds = 5,
    [string]$LogPath = 'backend\storage\logs\laravel.log'
)

$names = @('php', 'mysqld', 'caddy', 'k6')
$outDir = Join-Path (Get-Location) 'loadtest\results'
if (-not (Test-Path $outDir)) { New-Item -ItemType Directory -Path $outDir | Out-Null }
$csv = Join-Path $outDir ("$Label-resources.csv")
'time_utc,process,count,cpu_pct_one_core,mem_mb' | Set-Content -Path $csv -Encoding ascii

function Log-Size {
    if (Test-Path $LogPath) { return (Get-Item $LogPath).Length } else { return -1 }
}
$logStart = Log-Size
Write-Host ("Sampling every {0} s into {1}. Stop with Ctrl+C. laravel.log at start: {2} bytes ({3})" -f $IntervalSeconds, $csv, $logStart, $LogPath)
Write-Host "Logical cores on this PC: $([Environment]::ProcessorCount)  (100 percent = one core)"

$prev = @{}
$prevT = Get-Date
try {
    while ($true) {
        Start-Sleep -Seconds $IntervalSeconds
        $now = Get-Date
        $dt = ($now - $prevT).TotalSeconds
        $prevT = $now
        foreach ($n in $names) {
            $procs = @(Get-Process -Name $n -ErrorAction SilentlyContinue)
            $cpuTotal = 0.0
            $mem = 0.0
            foreach ($p in $procs) {
                if ($null -ne $p.CPU) { $cpuTotal += [double]$p.CPU }
                $mem += [double]$p.WorkingSet64
            }
            $pct = 0.0
            if ($prev.ContainsKey($n) -and $dt -gt 0) {
                $delta = $cpuTotal - $prev[$n]
                if ($delta -lt 0) { $delta = 0 }   # a process ended since the last sample
                $pct = 100.0 * $delta / $dt
            }
            $prev[$n] = $cpuTotal
            $line = '{0},{1},{2},{3:N1},{4:N1}' -f $now.ToUniversalTime().ToString('HH:mm:ss'), $n, $procs.Count, $pct, ($mem / 1MB)
            Add-Content -Path $csv -Value $line -Encoding ascii
        }
    }
}
finally {
    $logEnd = Log-Size
    Write-Host ''
    Write-Host ("laravel.log: start {0} bytes, end {1} bytes, growth {2} bytes" -f $logStart, $logEnd, ($logEnd - $logStart))
    Write-Host "CSV saved: $csv"
}
