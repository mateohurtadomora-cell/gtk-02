$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
# Raiz del sitio publicado: la carpeta que contiene a build/. Todo cuelga de
# aqui y nunca de una ruta absoluta, para que el repositorio se pueda clonar
# en cualquier maquina y generar exactamente lo mismo.
$site = Split-Path -Parent $root
$domain = 'https://www.globaltk.com'
# Formspree endpoint for the contact form. Replace FORM_ID with the real id
# from formspree.io (free account, delivers submissions to info@globaltk.com).
$formEndpoint = 'https://formspree.io/f/FORM_ID'

function Escape-Html([string]$s){
  if([string]::IsNullOrEmpty($s)){ return '' }
  $s = $s.Replace('&','&amp;')
  $s = $s.Replace('<','&lt;')
  $s = $s.Replace('>','&gt;')
  return $s
}
function Escape-Attr([string]$s){
  $s = Escape-Html $s
  $s = $s.Replace('"','&quot;')
  return $s
}
function JsStr([string]$s){
  if([string]::IsNullOrEmpty($s)){ return '' }
  $s = $s.Replace('\','\\')
  $s = $s.Replace('"','\"')
  $s = $s.Replace("`r`n","\n").Replace("`n","\n")
  return $s
}
function Fmt1([double]$n){ return [string]::Format([System.Globalization.CultureInfo]::InvariantCulture, '{0:0.0}', $n) }

$content = Get-Content -Raw -Path (Join-Path $root 'content.json') -Encoding UTF8 | ConvertFrom-Json

# ---------------------------------------------------------------------------
# 1) Hero node-network SVG (deterministic, fixed seed)
# ---------------------------------------------------------------------------
function Build-HeroSvg {
  $rng = New-Object System.Random(42)
  $n = 26
  $margin = 40.0
  $minDist = 62.0
  $pts = New-Object System.Collections.ArrayList

  for($i=0; $i -lt $n; $i++){
    $placed = $false
    $tries = 0
    while(-not $placed -and $tries -lt 800){
      $x = $margin + $rng.NextDouble() * (560.0 - 2*$margin)
      $y = $margin + $rng.NextDouble() * (560.0 - 2*$margin)
      $ok = $true
      foreach($p in $pts){
        $dx = $p.x - $x; $dy = $p.y - $y
        if([Math]::Sqrt($dx*$dx+$dy*$dy) -lt $minDist){ $ok = $false; break }
      }
      if($ok){
        [void]$pts.Add(@{ x = $x; y = $y })
        $placed = $true
      }
      $tries++
    }
    if(-not $placed){
      # relax: place anyway at last tried point to guarantee 26 nodes
      [void]$pts.Add(@{ x = $x; y = $y })
    }
  }

  # nearest neighbours -> edges (max 3 per node, dist < 170), no duplicate pairs
  $edgeSet = New-Object System.Collections.Generic.HashSet[string]
  $edges = New-Object System.Collections.ArrayList
  $degree = New-Object int[] $n

  for($i=0; $i -lt $n; $i++){
    $dists = New-Object System.Collections.ArrayList
    for($j=0; $j -lt $n; $j++){
      if($i -eq $j){ continue }
      $dx = $pts[$i].x - $pts[$j].x; $dy = $pts[$i].y - $pts[$j].y
      $d = [Math]::Sqrt($dx*$dx+$dy*$dy)
      if($d -lt 170){ [void]$dists.Add(@{ j = $j; d = $d }) }
    }
    $sorted = $dists | Sort-Object { $_.d } | Select-Object -First 3
    foreach($e in $sorted){
      $a = [Math]::Min($i, $e.j); $b = [Math]::Max($i, $e.j)
      $key = "$a-$b"
      if(-not $edgeSet.Contains($key)){
        [void]$edgeSet.Add($key)
        [void]$edges.Add(@{ a = $a; b = $b })
        $degree[$a]++; $degree[$b]++
      }
    }
  }

  # Marker hubs must stay near the canvas centre so they are never cropped
  # out of view by the smaller/narrower on-screen viewport (the background
  # SVG is centred and scaled, so only a central portion is ever visible).
  # "gtk" is intentionally left out: its title is long and wraps to
  # multiple lines, which reads better without an underline.
  $sectionKeys = @('areas','socios','colaboraciones')
  $hubIdx = @(0..($n-1) | Sort-Object { [Math]::Sqrt(($pts[$_].x-280)*($pts[$_].x-280) + ($pts[$_].y-280)*($pts[$_].y-280)) } | Select-Object -First $sectionKeys.Count)
  $hubSet = New-Object System.Collections.Generic.HashSet[int]
  foreach($h in $hubIdx){ [void]$hubSet.Add($h) }

  # Each hub is bound to one section title; its dot "becomes" that title's
  # underline the first time the section is scrolled into view.
  $sectionOfHub = @{}
  for($si2=0; $si2 -lt $hubIdx.Count; $si2++){ $sectionOfHub[$hubIdx[$si2]] = $sectionKeys[$si2] }

  $sb = New-Object System.Text.StringBuilder
  [void]$sb.Append('<svg viewBox="0 0 560 560" role="img" aria-labelledby="heroSvgTitle">')
  [void]$sb.Append('<title id="heroSvgTitle" data-i18n="hero_svg_title">{{t:hero_svg_title}}</title>')
  [void]$sb.Append('<defs><radialGradient id="heroGrad" cx="50%" cy="50%" r="65%"><stop offset="0%" stop-color="var(--accent)" stop-opacity=".18"/><stop offset="100%" stop-color="var(--accent)" stop-opacity="0"/></radialGradient></defs>')
  [void]$sb.Append('<rect x="0" y="0" width="560" height="560" fill="url(#heroGrad)"/>')

  [void]$sb.Append('<g class="edges">')
  $ei = 0
  foreach($e in $edges){
    $delay = Fmt1((($ei % 9) * 0.32))
    $x1 = Fmt1($pts[$e.a].x); $y1 = Fmt1($pts[$e.a].y)
    $x2 = Fmt1($pts[$e.b].x); $y2 = Fmt1($pts[$e.b].y)
    [void]$sb.Append('<line class="edge" style="--d:' + $delay + 's" x1="' + $x1 + '" y1="' + $y1 + '" x2="' + $x2 + '" y2="' + $y2 + '"/>')
    $ei++
  }
  [void]$sb.Append('</g>')

  [void]$sb.Append('<g class="nodes">')
  for($i=0; $i -lt $n; $i++){
    if($hubSet.Contains($i)){ continue }
    $x = Fmt1($pts[$i].x); $y = Fmt1($pts[$i].y)
    $delay = Fmt1((($i % 7) * 0.4))
    $dur = Fmt1((7.0 + ($i % 5)))
    $rIdx = $i % 3
    $r = @('2.6','3.2','4.0')[$rIdx]
    [void]$sb.Append('<circle class="node node--anim" style="--d:' + $delay + 's;--t:' + $dur + 's" cx="' + $x + '" cy="' + $y + '" r="' + $r + '"/>')
  }
  [void]$sb.Append('</g>')

  [void]$sb.Append('<g class="markers">')
  foreach($h in $hubIdx){
    $x = Fmt1($pts[$h].x); $y = Fmt1($pts[$h].y)
    $delay = Fmt1((($h % 7) * 0.4))
    $dur = Fmt1((7.0 + ($h % 5)))
    $sec = $sectionOfHub[$h]
    [void]$sb.Append('<circle class="node__halo node__halo--marker" style="--d:' + $delay + 's" cx="' + $x + '" cy="' + $y + '" r="15"/>')
    [void]$sb.Append('<circle class="node node--hub node--marker node--anim" style="--d:' + $delay + 's;--t:' + $dur + 's" cx="' + $x + '" cy="' + $y + '" r="8.6" data-section="' + $sec + '"/>')
  }
  [void]$sb.Append('</g>')
  [void]$sb.Append('</svg>')
  return $sb.ToString()
}

# ---------------------------------------------------------------------------
# 3) Project cards
# ---------------------------------------------------------------------------
function Build-ProjectCards {
  $cats = @('cities','academic','academic','accreditation','research','research','research','research','international','international','accreditation','cities','accreditation','corporate')
  $filterKeyOf = @{
    'cities' = 'filter_cities'; 'academic' = 'filter_academic'; 'accreditation' = 'filter_accreditation'
    'research' = 'filter_research'; 'international' = 'filter_international'; 'corporate' = 'filter_corporate'
  }
  # Escala del ecosistema de cada colaboracion: 0 Local, 1 Regional,
  # 2 Nacional, 3 Global. Es la clasificacion de la propuesta A, donde los
  # anillos ya salian con este reparto; se toca aqui y en ningun otro sitio.
  $levels = @(0,2,3,1,0,0,2,3,3,3,3,1,3,2)
  $sb = New-Object System.Text.StringBuilder
  for($i=1; $i -le 14; $i++){
    $cat = $cats[$i-1]
    $fk = $filterKeyOf[$cat]
    $num = '{0:00}' -f $i
    [void]$sb.Append('<article class="card" data-cat="' + $cat + '" data-level="' + $levels[$i-1] + '" data-i="' + $i + '">')
    [void]$sb.Append('<div class="card__top"><span class="card__cat" data-i18n="' + $fk + '">{{t:' + $fk + '}}</span><span class="card__i">' + $num + '</span></div>')
    [void]$sb.Append('<h3 class="card__title" data-i18n="project' + $i + '_title">{{t:project' + $i + '_title}}</h3>')
    [void]$sb.Append('<p class="card__desc" data-i18n="project' + $i + '_desc">{{t:project' + $i + '_desc}}</p>')
    [void]$sb.Append('</article>')
  }
  return $sb.ToString()
}

# ---------------------------------------------------------------------------
# 4) Client logos ribbon
# ---------------------------------------------------------------------------
$logoDir = Join-Path $site 'assets\logos'
Add-Type -AssemblyName System.Drawing

# Intrinsic size is read off the file rather than hardcoded, so replacing a
# logo never leaves a stale width/height attribute behind (which would show up
# as a squashed mark or a layout shift on load).
function Logo-Dims([string]$file){
  # An empty file name marks a partner whose official logo has not arrived yet;
  # it renders as a text placard on purpose, so it is not a build problem.
  if([string]::IsNullOrEmpty($file)){ return $null }
  $path = Join-Path $logoDir $file
  if(-not (Test-Path -LiteralPath $path)){
    Write-Warning "Logo no encontrado: $file"
    return $null
  }
  $img = [System.Drawing.Image]::FromFile($path)
  $dims = @{ w = $img.Width; h = $img.Height }
  $img.Dispose()
  return $dims
}

function Build-ClientLogos([bool]$dup){
  $clients = @(
    @{ name='MIT Industrial Liaison Program'; file='mit-ilp.png'; p=@('1','5') },
    @{ name='Excelsior College'; file='excelsior.png'; p=@('11') },
    @{ name='UC Irvine Extension'; file='uci-extension.jpg'; p=@('9') },
    @{ name='Boston University Metropolitan College'; file='bu-metropolitan.png'; p=@('10') },
    @{ name='University of Chicago Graham School'; file='chicago-graham.png'; p=@('6','8') },
    @{ name='Vantage Learning'; file='vantage-learning.jpg'; p=$null },
    @{ name='b_TEC Barcelona'; file='btec-barcelona.png'; p=@('1') },
    @{ name=('Fundaci' + [char]0x00F3 + 'n Madrid+D'); file='madrid-mas-d.png'; p=$null },
    @{ name='Universidad San Pablo CEU'; file='san-pablo-ceu.png'; p=@('2') },
    @{ name='IL3 Universitat de Barcelona'; file='il3-ub.png'; p=@('3') },
    @{ name='Universidad de Cantabria'; file='cantabria.png'; p=$null },
    @{ name='Universidad de Extremadura'; file='extremadura.png'; p=$null },
    @{ name='ACAP Madrid'; file='acap-madrid.jpg'; p=@('4') },
    @{ name=('EIN Bogot' + [char]0x00E1); file='ein-bogota.png'; p=@('10') },
    @{ name='Azuero'; file='azuero.png'; p=$null },

    # --- Socios incorporados en agosto de 2026 ---------------------------
    # Los seis logos llegaron del cliente con mucho blanco alrededor y se
    # recortaron al contenido, como los demas de la cinta. La denominacion de
    # SISU sigue por confirmar (ver README): el sello que envio el cliente es
    # el de Shanghai International *Studies* University, sin rastro de
    # "DongFang".
    @{ name='UCLA Extension'; file='ucla-extension.png'; p=$null },
    @{ name='UC Davis'; file='uc-davis.png'; p=$null },
    @{ name=('C' + [char]0x00E1 + 'mara de Comercio de ' + [char]0x00C1 + 'lava'); file='camara-alava.png'; p=$null },
    @{ name='Shanghai International University (SISU) DongFang'; file='sisu.png'; p=$null },
    @{ name=('Universidad Polit' + [char]0x00E9 + 'cnica de Madrid'); file='upm.png'; p=$null },
    @{ name='Global Alumni'; file='global-alumni.png'; p=$null }
  )
  $sb = New-Object System.Text.StringBuilder
  foreach($c in $clients){
    $extraCls = ''
    $extraAttr = ''
    if($dup){ $extraCls = ' logo__dup'; $extraAttr = ' aria-hidden="true" tabindex="-1"' }
    $d = Logo-Dims $c.file
    if($null -eq $d){
      # Missing file: fall back to the text placard rather than a broken image.
      $inner = Escape-Html $c.name
    } else {
      $alt = ''
      if(-not $dup){ $alt = Escape-Attr $c.name }
      # No loading="lazy" here on purpose: the ribbon scrolls by transform, not
      # by the viewport, so the duplicated half would never enter the lazy
      # loader's view and would show as gaps mid-marquee.
      $inner = '<img class="logo__img" src="../assets/logos/' + $c.file + '" alt="' + $alt + '" width="' + $d.w + '" height="' + $d.h + '" decoding="async">'
    }
    if($c.p){
      $pAttr = [string]::Join(',', $c.p)
      [void]$sb.Append('<button type="button" class="logo__btn' + $extraCls + '" data-client="' + (Escape-Attr $c.name) + '" data-p="' + $pAttr + '" title="' + (Escape-Attr $c.name) + '"' + $extraAttr + '>' + $inner + '</button>')
    } else {
      [void]$sb.Append('<span class="logo__btn is-static' + $extraCls + '" title="' + (Escape-Attr $c.name) + '"' + $extraAttr + '>' + $inner + '</span>')
    }
  }
  return $sb.ToString()
}

# ---------------------------------------------------------------------------
# 5b) Insights
# ---------------------------------------------------------------------------
# La seccion Insights forma parte del menu acordado con el cliente pero
# todavia no tiene articulos. Mientras esta lista este vacia, el bloque
# <!-- INSIGHTS --> ... <!-- /INSIGHTS --> de template.html se elimina de las
# paginas generadas (la seccion y sus dos entradas de menu), de modo que nunca
# se publica una seccion en blanco. Para activarla basta con anadir aqui una
# entrada y sus textos en content.json:
#
#   @{ key='insight1'; date='2026-09-14'; tag='analysis'; url='' }
#
# 'key' apunta a los pares bilingues insight1_title / insight1_body de
# content.json (los textos viven siempre en content.json, nunca aqui, para que
# las dos versiones de idioma no se puedan desincronizar). 'tag' apunta a
# insights_tag_<tag>. 'url' vacio deja la tarjeta sin enlace.
$insights = @()

function Build-InsightCards {
  $sb = New-Object System.Text.StringBuilder
  foreach($it in $insights){
    $k = $it.key
    [void]$sb.Append('      <li class="insight rv">')
    [void]$sb.Append('<p class="insight__meta">')
    if($it.tag){
      [void]$sb.Append('<span class="insight__tag" data-i18n="insights_tag_' + $it.tag + '">{{t:insights_tag_' + $it.tag + '}}</span>')
    }
    if($it.date){
      # <time> keeps the machine-readable date next to the printed one.
      [void]$sb.Append('<time datetime="' + $it.date + '">' + $it.date + '</time>')
    }
    [void]$sb.Append('</p>')
    [void]$sb.Append('<h3 class="insight__title">')
    if($it.url){
      [void]$sb.Append('<a href="' + (Escape-Attr $it.url) + '" data-i18n="' + $k + '_title">{{t:' + $k + '_title}}</a>')
    } else {
      [void]$sb.Append('<span data-i18n="' + $k + '_title">{{t:' + $k + '_title}}</span>')
    }
    [void]$sb.Append('</h3>')
    [void]$sb.Append('<p class="insight__body" data-i18n="' + $k + '_body">{{t:' + $k + '_body}}</p>')
    [void]$sb.Append("</li>`n")
  }
  return $sb.ToString()
}


# ---------------------------------------------------------------------------
# 5) Favicon data URI (fixed colors, not theme vars)
# ---------------------------------------------------------------------------
function Build-FaviconUri {
  $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="#0B2A4A" fill-rule="evenodd" d="M68.396 16.488 A14.807 14.807 0 1 1 83.512 31.604 A18.400 18.400 0 0 0 83.512 68.396 A14.807 14.807 0 1 1 68.396 83.512 A18.400 18.400 0 0 0 31.604 83.512 A14.807 14.807 0 1 1 16.488 68.396 A18.400 18.400 0 0 0 16.488 31.604 A14.807 14.807 0 1 1 31.604 16.488 A18.400 18.400 0 0 0 68.396 16.488 Z M58.1 50a8.1 8.1 0 1 0-16.2 0a8.1 8.1 0 1 0 16.2 0Z"/><g fill="#B9BCC2"><circle cx="50" cy="18.5" r="12.9"/><circle cx="81.5" cy="50" r="12.9"/><circle cx="50" cy="81.5" r="12.9"/><circle cx="18.5" cy="50" r="12.9"/></g></svg>'
  $encoded = [System.Uri]::EscapeDataString($svg)
  return 'data:image/svg+xml,' + $encoded
}

# ---------------------------------------------------------------------------
# 6) I18N JS data block
# ---------------------------------------------------------------------------
function Build-I18nJs {
  $keys = $content.PSObject.Properties.Name
  $sb = New-Object System.Text.StringBuilder
  [void]$sb.Append("var I18N = {`n")
  foreach($lang in @('es','en')){
    [void]$sb.Append("  $lang`: {`n")
    $count = $keys.Count
    $idx = 0
    foreach($k in $keys){
      $idx++
      $v = $content.$k.$lang
      $comma = if($idx -lt $count){ ',' } else { '' }
      [void]$sb.Append('    ' + $k + ': "' + (JsStr $v) + '"' + $comma + "`n")
    }
    $langComma = if($lang -eq 'es'){ ',' } else { '' }
    [void]$sb.Append("  }$langComma`n")
  }
  [void]$sb.Append("};`n")
  return $sb.ToString()
}

# ---------------------------------------------------------------------------
# Assemble
# ---------------------------------------------------------------------------
$templateRaw = Get-Content -Raw -Path (Join-Path $root 'template.html') -Encoding UTF8
$cssRaw = Get-Content -Raw -Path (Join-Path $root 'style.css') -Encoding UTF8
$jsRaw = Get-Content -Raw -Path (Join-Path $root 'app.js') -Encoding UTF8

$i18nJs = Build-I18nJs
$jsRaw = $jsRaw.Replace('/*__I18N_DATA__*/', $i18nJs)

$heroSvg = Build-HeroSvg
$cards = Build-ProjectCards
$logos = Build-ClientLogos $false
$logosDup = Build-ClientLogos $true
$favicon = Build-FaviconUri
$insightCards = Build-InsightCards

$keys = $content.PSObject.Properties.Name

foreach($lang in @('es','en')){
  $other = if($lang -eq 'es'){ 'en' } else { 'es' }
  $ogLocale = if($lang -eq 'es'){ 'es_ES' } else { 'en_US' }

  $html = $templateRaw
  # Con $insights vacia se borran de la pagina la seccion Insights y sus dos
  # entradas de menu; con articulos, solo se borran las marcas de comentario.
  if($insights.Count -gt 0){
    $html = [regex]::Replace($html, '<!--\s*/?INSIGHTS\s*-->\r?\n?', '')
  } else {
    $html = [regex]::Replace($html, '(?s)<!--\s*INSIGHTS\s*-->.*?<!--\s*/INSIGHTS\s*-->\r?\n?', '')
  }
  $html = $html.Replace('{{INSIGHT_CARDS}}', $insightCards)
  $html = $html.Replace('{{CSS}}', $cssRaw)
  $html = $html.Replace('{{JS}}', $jsRaw)
  $html = $html.Replace('{{HERO_SVG}}', $heroSvg)
  $html = $html.Replace('{{PROJECT_CARDS}}', $cards)
  $html = $html.Replace('{{CLIENT_LOGOS_DUP}}', $logosDup)
  $html = $html.Replace('{{CLIENT_LOGOS}}', $logos)

  $html = $html.Replace('{{FORM_ENDPOINT}}', $formEndpoint)
  $html = $html.Replace('{{DOMAIN}}', $domain)
  $html = $html.Replace('{{LANG}}', $lang)
  $html = $html.Replace('{{OTHERLANG}}', $other)
  $html = $html.Replace('{{OGLOCALE}}', $ogLocale)
  $html = $html.Replace('{{FAVICON_URI}}', $favicon)
  $html = $html.Replace('{{ESPRESSED}}', $(if($lang -eq 'es'){'true'}else{'false'}))
  $html = $html.Replace('{{ENPRESSED}}', $(if($lang -eq 'en'){'true'}else{'false'}))

  foreach($k in $keys){
    $val = $content.$k.$lang
    $html = $html.Replace('{{t:' + $k + '}}', (Escape-Html $val))
    $html = $html.Replace('{{h:' + $k + '}}', $val)
    $html = $html.Replace('{{a:' + $k + '}}', (Escape-Attr $val))
  }

  $leftover = [regex]::Matches($html, '\{\{[a-zA-Z:_]+\}\}')
  if($leftover.Count -gt 0){
    Write-Warning "Unresolved tokens in $lang page:"
    $leftover | ForEach-Object { Write-Warning $_.Value } | Select-Object -Unique
  }

  $outDir = Join-Path $site $lang
  if(-not (Test-Path $outDir)){ New-Item -ItemType Directory -Path $outDir -Force | Out-Null }
  $outPath = Join-Path $outDir 'index.html'
  [System.IO.File]::WriteAllText($outPath, $html, (New-Object System.Text.UTF8Encoding($false)))
  Write-Host "Wrote $outPath"
}
