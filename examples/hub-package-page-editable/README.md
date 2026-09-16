# Página HUB editable — ejemplo

Este ejemplo muestra el contrato mínimo para que una página diseñada con IA
siga siendo editable desde WordPress sin tocar HTML.

## Flujo

1. Comprime `manifest.json`, `index.html` y `style.css` en un ZIP.
2. Importa el ZIP desde **Constructor HUB → Importar ZIP**.
3. Publica la versión del diseño después de revisarla en preview.
4. Asigna el diseño a una Page WordPress mediante la caja **Constructor HUB**.
5. Recarga la edición de la Page: aparecerá **Contenido HUB de esta página**.
6. Edita título, descripción, CTA e imagen desde WordPress.
7. Una versión visual nueva del package puede cambiar layout/CSS sin borrar
   esos valores editoriales.

La imagen se selecciona desde Media Library y el HTML consume tanto su URL como
su texto alternativo mediante `{{CONTENT.hero.image.url}}` y
`{{CONTENT.hero.image.alt}}`.

Este ejemplo no incluye JavaScript porque no lo necesita.
