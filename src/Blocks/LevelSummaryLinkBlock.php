<?php

namespace Jankx\Extensions\MembershipLevels\Blocks;

use Jankx\Extensions\MembershipLevels\Block;
use Jankx\Extensions\MembershipLevels\Support\CurrentLevel;

/**
 * Optional "view details" link of the level summary.
 *
 * Split out of the description block so an author can drop it, reorder it, or
 * point it somewhere else without touching the description's text. Empty `text`
 * hides the block entirely, which is how an author removes the link without
 * deleting the block.
 */
class LevelSummaryLinkBlock extends Block
{
    protected $blockId = 'jankx/level-summary-link';

    /** Chevron drawn after the label. Matches the icon weight of the other parts. */
    const ARROW = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>';

    public function render($attributes, $content = '', $block = null)
    {
        if (!CurrentLevel::exists()) {
            return '';
        }

        $text = trim((string) ($attributes['text'] ?? ''));
        if ($text === '') {
            return '';
        }

        $level = CurrentLevel::resolve();

        $fontSize     = (int) ($attributes['fontSize'] ?? 13);
        $useLevelColor = !empty($attributes['useLevelColor']);
        $showArrow     = !empty($attributes['showArrow']);

        $style = 'display:inline-flex;align-items:center;gap:4px;margin:10px 0 0 0;font-weight:500;';
        if ($fontSize > 0) {
            $style .= sprintf('font-size:%dpx;', $fontSize);
        }
        if ($useLevelColor && !empty($level['color'])) {
            $style .= sprintf('color:%s;', $level['color']);
        }

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-level-summary-link',
            'style' => $style,
        ]);

        $href   = trim((string) ($attributes['url'] ?? ''));
        $href   = $href !== '' ? $href : '#';
        $target = ($attributes['linkTarget'] ?? '') === '_blank' ? '_blank' : '_self';

        return sprintf(
            '<a %1$s href="%2$s" target="%3$s">%4$s%5$s</a>',
            $wrapperAttrs,
            esc_url($href),
            esc_attr($target),
            esc_html($text),
            $showArrow ? self::ARROW : ''
        );
    }
}
