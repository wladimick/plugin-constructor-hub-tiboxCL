# Package de ejemplo — Hero genérico

Paquete mínimo válido contra el contrato de Constructor HUB. Sirve para dos
cosas: verificar una instalación nueva y mostrarle a una IA qué se espera de
ella.

## Probarlo

```bash
cd examples/hub-package-hero
zip -r ../hero-generico-hub-v1.zip manifest.json index.html style.css
```

Después: **Constructor HUB → Importar ZIP**, subir el archivo y dejar el destino
en *Crear un diseño nuevo*. El package entra como versión borrador; el aviso
ofrece el enlace de preview. Publicar es un paso aparte.

Una vez publicado, se inserta en cualquier página con:

```text
[hub_design slug="hero-generico-hub"]
```

## Qué demuestra

- `manifest.json` con `hub_package`, `type`, `slug` y `variables` declaradas;
- cero colores literales: todo sale de tokens `--hub-*`;
- variables del contrato en lugar de datos escritos a mano;
- `scope` declarado, de modo que el CSS se aísla al importar;
- foco visible y respeto por `prefers-reduced-motion`.

El contrato completo está en [`docs/AI-PACKAGE-CONTRACT.md`](../../docs/AI-PACKAGE-CONTRACT.md).
