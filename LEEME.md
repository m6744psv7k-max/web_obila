# Web Óbila Club de Basket · publicación automática en GitHub

Qué hace: la web se publica gratis en GitHub Pages y cada 3 horas un proceso
lee la clasificación, los resultados y el próximo partido del primer equipo
(Tercera FEB) y busca noticias nuevas del club. No hay que tocar nada.

## Puesta en marcha (unos 10 minutos)

1. En github.com pulsa **New repository**. Nombre: `web-obila`. Déjalo en **Public** y crea el repositorio.
2. Descomprime este zip. En el repositorio pulsa **Add file → Upload files** y arrastra estos archivos:
   `index.html`, `data.json`, `LEEME.md` y la carpeta `scripts` (con `update.py`). Pulsa **Commit changes**.
3. Pulsa **Add file → Create new file**. En el nombre escribe exactamente
   `.github/workflows/actualizar.yml` (al escribir las barras se crean las carpetas).
   Pega dentro todo el contenido del archivo `actualizar.yml` de este zip y pulsa **Commit changes**.
4. Ve a **Settings → Pages**. En **Source** elige **GitHub Actions**.
5. Ve a la pestaña **Actions**, abre **Actualizar y publicar web** y pulsa **Run workflow**.
6. Cuando termine (unos 2 minutos), tu web estará en `https://TU-USUARIO.github.io/web-obila/`.

## Cómo funciona
- Cada 3 horas se ejecuta solo. Si la FEB cambia algo, `data.json` se actualiza y la web se vuelve a publicar.
- El pie de la web muestra la fecha y hora de la última actualización.
- Si la FEB cambia el diseño de su página y el script no puede leerla, la pestaña Actions
  muestra la ejecución en rojo y GitHub te envía un correo. La web sigue mostrando los últimos datos buenos.

## Qué NO es automático todavía
- **Cantera (FBCyL):** sus resultados se publican en un formato (PDF) que todavía no se lee solo.
  Mientras tanto se mantiene el último dato cargado y las fichas avisan de que está pendiente de actualizar.
  Para automatizarlo hace falta un PDF de resultados de un grupo de ejemplo.
- **Noticias:** las nuevas aparecen solo con titular, medio y enlace. Los resúmenes
  escritos a mano están en `data.json`, en `news_manual`.

## Qué cambia respecto a la versión de Claude
Fuera de Claude no existe el panel de administración. Por eso no funcionan: la subida de fotos a la galería,
las fichas de plantilla, el formulario de inscripciones (muestra el correo y el teléfono del club) y
los patrocinadores añadidos desde el panel. Los logos de patrocinadores se incluyen enviándome las imágenes.
