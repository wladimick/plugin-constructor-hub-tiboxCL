# ADR-0006 — Content Schema separado de la presentación

- Estado: Aceptada
- Fecha: 2026-09-15
- Rama: `feat/hub-content-schema`
- Base: `feat/page-assignment`
- Depende de: ADR-0003, ADR-0004 y ADR-0005

## Contexto

Constructor HUB ya puede versionar HTML/CSS/JS, importar paquetes generados por
IA y asignar un diseño HUB a una Page WordPress existente sin cambiar su URL.
Quedaba un problema operativo: si el copy, una imagen o un enlace viven
hardcodeados dentro del HTML generado por IA, una persona de contenidos debe
editar código para hacer un cambio editorial simple.

Eso contradice el objetivo de usar WordPress como backend mantenible. También
acopla contenido y presentación: publicar una nueva versión visual puede
obligar a volver a incorporar textos o imágenes manualmente.

## Decisión

### El diseño declara sus campos editables

`manifest.json` puede declarar opcionalmente `content_schema`.

```json
{
  "content_schema": {
    "hero.title": {
      "type": "text",
      "label": "Título principal",
      "default": "Título inicial",
      "required": true,
      "group": "Hero"
    },
    "hero.image": {
      "type": "media",
      "label": "Imagen principal",
      "group": "Hero"
    }
  }
}
```

El HTML usa placeholders dinámicos:

```html
<h1>{{CONTENT.hero.title}}</h1>
<img src="{{CONTENT.hero.image.url}}" alt="{{CONTENT.hero.image.alt}}">
```

Los tipos iniciales son `text`, `textarea`, `url`, `media`, `number`,
`boolean` y `select`. No existe un tipo `html` arbitrario: el propósito de esta
capa es edición editorial segura, no una segunda vía para publicar código.

### Presentación y contenido tienen ciclos de vida distintos

- HTML/CSS/JS y `content_schema` pertenecen a la versión inmutable del diseño.
- Los valores editables viven en post meta `_hub_content_values`.
- Publicar o hacer rollback de una versión visual no reescribe los valores.
- El package puede aportar `default`; al importar solo se siembran campos que
  todavía no tengan un valor base.

### La Page WordPress conserva la identidad editorial

Cuando un diseño `page` está asignado a una Page existente mediante ADR-0005,
los valores editados desde esa Page se guardan en la Page, no en la versión del
diseño. La precedencia es:

```text
schema default < valor base del hub_design < override de la Page
```

Por lo tanto una nueva versión de Home puede cambiar layout, CSS o JavaScript
sin perder el copy que Marketing administra en la Page `Inicio`.

Header, Footer y componentes reutilizables usan por defecto los valores del
`hub_design`; una Page solo se convierte en host de contenido cuando tiene ese
mismo diseño asignado como renderer de página.

### Media usa WordPress Media Library

Un campo `media` guarda el attachment ID, no una URL hardcodeada. En render:

- `{{CONTENT.hero.image}}` y `.url` entregan la URL del attachment;
- `.alt` entrega el texto alternativo del Media Library;
- `.id` entrega el ID.

Eso conserva la relación con la biblioteca de medios y permite evolucionar
más adelante a tamaños responsive/srcset sin cambiar el contrato editorial.

### Seguridad y permisos

- El schema se valida al importar el package.
- Un placeholder `CONTENT.*` no declarado hace fallar la importación.
- Los valores se sanitizan según tipo y se escapan al renderizar.
- No se permite HTML libre como campo editorial.
- En una Page, puede editar contenido quien pueda editar esa Page.
- Editar el código/diseño conserva las capacidades HUB existentes.

## Alternativas consideradas

### Guardar el copy dentro del HTML versionado

Rechazada: diseño y contenido cambiarían juntos y Marketing necesitaría acceso
a código para tareas editoriales.

### Usar ACF como dependencia obligatoria

Rechazada para el core. ACF puede tener un adaptador futuro, pero Constructor
HUB debe poder instalarse sin plugins comerciales ni contratos externos.

### Convertir todo el HTML generado por IA a bloques Gutenberg/Elementor

Rechazada como requisito. Puede ser una estrategia adicional, pero elimina la
ventaja de poder publicar una página IA liviana sin obligar a serializar cada
componente al modelo de otro builder.

### Guardar contenido dentro de la tabla de versiones

Rechazada: un rollback visual también haría rollback de textos e imágenes,
mezclando dos ciclos de vida que deben ser independientes.

## Consecuencias

Positivas:

- una página generada por IA deja de ser un HTML que solo un desarrollador
  puede mantener;
- Marketing puede editar copy, links e imágenes desde WordPress;
- rediseñar con IA no implica volver a cargar contenido;
- el mismo package sigue siendo portable entre sitios;
- Elementor queda disponible solo donde aporte valor editorial real.

Costos y límites iniciales:

- los componentes reutilizables tienen un contenido base por diseño; overrides
  distintos por cada inserción quedan para una fase posterior;
- no hay campos repetibles/repeater en esta primera versión;
- no hay rich text libre en esta primera versión;
- el QA en WordPress real sigue siendo obligatorio antes de producción.

## QA obligatorio

1. importar un package con `content_schema`;
2. verificar rechazo de un `CONTENT.*` no declarado;
3. editar campos en un `hub_design` y confirmar persistencia entre versiones;
4. asignar el diseño a una Page y editar overrides desde esa Page;
5. publicar una nueva versión visual y comprobar que los valores no cambian;
6. probar media picker y alt de WordPress;
7. preview, publicación y rollback;
8. Elementor y Theme/Elementor siguen funcionando en páginas no tomadas por HUB.

## Referencias

- `includes/class-hub-content.php`
- `includes/class-hub-variables.php`
- `includes/class-hub-package.php`
- `assets/js/content-fields-admin.js`
- `tests/test-content-schema.php`
- `docs/AI-PACKAGE-CONTRACT.md`
