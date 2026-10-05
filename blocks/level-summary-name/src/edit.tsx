import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl, RangeControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';

export default function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({ className: 'jankx-level-summary-name' });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Text', 'jankx')} initialOpen>
                    <TextControl
                        label={__('Override text', 'jankx')}
                        help={__(
                            'Leave empty to show the level name of the current user.',
                            'jankx'
                        )}
                        value={attributes.text}
                        onChange={(text) => setAttributes({ text })}
                    />
                    <SelectControl
                        label={__('HTML tag', 'jankx')}
                        value={attributes.tagName}
                        options={[
                            { label: 'h2', value: 'h2' },
                            { label: 'h3', value: 'h3' },
                            { label: 'h4', value: 'h4' },
                            { label: 'h5', value: 'h5' },
                            { label: 'h6', value: 'h6' },
                        ]}
                        onChange={(tagName) => setAttributes({ tagName })}
                    />
                    <RangeControl
                        label={__('Font size', 'jankx')}
                        value={attributes.fontSize}
                        onChange={(fontSize) => setAttributes({ fontSize })}
                        min={10}
                        max={48}
                    />
                    <ToggleControl
                        label={__('Use level colour', 'jankx')}
                        checked={attributes.useLevelColor}
                        onChange={(useLevelColor) => setAttributes({ useLevelColor })}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <ServerSideRender block={metadata.name} attributes={attributes} />
            </div>
        </>
    );
}