param([int]$Port = 8099, [string]$Root = '')

# Por defecto sirve la carpeta que contiene a build/, o sea la raiz del sitio
# publicado. Antes era la ruta absoluta de una maquina concreta.
if([string]::IsNullOrEmpty($Root)){ $Root = Split-Path -Parent $PSScriptRoot }

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://localhost:$Port/")
$listener.Start()
Write-Host "Serving $Root on http://localhost:$Port/"

$mime = @{
  '.html'='text/html; charset=utf-8'; '.css'='text/css'; '.js'='application/javascript'
  '.xml'='application/xml'; '.txt'='text/plain'; '.svg'='image/svg+xml'; '.json'='application/json'
}

while ($listener.IsListening) {
  $ctx = $listener.GetContext()
  $req = $ctx.Request
  $res = $ctx.Response
  try {
    $path = $req.Url.AbsolutePath
    if ($path -eq '/') { $path = '/index.html' }
    if ($path.EndsWith('/')) { $path = $path + 'index.html' }
    $fullPath = Join-Path $Root ($path.TrimStart('/') -replace '/', '\')
    if (Test-Path $fullPath -PathType Leaf) {
      $ext = [System.IO.Path]::GetExtension($fullPath)
      $ct = $mime[$ext]
      if (-not $ct) { $ct = 'application/octet-stream' }
      $bytes = [System.IO.File]::ReadAllBytes($fullPath)
      $res.ContentType = $ct
      $res.ContentLength64 = $bytes.Length
      $res.OutputStream.Write($bytes, 0, $bytes.Length)
    } else {
      $res.StatusCode = 404
      $msg = [System.Text.Encoding]::UTF8.GetBytes("404 Not Found: $path")
      $res.OutputStream.Write($msg, 0, $msg.Length)
    }
  } catch {
    $res.StatusCode = 500
  } finally {
    $res.OutputStream.Close()
  }
}
