# GTK – Global Technology Knowledge Corp.

Web corporativa estática, bilingüe (ES/EN), sin frameworks ni paso de build.

## Estructura

```
index.html    raíz: detecta idioma del navegador y redirige
es/index.html versión española
en/index.html versión inglesa
sitemap.xml
robots.txt
```

## Publicación (GitHub Pages)

Esta carpeta es la raíz que sirve GitHub Pages (rama `main`, carpeta `/root`).
No requiere build ni backend: son ficheros estáticos autocontenidos.

## Código fuente / regeneración

`es/index.html` y `en/index.html` se generan desde una plantilla única en
`../build/` (`template.html`, `style.css`, `app.js`, `content.json`) mediante
`../build/build.ps1`, para que ambos idiomas no puedan desincronizarse. Esa
carpeta `build/` vive fuera de este repo; si quieres versionarla también,
cópiala dentro y ajusta las rutas de salida en `build.ps1`.

## Formulario de contacto (pendiente de activar)

El formulario de la sección «Escríbanos» envía por AJAX a Formspree. El
endpoint es todavía un marcador de posición:

```
https://formspree.io/f/FORM_ID
```

Para activarlo: crear una cuenta gratuita en formspree.io, dar de alta un
formulario con destino `info@globaltk.com`, copiar el id real y sustituir
`FORM_ID` en la variable `$formEndpoint` de `../build/build.ps1`, luego
regenerar las páginas. Hasta entonces el formulario valida correctamente
pero los envíos no llegan a ningún buzón.

## Dominio

El `canonical`, `hreflang`, Open Graph, JSON-LD, `sitemap.xml` y `robots.txt`
usan `https://www.globaltk.com` como dominio de referencia. Si el dominio real
es otro, actualízalo en `build/content.json` / `build/build.ps1` y en
`sitemap.xml` / `robots.txt`, y vuelve a generar las páginas.
