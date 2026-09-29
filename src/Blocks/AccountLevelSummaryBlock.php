<?php

namespace Jankx\Extensions\MembershipLevels\Blocks;

use Jankx\Extensions\MembershipLevels\Block;
use Jankx\Extensions\MembershipLevels\MembershipLevelsExtension;

class AccountLevelSummaryBlock extends Block
{
    protected $blockId = 'jankx/account-level-summary';

    public function render($attributes, $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $user = wp_get_current_user();
        $showIcon = $attributes['showIcon'] ?? true;
        $showName = $attributes['showName'] ?? true;
        $showDescription = $attributes['showDescription'] ?? true;
        $detailUrl = $attributes['detailUrl'] ?? '';

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-account-level-summary',
            'style' => 'background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.02);',
        ]);

        $badge = $this->renderLevelCard(
            $user,
            (bool) $showIcon,
            (bool) $showName,
            (bool) $showDescription,
            $detailUrl
        );

        if ($badge === '') {
            return '';
        }

        return sprintf('<div %s>%s</div>', $wrapperAttrs, $badge);
    }

    protected function renderLevelCard($user, bool $showIcon, bool $showName, bool $showDescription, string $detailUrl = ''): string
    {
        // Get level data from MembershipLevelsExtension (source of truth)
        $levels = MembershipLevelsExtension::getLevels();
        $levelSlug = MembershipLevelsExtension::get_instance()
            ? MembershipLevelsExtension::get_instance()->getUserLevel($user->ID)
            : (get_user_meta($user->ID, MembershipLevelsExtension::USER_LEVEL_META, true) ?: 'silver');

        $level = $levels[$levelSlug] ?? $levels[array_key_first($levels)] ?? [
            'name'        => 'Bạc',
            'description' => 'Thành viên Bạc',
            'color'       => '#C0C0C0',
        ];

        if (!$showIcon && !$showName && !$showDescription) {
            return '';
        }

        $color = esc_attr($level['color'] ?? '#CD7F32');
        $icon  = $level['icon'] ?? $this->defaultIcon($levelSlug, $color);

        $output = '<div style="display:flex;align-items:flex-start;gap:16px;">';

        if ($showIcon) {
            $output .= '<div style="width:48px;height:48px;border-radius:50%;border:2px solid ' . $color . ';background:#ffffff;display:flex;align-items:center;justify-content:center;color:' . $color . ';flex:0 0 auto;">';
            $output .= $icon;
            $output .= '</div>';
        }

        if ($showName || $showDescription) {
            $output .= '<div style="flex:1;min-width:0;">';

            if ($showName) {
                $output .= '<h3 style="margin:0 0 6px 0;font-size:17px;font-weight:600;color:' . $color . ';line-height:1.2;">'
                    . esc_html($level['name'])
                    . '</h3>';
            }

            if ($showDescription && !empty($level['description'])) {
                $output .= '<p style="margin:0 0 12px 0;font-size:13px;color:#8f9bb3;line-height:1.5;">'
                    . esc_html($level['description'])
                    . '</p>';
            }

            $href = $detailUrl ?: '#';
            $output .= '<a href="' . esc_url($href) . '" style="display:inline-flex;align-items:center;gap:4px;font-size:13px;font-weight:500;color:' . $color . ';text-decoration:none;">'
                . esc_html__('Xem chi tiết', 'jankx')
                . ' <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>'
                . '</a>';

            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Default SVG icon per level slug when no icon is set in level data
     */
    protected function defaultIcon(string $levelSlug, string $color): string
    {
        $icons = [
            'bronze'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
            'silver'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>',
            'gold'    => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            'platinum' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h12l4 6-10 13L2 9z"/><path d="M2 9h20"/></svg>',
            'diamond' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h12l4 6-10 13L2 9z"/><path d="M2 9h20"/></svg>',
        ];

        return $icons[$levelSlug] ?? $icons['bronze'];
    }
}
