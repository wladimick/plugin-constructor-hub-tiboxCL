# HUB Theme (opcional)

Chasis WordPress ultraliviano para sitios cuya presentación ya administra
Constructor HUB.

**No es un requisito.** Constructor HUB está diseñado para funcionar sobre el
theme que el sitio ya tiene —Hello Elementor, un theme de cliente, cualquiera— y
esa sigue siendo la ruta recomendada durante la migración. Este theme existe para
el final del recorrido: cuando un sitio ya no necesita nada del theme anterior y
mantenerlo solo añade CSS, JavaScript y superficie de actualización.

## Qué aporta

- `header.php`, `footer.php`, `index.php`, `singular.php` y `404.php`: el mínimo
  para que WordPress sirva blog, páginas, archivos, búsqueda y errores.
- Los hooks intactos: `wp_head()`, `wp_body_open()` y `wp_footer()` en su lugar,
  de modo que Rank Math, GTM y cualquier snippet global siguen igual.
- Header y Footer delegados a las regiones de Constructor HUB.
- `theme.json` que expone los tokens `--hub-*` como paleta del editor de bloques.

## Qué NO aporta, a propósito

- Identidad visual. Los colores, la tipografía y el espaciado vienen del Design
  System del plugin.
- Componentes. Viven en Constructor HUB, que es lo que permite moverlos entre
  sitios y versionarlos.
- Opciones de personalización propias. Cada opción que se añada aquí es una que
  habrá que migrar el día que el theme cambie.

Si Constructor HUB se desactiva, el sitio sigue navegable con una cabecera de
respaldo mínima. Eso es deliberado: cambiar de theme deja de ser una decisión
irreversible.

## Instalación

```bash
cd theme
zip -r hub-theme.zip hub-theme
```

Después: **Apariencia → Temas → Añadir nuevo → Subir tema**.

## Antes de activarlo

Lista de verificación mínima al migrar desde Hello Elementor o similar:

1. Header y Footer HUB publicados y probados en modo `inject`.
2. Ninguna página con Elementor pendiente según el
   **Mapa de migración**, o Elementor todavía activo para las que quedan.
3. Menús asignados a las ubicaciones `primary`, `footer` o `secondary`.
4. Logo del sitio configurado en el personalizador.
5. Widgets del theme anterior inventariados: este theme no registra áreas de
   widgets.
6. Core Web Vitals medidos antes del cambio, para poder comparar.

Una vez activado, las regiones pueden pasarse de `inject` a `replace` sin tocar
los componentes.
