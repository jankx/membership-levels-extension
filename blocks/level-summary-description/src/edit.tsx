import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl, ToggleControl, RangeControl } from '@wordpress/components';
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
                <PanelBody title={__('Detail link', 'jankx')} initialOpen={false}>
                    <ToggleControl
                        label={__('Show link', 'jankx')}
                        checked={attributes.showLink}
                        onChange={(showLink) => setAttributes({ showLink })}
                    />
                    {attributes.showLink && (
                        <>
                            <TextControl
                                label={__('Link text', 'jankx')}
                                value={attributes.linkText}
                                onChange={(linkText) => setAttributes({ linkText })}
                            />
                            <TextControl
                                label={__('URL', 'jankx')}
                                value={attributes.detailUrl}
                                onChange={(detailUrl) => setAttributes({ detailUrl })}
                            />
                            <ToggleControl
                                label={__('Open in new tab', 'jankx')}
                                checked={attributes.linkTarget === '_blank'}
                                onChange={(openNew) =>
                                    setAttributes({ linkTarget: openNew ? '_blank' : '_self' })
                                }
                            />
                        </>
                    )}
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <ServerSideRender block={metadata.name} attributes={attributes} />
            </div>
        </>
    );
}