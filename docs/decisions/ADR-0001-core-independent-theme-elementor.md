# ADR-0001 — Core independiente de theme y Elementor

- Estado: Aceptada
- Fecha: 2026-08-28
- Rama: `feat/ai-frontend-mvp`

## Contexto

Constructor HUB Tibox debe utilizarse inicialmente en `tibox.cl` y `prodata.cl`, ambos WordPress existentes con Elementor, pero con posibilidad futura de migrar a un theme propio HUB.

Cloud-tibox demuestra que un frontend WordPress ultraliviano con theme propio funciona bien en sitios nuevos. Sin embargo, exigir ese cambio en sitios productivos existentes elevaría el riesgo y dificultaría una transición gradual.

## Decisión

El core de Constructor HUB Tibox será independiente de:

- Elementor;
- Hello Elementor;
- cualquier theme específico;
- branding Tibox/Prodata.

Elementor se tratará mediante un adaptador de compatibilidad opcional.

El plugin deberá soportar tres modos conceptuales: Legacy, Híbrido y HUB.

Un `HUB Tibox Theme` podrá existir en el futuro, pero será opcional y no contendrá la fuente de verdad de los componentes visuales.

## Alternativas consideradas

### Convertir el plugin en extensión exclusiva de Elementor

Rechazada porque impediría independizar el frontend a futuro.

### Crear inmediatamente un theme HUB y migrar Tibox/Prodata

Rechazada para la etapa inicial por riesgo operacional y alcance.

### Mantener toda la presentación dentro del theme

Rechazada porque dificulta reutilizar componentes entre themes/sitios y hace más costoso cambiar el theme posteriormente.

## Consecuencias

Positivas:

- transición incremental;
- reutilización multi-sitio;
- futuro cambio de theme más sencillo;
- arquitectura testeable por capas.

Costos:

- requiere adaptadores;
- reemplazar Header/Footer sobre themes desconocidos necesita compatibilidad específica;
- durante la transición coexistirán distintas tecnologías.

## Referencias

- Repositorio: `wladimick/plugin-constructor-hub-tiboxCL`
- Antecedente: `wladimick/Cloud-tibox`
- Documentos: `ARCHITECTURE.md`, `CLOUD-TIBOX-RELATION.md`, `SITE-ADAPTERS.md`
- Cambio de identidad inicial: commit `8c7c5ff4d391f16f24a93e4109b6e7232c1279d5`