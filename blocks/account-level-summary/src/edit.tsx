import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

const ALLOWED_BLOCKS = [
    'jankx/level-summary-icon',
    'jankx/level-summary-name',
    'jankx/level-summary-description',
];

const TEMPLATE = [
    ['jankx/level-summary-icon'],
    ['jankx/level-summary-name'],
    ['jankx/level-summary-description'],
];

export default function Edit() {
    const blockProps = useBlockProps({
        className: 'jankx-account-level-summary',
    });

    return (
        <div {...blockProps}>
            <p className="jankx-account-level-summary__hint">
                Kéo thả các block bên dưới để tuỳ chỉnh từng phần: icon, tên hạng, mô tả. Xoá
                block nào thì phần đó không hiển thị.
            </p>
            <InnerBlocks
                allowedBlocks={ALLOWED_BLOCKS}
                template={TEMPLATE}
                templateLock={false}
                orientation="horizontal"
            />
        </div>
    );
}