# Arquitectura — Tibox AI Frontend

## Principio

WordPress sigue resolviendo la URL, permisos, SEO, REST, medios, administración y datos. El plugin evita el template del tema solo en las páginas habilitadas.

```text
Request /inicio-con-ia/
        │
        ▼
WordPress resuelve Page + Rank Math
        │
        ▼
Tibox AI Frontend detecta meta enabled=1
        │
        ├─ wp_head()  → SEO / canonical / OG / snippets compatibles
        ├─ header liviano
        ├─ template home-ai
        ├─ formulario → /wp-json/tibox/v1/lead
        ├─ wp_footer() → mega menú / GTM actual / hooks compatibles
        │
        ▼
HTML liviano sin template Elementor
```

## Por qué no usar documento HTML completamente aislado

Un HTML completo servido antes de `wp_head()` puede ser muy rápido, pero obliga a reconstruir manualmente Rank Math, canonical, Open Graph, GTM y otros hooks. Para esta etapa se prefiere un shell limpio que conserve los hooks de WordPress y descargue solo los assets pesados conocidos.

## Compatibilidad con el menú actual

El mega menú WPCode actual escucha un opener con atributo `data-open-tibox-mega-menu`. El header del plugin utiliza ese atributo, por lo que se puede eliminar el header de Elementor en la página IA sin perder el menú de servicios.

## Formulario

La plantilla usa el contrato del endpoint existente:

- `name`
- `email`
- `phone`
- `company`
- `rut`
- `area`
- `users`
- `message`
- `privacy`
- `website` (honeypot)
- contexto de landing
- UTM / IDs de Ads

El evento `form_submit` se publica a `dataLayer` únicamente después de que el servidor confirma `lead_created=true`.
