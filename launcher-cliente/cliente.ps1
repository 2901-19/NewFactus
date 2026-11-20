# FACTUS - Lanzador cliente (PC ligera)
# Abre el sistema servido por la PC principal en una ventana de navegador
# dedicada (modo app). No levanta PHP ni PostgreSQL: solo conecta al servidor.
# Al cerrar la ventana: mata el navegador y cierra la sesion del usuario
# (POST /lanzador/cerrar-sesion).

$ErrorActionPreference = 'Stop'

$base = Split-Path -Parent $MyInvocation.MyCommand.Path
$configPath = Join-Path $base 'cliente-config.json'

$config = @{ servidor = 'FACTUS-SERVIDOR'; port = 8000; browser = 'auto' }
if (Test-Path $configPath) {
    $cfg = Get-Content $configPath -Raw | ConvertFrom-Json
    if ($cfg.servidor) { $config.servidor = [string]$cfg.servidor }
    if ($cfg.port)     { $config.port     = [int]$cfg.port }
    if ($cfg.browser)  { $config.browser  = [string]$cfg.browser }
}

$servidor = $config.servidor
$port = $config.port
$baseUrl = "http://${servidor}:$port"

$stateDir = Join-Path $env:LOCALAPPDATA 'FACTUS'
New-Item -ItemType Directory -Force -Path $stateDir | Out-Null
$tokenFile = Join-Path $stateDir 'cliente-token.txt'
$profileDir = Join-Path $stateDir 'edge-profile-cliente'

$browser = $null
$token = $null

function Mensaje([string]$texto, [string]$tipo = 'Information') {
    Add-Type -AssemblyName System.Windows.Forms
    [System.Windows.Forms.MessageBox]::Show($texto, 'FACTUS', 'OK', $tipo) | Out-Null
}

function Test-Puerto([int]$p, [string]$hostName) {
    $cliente = New-Object System.Net.Sockets.TcpClient
    try {
        $async = $cliente.BeginConnect($hostName, $p, $null, $null)
        $ok = $async.AsyncWaitHandle.WaitOne(3000, $false)
        if (-not $ok) { return $false }
        $cliente.EndConnect($async)
        return $true
    } catch {
        return $false
    } finally {
        $cliente.Dispose()
    }
}

function Matar-Proc([int]$id) {
    if (-not $id -or $id -le 0) { return }
    try { & taskkill /PID $id /T /F 2>$null | Out-Null } catch { }
}

function Localizar-Navegador() {
    $rutas = @{
        edge   = @(
            "$env:ProgramFiles(x86)\Microsoft\Edge\Application\msedge.exe",
            "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe"
        )
        chrome = @(
            "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
            "$env:ProgramFiles(x86)\Google\Chrome\Application\chrome.exe",
            "$env:LOCALAPPDATA\Google\Chrome\Application\chrome.exe"
        )
        brave  = @(
            "$env:ProgramFiles\BraveSoftware\Brave-Browser\Application\brave.exe",
            "$env:ProgramFiles(x86)\BraveSoftware\Brave-Browser\Application\brave.exe",
            "$env:LOCALAPPDATA\BraveSoftware\Brave-Browser\Application\brave.exe"
        )
    }
    $orden = switch ($config.browser) {
        'brave'  { @('brave', 'edge', 'chrome') }
        'edge'   { @('edge', 'chrome', 'brave') }
        'chrome' { @('chrome', 'edge', 'brave') }
        default  { @('edge', 'chrome', 'brave') }
    }
    foreach ($nombre in $orden) {
        foreach ($ruta in $rutas[$nombre]) {
            if (Test-Path $ruta) { return $ruta }
        }
    }
    return $null
}

function Abrir-Ventana([string]$url) {
    $navegadorPath = Localizar-Navegador
    if (-not $navegadorPath) { throw 'No se encontro Microsoft Edge, Chrome ni Brave. Instala uno de ellos para usar FACTUS.' }
    return Start-Process -FilePath $navegadorPath -ArgumentList @("--app=$url", "--user-data-dir=$profileDir", '--start-maximized', '--no-first-run', '--no-default-browser-check') -PassThru
}

try {
    if (-not (Test-Puerto $port $servidor)) {
        throw "No se pudo conectar con el servidor de FACTUS ($baseUrl).`n`nVerifica que la PC principal este encendida, que el sistema este abierto y que el nombre/IP en cliente-config.json sea correcto."
    }

    $token = [guid]::NewGuid().ToString('N')
    Set-Content -Path $tokenFile -Value $token

    $browser = Abrir-Ventana "$baseUrl/?_lanzador=$token"
    $ventana = $false
    for ($i = 0; $i -lt 60; $i++) {
        Start-Sleep -Seconds 1
        $browser.Refresh()
        if ($browser.HasExited) { break }
        if ($browser.MainWindowHandle -ne 0) { $ventana = $true; break }
    }
    if (-not $ventana) { throw 'No se pudo abrir la ventana de FACTUS.' }

    while ($true) {
        Start-Sleep -Seconds 2
        $browser.Refresh()
        if ($browser.HasExited) { break }
        if ($browser.MainWindowHandle -eq 0) {
            Start-Sleep -Seconds 3
            $browser.Refresh()
            if ($browser.HasExited -or $browser.MainWindowHandle -eq 0) { break }
        }
    }
} catch {
    Mensaje $_.Exception.Message 'Error'
} finally {
    if ($browser -and -not $browser.HasExited) { Matar-Proc $browser.Id }
    if ($token) {
        try {
            Invoke-WebRequest -Uri "$baseUrl/lanzador/cerrar-sesion" -Method Post -Body @{ token = $token } -UseBasicParsing -TimeoutSec 5 | Out-Null
        } catch { }
    }
    Remove-Item $tokenFile -Force -ErrorAction SilentlyContinue
}

exit 0
