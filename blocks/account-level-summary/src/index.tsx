import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import Edit from './edit';
import metadata from '../block.json';

registerBlockType(metadata.name, {
    ...metadata,
    edit: Edit,
    // Phải trả InnerBlocks.Content: save trả null khiến getSaveElement() rỗng
    // và inner blocks bị mất hoàn toàn khi lưu post.
    save: () => <InnerBlocks.Content />,
});