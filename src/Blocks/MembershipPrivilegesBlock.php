<?php

namespace Jankx\Extensions\MembershipLevels\Blocks;

use Jankx\Extensions\MembershipLevels\Block;
use Jankx\Extensions\MembershipLevels\MembershipLevelsExtension;

class MembershipPrivilegesBlock extends Block
{
    protected $blockId = 'jankx/membership-privileges';

    public function render($attributes, $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $user = wp_get_current_user();
        $levels = MembershipLevelsExtension::getLevels();
        $currentLevel = MembershipLevelsExtension::get_instance()->getUserLevel($user->ID);
        $current = $levels[$currentLevel] ?? $levels[array_key_first($levels)];
        $privileges = $current['privileges'] ?? [];

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-membership-section jankx-membership-privileges-section',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);

        if (!empty($privileges)) {
            $output .= '<h3 class="jankx-section-title">' . esc_html__('Quyền lợi', 'jankx') . '</h3>';
            $output .= '<ul class="jankx-membership-privileges">';
            foreach ($privileges as $privilege) {
                if (is_array($privilege)) {
                    $text = trim((string) ($privilege['text'] ?? $privilege['label'] ?? $privilege['title'] ?? $privilege['name'] ?? ''));
                    $description = trim((string) ($privilege['description'] ?? $privilege['desc'] ?? ''));
                    if ($text === '') {
                        continue;
                    }
                    $output .= '<li class="jankx-membership-privilege">';
                    $output .= '<span class="jankx-membership-privilege__name">' . esc_html($text) . '</span>';
                    if ($description !== '') {
                        $output .= '<span class="jankx-membership-privilege__desc">' . esc_html($description) . '</span>';
                    }
                    $output .= '</li>';
                } else {
                    $output .= '<li class="jankx-membership-privilege"><span class="jankx-membership-privilege__name">'
                        . esc_html((string) $privilege) . '</span></li>';
                }
            }
            $output .= '</ul>';
        } else {
            $output .= '<div class="jankx-empty-state"><p>' . esc_html__('Chưa có quyền lợi nào.', 'jankx') . '</p></div>';
        }

        $output .= '</div>';
        return $output;
    }
}
