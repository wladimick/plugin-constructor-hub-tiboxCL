(function (blocks, element, components, blockEditor, serverSideRender, i18n) {
    'use strict';

    const { createElement: el, Fragment } = element;
    const { SelectControl, Placeholder, PanelBody } = components;
    const { InspectorControls, useBlockProps } = blockEditor;
    const ServerSideRender = serverSideRender;

    const choices = (window.HubDesignBlockData && window.HubDesignBlockData.designs) || [];
    const options = [{ value: '', label: '— Seleccionar diseño —' }].concat(choices);

    blocks.registerBlockType('constructor-hub/design', {
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const blockProps = useBlockProps();

            const selector = el(SelectControl, {
                label: 'Diseño HUB',
                value: attributes.slug,
                options: options,
                onChange: function (slug) {
                    setAttributes({ slug: slug });
                },
            });

            if (!attributes.slug) {
                return el(
                    'div',
                    blockProps,
                    el(
                        Placeholder,
                        {
                            icon: 'layout',
                            label: 'Diseño HUB',
                            instructions: 'Elige un componente publicado en Constructor HUB.',
                        },
                        selector
                    )
                );
            }

            return el(
                Fragment,
                null,
                el(InspectorControls, null, el(PanelBody, { title: 'Diseño HUB' }, selector)),
                el(
                    'div',
                    blockProps,
                    el(ServerSideRender, {
                        block: 'constructor-hub/design',
                        attributes: attributes,
                    })
                )
            );
        },

        // Rendered in PHP so the markup can never drift from the shortcode.
        save: function () {
            return null;
        },
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.blockEditor,
    window.wp.serverSideRender,
    window.wp.i18n
);
