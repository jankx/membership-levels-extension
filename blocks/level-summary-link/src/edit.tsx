import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
    PanelBody,
    RangeControl,
    TextControl,
    ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';

export default function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({ className: 'jankx-level-summary-link-wrap' });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Link', 'jankx')} initialOpen>
                    <TextControl
                        label={__('Link text', 'jankx')}
                        help={__('Leave empty to hide the whole block.', 'jankx')}
                        value={attributes.text}
                        onChange={(text) => setAttributes({ text })}
                    />
                    <TextControl
                        label={__('URL', 'jankx')}
                        help={__('Leave empty to link to "#".', 'jankx')}
                        value={attributes.url}
                        onChange={(url) => setAttributes({ url })}
                    />
                    <ToggleControl
                        label={__('Open in new tab', 'jankx')}
                        checked={attributes.linkTarget === '_blank'}
                        onChange={(openNew) =>
                            setAttributes({ linkTarget: openNew ? '_blank' : '_self' })
                        }
                    />
                </PanelBody>
                <PanelBody title={__('Appearance', 'jankx')} initialOpen={false}>
                    <RangeControl
                        label={__('Font size', 'jankx')}
                        value={attributes.fontSize}
                        onChange={(fontSize) => setAttributes({ fontSize })}
                        min={10}
                        max={28}
                    />
                    <ToggleControl
                        label={__('Use level colour', 'jankx')}
                        checked={attributes.useLevelColor}
                        onChange={(useLevelColor) => setAttributes({ useLevelColor })}
                    />
                    <ToggleControl
                        label={__('Show arrow', 'jankx')}
                        checked={attributes.showArrow}
                        onChange={(showArrow) => setAttributes({ showArrow })}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <ServerSideRender block={metadata.name} attributes={attributes} />
            </div>
        </>
    );
}
