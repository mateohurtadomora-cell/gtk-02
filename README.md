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

**Aceptación de los términos.** Junto al botón de envío hay una casilla que
hay que marcar para poder enviar. Se valida como un campo más (por eso su
contenedor lleva la clase `form__field`), pero con dos particularidades: se
comprueba `checked` en lugar del texto, y solo al marcarla o desmarcarla, no
al perder el foco, porque si no daría error por el mero hecho de tabular
sobre ella. El enlace a los términos vive fuera del `<label>` a propósito:
dentro, pulsarlo marcaría la casilla en vez de abrir el modal. El envío
incluye el campo `acepta_terminos`, de modo que en Formspree queda constancia
de la aceptación.

## Contacto en el pie

El pie no publica teléfonos: el único contacto directo es
`info@globaltk.com`. Los tres números que había (NY, CA, MA) se retiraron a
petición del cliente, y con ellos las claves `contact_region_*` de
`content.json` y los `ContactPoint` telefónicos del JSON-LD, que ahora
declaran solo el correo.

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
- **La altura de la barra superior es la variable `--topbar`.** La usan el
  hueco que el `body` deja arriba, el `scroll-margin-top` de cada seccion y el
  `padding-top` de la portada, que antes repetian un `70px` suelto cada uno. Si
  cambia el tamano de la marca hay que recalcularla: marca + 14 px de padding
  arriba y abajo + el borde. Por debajo de 420 px la marca se reduce y la
  variable baja con ella, porque si no la marca y el conmutador ES/EN se tocan
  (el subtitulo va en `nowrap` y no puede encoger).
- **El rail mide `--rail`, y su ancho depende del ancho de la marca.** Con la
  marca a 52 px, "Global Technology Knowledge" en una linea necesita unos
  240 px de contenido; el rail deja 268 utiles.
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

## Escala del ecosistema (anillos)

Las colaboraciones se organizan por la escala del ecosistema en el que se
trabajó, que es el elemento propio de la propuesta A: cuatro anillos
concéntricos, de Local (el círculo interior) a Global (el exterior). Al elegir
una escala se encienden esa y todas las interiores, porque el alcance es
acumulativo: un ecosistema nacional contiene al regional y al local.

Este control sustituye a la fila de píldoras temáticas que había antes. La
categoría no se ha perdido: sigue impresa en cada tarjeta (`card__cat`), y sus
textos siguen siendo las claves `filter_*` de `content.json`. Se descartó
mantener los dos filtros a la vez porque catorce colaboraciones repartidas
entre seis categorías y cuatro escalas dejan casi todas las combinaciones
vacías.

El reparto vive en una sola línea de `build/build.ps1`, dentro de
`Build-ProjectCards`:

```powershell
$levels = @(0,2,3,1,0,0,2,3,3,3,3,1,3,2)
```

Es el orden de las colaboraciones 01 a 14, con `0` Local, `1` Regional,
`2` Nacional y `3` Global. Sale de la clasificación que ya llevaba la propuesta
A; **conviene que el cliente la revise**, porque es un criterio editorial, no
un dato. Cambiar un número y regenerar es todo lo que hace falta: los
contadores de cada botón se calculan solos a partir de las tarjetas.

Reparto actual: Local 3, Regional 2, Nacional 3, Global 6.

Los textos son las claves `scope_*` de `content.json`. Cada escala tiene su
propia descripción (`scope_local_desc`, `scope_regional_desc`, ...); al elegir
una, el párrafo cambia de `data-i18n`, de modo que el conmutador de idioma
repone la descripción de la escala elegida y no la de «todas».

## Paleta

Los dos temas viven en variables CSS al principio de `build/style.css`: el
claro en `:root` y el oscuro en el bloque `@media (prefers-color-scheme:
dark)`. No hay conmutador manual; se sigue la preferencia del sistema.

En agosto de 2026 se bajó de tono el tema claro y se subió el oscuro, a
petición del cliente. Al mover una paleta entera hay dos sitios donde el
contraste se rompe con facilidad:

- **Rellenos de acento con texto encima.** `.btn--primary`,
  `.lang__btn[aria-pressed="true"]` y `.row__num--accent` pintan blanco sobre
  `--accent`. En el tema oscuro el acento es un azul claro, así que ahí el
  texto pasa a tinta oscura mediante una regla propia. Con blanco daba 2,9:1.
- **`--danger`.** El rojo del tema claro sobre las superficies aclaradas del
  oscuro se quedaba en 2,5:1, así que el tema oscuro tiene su propio rojo
  claro. Es la única variable que existía solo en `:root`.

Si se vuelve a tocar la paleta conviene repasar los dos temas con un medidor
de contraste, incluidos el modal de términos abierto y el formulario con los
errores pintados, que son estados que no se ven en la primera pantalla. La
referencia actual: el peor par del tema claro está en 5,8:1 y el del oscuro
en 5,2:1, ambos por encima del 4,5:1 que pide WCAG AA.

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

## Socios estrategicos: logos

Los veintiun socios de la cinta tienen ya su logo. La banda de datos anuncia
esa misma cifra (`data-count` en `build/template.html`); si se anade o quita
un socio hay que actualizarla a mano.

Para incorporar uno nuevo: dejar el fichero en `assets/logos/` y rellenar el
campo `file` de su entrada en el array `$clients` de `build/build.ps1`, luego
regenerar. El ancho y el alto intrinsecos se leen del propio fichero, asi que
no hay medidas que tocar. Si `file` se deja vacio, la entrada sale como placa
de texto en vez de dar una imagen rota.

**Convencion de los ficheros.** Recortados al contenido, sin margen blanco
propio (la tarjeta ya pone 14x22 px de aire), y con el alto normalizado a
180 px como maximo. Los seis logos que llego en agosto de 2026 venian con
mucho blanco alrededor -- entre el 10 % y el 65 % de la imagen era contenido
util -- y se recortaron antes de entrar. Un logo sin recortar se ve
diminuto en la cinta, porque `object-fit:contain` escala el fichero entero,
blanco incluido.

**SISU, denominacion por confirmar.** Se muestra como "Shanghai International
University (SISU) DongFang", pero el sello que envio el cliente es el de
*Shanghai International **Studies** University* (los caracteres del circulo
son los de esa universidad, con la fecha 1949). "Shanghai International
University" no existe con ese nombre, y no se ha encontrado ningun college
"DongFang" adscrito a SISU: el college independiente documentado de SISU es
*Xianda College of Economics and Humanities*, y el "Dongfang College" que si
aparece pertenece a la Shandong University of Finance and Economics, otra
institucion. El logo ya esta publicado; lo que falta por decidir es el nombre
con el que aparece.

**Camara de Comercio de Alava.** El fichero es solo el monograma (la "C" roja
con la "a"), sin el nombre de la entidad. Pasa lo mismo con Vantage Learning.
Funciona en la cinta porque hay un `title` con el nombre completo, pero quien
no conozca la marca no la identifica de un vistazo.

**Denominaciones verificadas.**

| Entidad (como se muestra) | Denominacion oficial |
|---------------------------|----------------------|
| UCLA Extension | UCLA Extension (University of California, Los Angeles) |
| UC Davis | University of California, Davis |
| Camara de Comercio de Alava | Camara de Comercio, Industria y Servicios de Alava / Arabako Merkataritza, Industria eta Zerbitzu Ganbera. Se muestra el nombre comercial, que es el que usa la propia entidad |
| Universidad Politecnica de Madrid | Universidad Politecnica de Madrid (UPM) |
| Global Alumni | Global Alumni |
