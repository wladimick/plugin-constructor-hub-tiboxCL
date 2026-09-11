# 2026-09-11 — Asignación HUB sobre Pages WordPress existentes

Rama: `feat/page-assignment`  
Base: `feat/hub-v0.5-refundacion`  
Estado: **implementado en código / QA WordPress real pendiente**

## Objetivo

Permitir que una Page ya publicada e indexada —por ejemplo la página SOC de
Tibox— pueda cambiar su frontend de Elementor a un diseño `hub_design` de tipo
`page` sin cambiar post ID, permalink, Rank Math, ACF ni borrar el contenido
legacy.

## Implementación

- nuevo `HUB_Tibox_Page_Assignment`;
- meta box **Constructor HUB** en el editor de Pages;
- selector de diseño HUB tipo `page`;
- selector `Theme / Elementor` vs `Constructor HUB`;
- activación fail-safe: un diseño sin versión live nunca deja la Page en blanco;
- preview firmado sobre el permalink de la Page original antes del cutover;
- shell `templates/assigned-page.php` con hooks WordPress intactos;
- noindex/nofollow durante el preview firmado;
- integración con el adaptador Elementor para declarar la Page como
  independiente solo después del cutover válido;
- integración con el optimizador de assets y el mapa de migración;
- datos Elementor preservados para rollback inmediato.

## Seguridad y permisos

Asignar/preparar requiere capacidad de gestión de diseños. Activar HUB en una
URL pública requiere `hub_publish_designs` o `manage_options`.

No se ejecuta PHP desde el diseño y no cambia el contrato de packages.

## Compatibilidad

El comportamiento por defecto no cambia: sin asignación válida y modo HUB,
WordPress usa el mismo theme/Elementor que antes.

Si un diseño asignado se elimina, deja de ser `page`, se despublica o pierde su
versión live, el takeover no ocurre y la Page vuelve al renderer legacy.

## QA

Se añadieron pruebas puras para la regla de activación:

- sin asignación;
- modo theme;
- HUB sin versión live;
- HUB con versión live;
- preview firmado antes del cutover.

Pendiente Fase 7 en una copia real de Tibox: editor, preview, Rank Math, GTM,
Elementor, responsive, caché, rollback y optimización de assets.
