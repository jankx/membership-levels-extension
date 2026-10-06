import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';

const ALLOWED_ICON_BLOCKS = [
    'jankx/svg-icon',
    'jankx/icon-picker',
    'jankx/advanced-image-box',
];

/**
 * The icon slot behaves differently from its two siblings: instead of only
 * previewing server output, it has to *accept* a nested icon block. So when the
 * slot is empty we ask the server for the default artwork, and as soon as the
 * author drops an icon in we hand the slot over to InnerBlocks. Both states are
 * serialized the same way, so nothing is lost when switching between them.
 */
export default function Edit({ attributes, setAttributes, clientId }) {
    const hasInnerBlock = useSelect(
        (select) => select(blockEditorStore).getBlocks(clientId).length > 0,
        [clientId]
    );

    const blockProps = useBlockProps({ className: 'jankx-level-summary-icon' });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Badge', 'jankx')} initialOpen>
                    <RangeControl
                        label={__('Size', 'jankx')}
                        value={attributes.size}
                        onChange={(size) => setAttributes({ size })}
                        min={24}
                        max={120}
                        step={2}
                    />
                    <RangeControl
                        label={__('Icon size', 'jankx')}
                        value={attributes.iconSize}
                        onChange={(iconSize) => setAttributes({ iconSize })}
                        min={12}
                        max={96}
                        step={2}
                    />
                    <RangeControl
                        label={__('Border width', 'jankx')}
                        value={attributes.borderWidth}
                        onChange={(borderWidth) => setAttributes({ borderWidth })}
                        min={0}
                        max={8}
                    />
                    <ToggleControl
                        label={__('Use level colour', 'jankx')}
                        help={__(
                            'Off = use the colour picked in the block settings instead.',
                            'jankx'
                        )}
                        checked={attributes.useLevelColor}
                        onChange={(useLevelColor) => setAttributes({ useLevelColor })}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                {hasInnerBlock ? (
                    <InnerBlocks
                        allowedBlocks={ALLOWED_ICON_BLOCKS}
                        templateLock={false}
                        renderAppender={InnerBlocks.ButtonBlockAppender}
                    />
                ) : (
                    <div style={{ position: 'relative' }}>
                        <ServerSideRender
                            block={metadata.name}
                            attributes={attributes}
                        />
                        <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                            <InnerBlocks
                                allowedBlocks={ALLOWED_ICON_BLOCKS}
                                templateLock={false}
                                renderAppender={InnerBlocks.ButtonBlockAppender}
                            />
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}