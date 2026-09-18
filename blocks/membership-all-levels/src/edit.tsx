import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from '../block.json';
export default function Edit({ attributes }) {
    const blockProps = useBlockProps({ className: 'is-editor-preview' });
    return <div {...blockProps}><ServerSideRender block={metadata.name} attributes={attributes} /></div>;
}
