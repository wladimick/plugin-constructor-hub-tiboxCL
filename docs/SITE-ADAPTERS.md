# Site Adapters — Tibox, Prodata y futuros sitios

Fecha base: 2026-08-28.

## Objetivo

Evitar que el core de Constructor HUB Tibox se llene de condiciones específicas por cliente/sitio.

## Regla

El core define capacidades genéricas. Un adaptador define cómo esas capacidades se integran con un sitio o tecnología concreta.

```text
Core HUB
├── componentes
├── renderer
├── variables
├── packages
├── assets
└── preview

Adapters
├── elementor
├── tibox-cl
├── prodata-cl
└── futuros
```

## Adaptador Elementor

Debe resolver comportamiento tecnológico reutilizable:

- detectar Elementor/Pro;
- detectar dependencia de una página;
- impedir dequeue de assets todavía requeridos;
- identificar Header/Footer de Theme Builder cuando sea posible;
- ocultar/reemplazar Header/Footer sin afectar `the_content()`;
- estrategia de rollback.

No debe contener branding de Tibox ni Prodata.

## Adaptador Tibox

Puede conocer integraciones exclusivas del sitio, por ejemplo:

- mega menú heredado de WPCode durante transición;
- endpoint REST de leads Tibox;
- bridge hacia WebOps;
- rutas de servicios concretas;
- reglas temporales de compatibilidad.

Estas dependencias deben desaparecer del core genérico.

## Adaptador Prodata

Se definirá después de inventariar el WordPress productivo de Prodata.

No asumir que comparte:

- menús;
- formularios;
- WPCode;
- endpoints;
- Design System;
- plugins auxiliares;

con Tibox únicamente porque ambos usan Elementor.

## Configuración vs adaptador

Una diferencia de color, logo o tipografía pertenece al **Design System/configuración**, no debería requerir código de adaptador.

Un adaptador se justifica cuando hay comportamiento técnico específico.

## Regla de portabilidad

Un componente visual correctamente construido debería poder pasar de Tibox a Prodata cambiando Design System, contenido y variables cuando su estructura funcional sea compatible.

Si requiere editar el core para cambiar un logo/color/url normal, la separación está mal diseñada.

## Datos requeridos antes de crear un adaptador nuevo

Documentar:

- URL/sitio;
- theme actual;
- page builder;
- SEO;
- forms;
- analítica;
- snippets relevantes;
- Header/Footer actual;
- integraciones REST/API;
- plugins que impactan frontend;
- requisitos de rollback.

## Futuro

Idealmente el plugin ofrecerá una pantalla de diagnóstico que detecte automáticamente parte de este inventario y sugiera el modo de compatibilidad apropiado.