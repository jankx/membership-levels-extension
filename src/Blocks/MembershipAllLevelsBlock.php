<?php

namespace Jankx\Extensions\MembershipLevels\Blocks;

use Jankx\Extensions\MembershipLevels\Block;
use Jankx\Extensions\MembershipLevels\MembershipLevelsExtension;

class MembershipAllLevelsBlock extends Block
{
    protected $blockId = 'jankx/membership-all-levels';

    public function render($attributes, $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $user = wp_get_current_user();
        $levels = MembershipLevelsExtension::getLevels();
        $currentLevel = MembershipLevelsExtension::get_instance()->getUserLevel($user->ID);

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-membership-section jankx-membership-all-levels',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<h3 class="jankx-section-title">' . esc_html__('Các hạng thành viên', 'jankx') . '</h3>';
        $output .= '<div class="jankx-membership-levels">';

        foreach ($levels as $slug => $level) {
            $isCurrent = $slug === $currentLevel;
            $output .= '<div class="jankx-membership-level' . ($isCurrent ? ' jankx-membership-level--active' : '') . '"'
                . ' style="--membership-color: ' . esc_attr($level['color'] ?? '#65A30D') . '">';
            $output .= '<span class="jankx-membership-dot" aria-hidden="true"></span>';
            $output .= '<div class="jankx-membership-level-body">';
            $output .= '<strong class="jankx-membership-level-name">' . esc_html($level['name'] ?? $slug) . '</strong>';
            if (!empty($level['description'])) {
                $output .= '<p class="jankx-membership-level-desc">' . esc_html($level['description']) . '</p>';
            }
            $output .= '</div>';
            if ($isCurrent) {
                $output .= '<span class="jankx-membership-level-tag">' . esc_html__('Hạng của bạn', 'jankx') . '</span>';
            }
            $output .= '</div>';
        }

        $output .= '</div></div>';
        return $output;
    }
}
