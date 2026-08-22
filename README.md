# GTK – Global Technology Knowledge Corp.

Web corporativa estática, bilingüe (ES/EN), sin frameworks ni dependencias.

## Estructura

```
index.html          raíz: detecta idioma del navegador y redirige
es/index.html       versión española   (generado)
en/index.html       versión inglesa    (generado)
assets/logos/       logotipos de los socios
sitemap.xml
robots.txt
build/              fuentes del generador
  template.html       plantilla única de la que salen las dos páginas
  content.json        todos los textos, ES y EN emparejados clave a clave
  style.css           sistema de diseño
  app.js              comportamiento (ES5, sin dependencias)
  build.ps1           generador
  serve.ps1           servidor local de desarrollo
```

`build/` está dentro del repositorio a propósito: es la única fuente de verdad
del sitio. El HTML de `es/` y `en/` es producto, no original — editarlo a mano
se pierde en la siguiente regeneración.

GitHub Pages sirve también `build/` como ficheros estáticos. No hay nada
sensible ahí (el CSS y el JS ya van incrustados en las páginas publicadas y
`content.json` es texto público), pero `robots.txt` lo excluye de la
indexación porque `template.html` se serviría con los marcadores `{{...}}` sin
resolver.

## Publicación (GitHub Pages)

Esta carpeta es la raíz que sirve GitHub Pages (rama `main`, carpeta `/root`).
Las páginas publicadas son estáticas y autocontenidas: no requieren build ni
backend en el servidor.

## Regeneración

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File build\build.ps1
```

Reescribe `es/index.html` y `en/index.html` desde la plantilla única, de modo
que los dos idiomas no puedan desincronizarse. Todas las rutas del generador
son relativas a su propia ubicación, así que el repositorio se puede clonar en
cualquier máquina y produce exactamente el mismo resultado.

Para ver el resultado en local:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File build\serve.ps1 -Port 8099
```

## Formulario de contacto (pendiente de activar)

El formulario de la sección «Escríbanos» envía por AJAX a Formspree. El
endpoint es todavía un marcador de posición:

```
https://formspree.io/f/FORM_ID
```

Para activarlo: crear una cuenta gratuita en formspree.io, dar de alta un
formulario con destino `info@globaltk.com`, copiar el id real y sustituir
`FORM_ID` en la variable `$formEndpoint` de `build/build.ps1`, luego
regenerar las páginas. Hasta entonces el formulario valida correctamente
pero los envíos no llegan a ningún buzón.

## Dominio

El `canonical`, `hreflang`, Open Graph, JSON-LD, `sitemap.xml` y `robots.txt`
usan `https://www.globaltk.com` como dominio de referencia. Si el dominio real
es otro, actualízalo en `build/content.json` / `build/build.ps1` y en
`sitemap.xml` / `robots.txt`, y vuelve a generar las páginas.

## Navegacion y nomenclatura

El menu es el acordado con el cliente, en este orden:

| # | Ancla | ES | EN |
|---|-------|----|----|
| 1 | `#gtk` | Sobre GTK | About GTK |
| 2 | `#areas` | Areas de especializacion | Areas of Expertise |
| 3 | `#socios` | Socios estrategicos | Strategic Partners |
| 4 | `#colaboraciones` | Colaboraciones destacadas | Selected Collaborations |
| 5 | `#insights` | Insights | Insights |
| 6 | `#escribenos` | Contacto | Contact |

Notas de implementacion:

- **No hay entrada "Inicio".** El logotipo (barra superior y rail) y el enlace
  "Volver arriba" del pie ya devuelven a `#inicio`, asi que una sexta entrada
  para lo mismo sobraba.
- **El h2 de cada seccion repite literalmente su etiqueta de menu.** Si se
  cambia una, hay que cambiar la otra: `nav_*` y `*_title` en `content.json`.
- **La barra inferior movil usa etiquetas cortas** (`tab_*`), porque seis
  pestanas en 375 px dan ~52 px por pestana y "Colaboraciones destacadas" no
  cabe. Mismos destinos, palabra abreviada: GTK / Areas / Socios / Proyectos /
  Insights / Contacto.
- La seccion `#colaboraciones` se llamaba `#proyectos`. El id aparece en
  `template.html`, en `markedSections` y `getElementById` de `app.js`, y en
  `$sectionKeys` de `build.ps1`: los cuatro tienen que coincidir o el
  subrayado animado del titulo deja de dispararse.

## Insights

La seccion existe en `template.html` pero **no se publica todavia**: no hay
articulos. Mientras el array `$insights` de `build/build.ps1` este vacio, el
generador borra de las paginas el bloque delimitado por
`<!-- INSIGHTS -->` / `<!-- /INSIGHTS -->` — la seccion y sus dos entradas de
menu (rail y barra movil) — para no publicar una seccion en blanco.

Para activarla, anadir una entrada al array:

```powershell
$insights = @(
  @{ key='insight1'; date='2026-09-14'; tag='analysis'; url='' }
)
```

y sus textos a `content.json`: `insight1_title`, `insight1_body` y
`insights_tag_analysis`, cada uno con sus pares `es` / `en`. Los textos viven
siempre en `content.json`, nunca en `build.ps1`, para que las dos versiones de
idioma no se puedan desincronizar. `url` vacio deja la tarjeta sin enlace.

Ya estan escritos el titulo y la entradilla de la seccion (`insights_title`,
`insights_intro`); son **texto propuesto, pendiente de validar** con el cliente.

## Terminos de uso (revision juridica pendiente)

El pie tiene un boton «Terminos de uso» que abre una ventana modal con el
texto completo, en ES y EN. Las claves son `terms_link`, `terms_title`,
`terms_updated`, `terms_close_aria` y doce pares `terms_sN_title` /
`terms_sN_body` en `build/content.json`.

**El texto es un articulado estandar de terminos de uso de sitio web, no
asesoramiento juridico.** Esta redactado para una sociedad estadounidense
(Global Technology Knowledge Corp., Union City, Nueva Jersey) y somete el
acuerdo a la ley de Nueva Jersey y a los tribunales del condado de Hudson.
Debe revisarlo un abogado de la empresa antes de publicarlo en el dominio
definitivo. Puntos que conviene que confirme:

- La ley aplicable y el foro (Nueva Jersey / condado de Hudson).
- La clausula 4, que declara que los logotipos de la cinta de socios
  pertenecen a sus titulares y se muestran con fines identificativos, sin
  implicar patrocinio. Es la que cubre el uso de esas marcas.
- La clausula 7, que describe lo que hace hoy el sitio: no instala cookies ni
  guarda nada en el navegador, el formulario se transmite por un proveedor
  externo (Formspree) y las tipografias se cargan desde Google Fonts. **Si
  cambia cualquiera de esas tres cosas, hay que actualizar la clausula.**
- Si la empresa necesita ademas una politica de privacidad separada; aqui solo
  hay la mencion minima dentro de los terminos.

La fecha de `terms_updated` se edita a mano al cambiar el texto.

El dialogo es generico: cualquier elemento con `data-modal-open="<id>"` abre
el contenedor con ese id, y cualquier `data-modal-close` dentro de el (o el
fondo, o la tecla Escape) lo cierra. El foco se lleva al panel al abrir, queda
atrapado dentro mientras esta abierto y vuelve al boton de origen al cerrar.

## Socios estrategicos pendientes de logo

Seis entidades ya figuran en la cinta de socios como placa de texto, a la
espera del logo oficial del cliente. Para activarlas: dejar el fichero en
`assets/logos/` y rellenar el campo `file` de la entrada correspondiente en
el array `$clients` de `build/build.ps1`, luego regenerar.

| Entidad (como se muestra) | Denominacion oficial | Estado |
|---------------------------|----------------------|--------|
| UCLA Extension | UCLA Extension (University of California, Los Angeles) | Nombre correcto |
| UC Davis | University of California, Davis | Nombre correcto |
| Camara de Comercio de Alava | Camara de Comercio, Industria y Servicios de Alava / Arabako Merkataritza, Industria eta Zerbitzu Ganbera | Se muestra el nombre comercial, que es el que usa la propia entidad |
| Shanghai International University (SISU) DongFang | **Sin verificar** | Ver abajo |
| Universidad Politecnica de Madrid | Universidad Politecnica de Madrid (UPM) | Nombre correcto |
| Global Alumni | Global Alumni | Nombre correcto |

**SISU DongFang, denominacion por confirmar.** La sigla SISU corresponde a
*Shanghai International **Studies** University*; "Shanghai International
University" no existe con ese nombre. No se ha encontrado ningun college
"DongFang" adscrito a SISU: el college independiente documentado de SISU es
*Xianda College of Economics and Humanities*, y el "Dongfang College" que si
aparece pertenece a la Shandong University of Finance and Economics, otra
institucion. Hay que confirmar con el cliente de que entidad se trata antes de
publicar su logo.

Al incorporarlas, la banda de datos pasa a anunciar 21 socios
(`data-count` en `build/template.html`).
