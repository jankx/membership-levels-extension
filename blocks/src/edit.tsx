import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit({ attributes }) {
    const blockProps = useBlockProps({
        className: 'jankx-account-tab-membership is-editor-preview',
    });

    return (
        <div {...blockProps}>
            <ServerSideRender
                block="jankx/account-tab-membership"
                attributes={attributes}
            />
        </div>
    );
}
