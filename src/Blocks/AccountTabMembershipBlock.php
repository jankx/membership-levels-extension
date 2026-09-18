<?php

namespace Jankx\Extensions\MembershipLevels\Blocks;

use Jankx\Extensions\MembershipLevels\Block;

class AccountTabMembershipBlock extends Block
{
    protected $blockId = 'jankx/account-tab-membership';

    public function render($attributes, $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $activeTab = get_query_var('jankx_account_page');
        if (empty($activeTab)) {
            $activeTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'membership';
        }

        $is_editor = defined('REST_REQUEST') && REST_REQUEST
            && !empty($_SERVER['REQUEST_URI'])
            && strpos($_SERVER['REQUEST_URI'], '/block-renderer/') !== false;

        if (!$is_editor && $activeTab !== 'membership') {
            return '';
        }

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-tab-panel jankx-tab-membership',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);

        if (!empty($content)) {
            $output .= $content;
        }

        $output .= '</div>';

        return $output;
    }
}
