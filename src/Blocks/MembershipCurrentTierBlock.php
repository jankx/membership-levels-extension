<?php

namespace Jankx\Extensions\MembershipLevels\Blocks;

use Jankx\Extensions\MembershipLevels\Block;
use Jankx\Extensions\MembershipLevels\MembershipLevelsExtension;

class MembershipCurrentTierBlock extends Block
{
    protected $blockId = 'jankx/membership-current-tier';

    public function render($attributes, $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $user = wp_get_current_user();
        $levels = MembershipLevelsExtension::getLevels();
        $currentLevel = MembershipLevelsExtension::get_instance()->getUserLevel($user->ID);
        $current = $levels[$currentLevel] ?? $levels['bronze'];

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-membership-section jankx-membership-current-tier',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<h3 class="jankx-section-title">' . esc_html__('Hạng thành viên', 'jankx') . '</h3>';
        $output .= '<div class="jankx-membership-current" style="--membership-color: ' . esc_attr($current['color'] ?? '#65A30D') . '">';
        $output .= '<span class="jankx-membership-dot" aria-hidden="true"></span>';
        $output .= '<div class="jankx-membership-current-body">';
        $output .= '<span class="jankx-membership-current-label">' . esc_html__('Hạng hiện tại của bạn', 'jankx') . '</span>';
        $output .= '<strong class="jankx-membership-current-name">' . esc_html($current['name'] ?? $currentLevel) . '</strong>';
        if (!empty($current['description'])) {
            $output .= '<p class="jankx-membership-current-desc">' . esc_html($current['description']) . '</p>';
        }
        $output .= '</div></div>';
        $output .= '</div>';

        return $output;
    }
}
