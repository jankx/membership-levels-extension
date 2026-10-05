<?php

namespace Jankx\Extensions\MembershipLevels\Blocks;

use Jankx\Extensions\MembershipLevels\Block;
use Jankx\Extensions\MembershipLevels\Support\CurrentLevel;

/**
 * Level description plus the optional "view details" link.
 *
 * The link lives here rather than in its own block because it is semantically
 * part of the description: it explains where to go to read more about the tier.
 * An author who does not want it unchecks `showLink`.
 */
class LevelSummaryDescriptionBlock extends Block
{
    protected $blockId = 'jankx/level-summary-description';

    public function render($attributes, $content = '', $block = null)
    {
        if (!CurrentLevel::exists()) {
            return '';
        }

        $level = CurrentLevel::resolve();

        $override   = trim((string) ($attributes['text'] ?? ''));
        $description = $override !== '' ? $override : $level['description'];

        $fontSize   = (int) ($attributes['fontSize'] ?? 13);
        $showLink   = !empty($attributes['showLink']);
        $linkText   = (string) ($attributes['linkText'] ?? __('Xem chi tiết', 'jankx'));
        $detailUrl  = (string) ($attributes['detailUrl'] ?? '');
        $linkTarget = (string) ($attributes['linkTarget'] ?? '_self');

        if ($linkTarget === '_blank') {
            $linkTarget = '_blank';
        } else {
            $linkTarget = '_self';
        }

        // Nothing to show at all: no description and no link.
        if ($description === '' && !$showLink) {
            return '';
        }

        $output = '';

        if ($description !== '') {
            $style = 'margin:0;font-size:' . $fontSize . 'px;color:#8f9bb3;line-height:1.5;';
            if ($showLink) {
                $style .= 'margin-bottom:12px;';
            }

            $wrapperAttrs = get_block_wrapper_attributes([
                'class' => 'jankx-level-summary-description',
                'style' => $style,
            ]);

            $output .= sprintf('<p %s>%s</p>', $wrapperAttrs, esc_html($description));
        }

        if ($showLink && $linkText !== '') {
            $href = $detailUrl !== '' ? $detailUrl : '#';
            $color = $level['color'] ?: '#CD7F32';

            $output .= sprintf(
                '<a class="jankx-level-summary-link" href="%1$s" target="%2$s" style="display:inline-flex;align-items:center;gap:4px;font-size:13px;font-weight:500;color:%3$s;text-decoration:none;">%4$s <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg></a>',
                esc_url($href),
                esc_attr($linkTarget),
                esc_attr($color),
                esc_html($linkText)
            );
        }

        if ($output === '') {
            return '';
        }

        return sprintf(
            '<div class="jankx-level-summary-description-wrap">%s</div>',
            $output
        );
    }
}