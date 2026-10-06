import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextareaControl, RangeControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';

export default function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({ className: 'jankx-level-summary-description' });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Text', 'jankx')} initialOpen>
                    <TextareaControl
                        label={__('Override description', 'jankx')}
                        help={__(
                            'Leave empty to show the level description of the current user.',
                            'jankx'
                        )}
                        value={attributes.text}
                        onChange={(text) => setAttributes({ text })}
                    />
                    <RangeControl
                        label={__('Font size', 'jankx')}
                        value={attributes.fontSize}
                        onChange={(fontSize) => setAttributes({ fontSize })}
                        min={10}
                        max={28}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <ServerSideRender block={metadata.name} attributes={attributes} />
            </div>
        </>
    );
}
