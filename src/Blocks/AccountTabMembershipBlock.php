<?php

namespace Jankx\Extensions\MembershipLevels\Blocks;

use Jankx\Extensions\MembershipLevels\Block;
use Jankx\Extensions\MembershipLevels\MembershipLevelsExtension;

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
            $activeTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
        }

        $is_editor = defined('REST_REQUEST') && REST_REQUEST
            && !empty($_SERVER['REQUEST_URI'])
            && strpos($_SERVER['REQUEST_URI'], '/block-renderer/') !== false;

        if (!$is_editor && $activeTab !== 'membership') {
            return '';
        }

        $user = wp_get_current_user();
        $levels = MembershipLevelsExtension::getLevels();
        $currentLevel = MembershipLevelsExtension::get_instance()->getUserLevel($user->ID);
        $current = $levels[$currentLevel] ?? $levels['bronze'];

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-tab-panel jankx-tab-membership',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);

        $output .= '<h2 class="jankx-section-title">' . esc_html__('Hạng thành viên', 'jankx') . '</h2>';

        $output .= '<div class="jankx-membership-current" style="--membership-color: ' . esc_attr($current['color'] ?? '#65A30D') . '">';
        $output .= '<span class="jankx-membership-dot" aria-hidden="true"></span>';
        $output .= '<div class="jankx-membership-current-body">';
        $output .= '<span class="jankx-membership-current-label">' . esc_html__('Hạng hiện tại của bạn', 'jankx') . '</span>';
        $output .= '<strong class="jankx-membership-current-name">' . esc_html($current['name'] ?? $currentLevel) . '</strong>';
        if (!empty($current['description'])) {
            $output .= '<p class="jankx-membership-current-desc">' . esc_html($current['description']) . '</p>';
        }
        $output .= '</div>';
        $output .= '</div>';

        $privileges = $current['privileges'] ?? [];
        if (!empty($privileges)) {
            $output .= '<h3 class="jankx-membership-subtitle">' . esc_html__('Quyền lợi', 'jankx') . '</h3>';
            $output .= '<ul class="jankx-membership-privileges">';
            foreach ($privileges as $privilege) {
                $output .= '<li>' . esc_html($privilege) . '</li>';
            }
            $output .= '</ul>';
        }

        $output .= '<h3 class="jankx-membership-subtitle">' . esc_html__('Các hạng thành viên', 'jankx') . '</h3>';
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
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }
}