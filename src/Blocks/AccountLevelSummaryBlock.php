<?php

namespace Jankx\Extensions\MembershipLevels\Blocks;

use Jankx\Extensions\MembershipLevels\Block;
use Jankx\Extensions\MembershipLevels\Support\CurrentLevel;

/**
 * Card shell of the account level summary.
 *
 * ── Why this is now a container ──────────────────────────────────────────────
 * The card used to render icon + name + description + link from a set of
 * on/off switches, which meant the only customisation was "hide it". Those four
 * pieces are now separate blocks (level-summary-icon / -name / -description) so
 * each can be restyled, re-ordered or dropped entirely.
 *
 * ── Backward compatibility ───────────────────────────────────────────────────
 * Pages already saved with the old switches contain no inner blocks. Dropping
 * the container in blindly would render an empty card, so when no inner blocks
 * are present this falls back to the original markup, driven by the legacy
 * showIcon/showName/showDescription attributes. Those attributes stay declared
 * in block.json for exactly this reason. Once an author inserts the inner blocks
 * the container takes over and the legacy path is no longer used.
 */
class AccountLevelSummaryBlock extends Block
{
    protected $blockId = 'jankx/account-level-summary';

    public function render($attributes, $content = '', $block = null)
    {
        if (!CurrentLevel::exists()) {
            return '';
        }

        // The card look lives in CSS-free inline styles because this block ships
        // no stylesheet; block-level colour/spacing support still layers on top
        // through the wrapper attributes.
        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-account-level-summary',
            'style' => 'background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.02);',
        ]);

        $inner = $this->innerContent($content);

        $body = $inner !== '' ? $inner : $this->legacyBody($attributes);

        if (trim($body) === '') {
            return '';
        }

        return sprintf('<div %s>%s</div>', $wrapperAttrs, $body);
    }

    /**
     * Wrap the inner blocks.
     *
     * Layout lives in style.css, not inline: the icon has to sit in column 1
     * spanning all rows while the text blocks stack in column 2, and that is not
     * expressible as one inline flex declaration.
     */
    protected function innerContent(string $content): string
    {
        $inner = trim($content);

        if ($inner === '') {
            return '';
        }

        return sprintf(
            '<div class="jankx-account-level-summary__inner">%s</div>',
            $inner
        );
    }

    /**
     * Original markup, used when the block has no inner blocks (pre-split content).
     */
    protected function legacyBody($attributes): string
    {
        $showIcon        = $attributes['showIcon'] ?? true;
        $showName        = $attributes['showName'] ?? true;
        $showDescription = $attributes['showDescription'] ?? true;
        $detailUrl       = (string) ($attributes['detailUrl'] ?? '');

        if (!$showIcon && !$showName && !$showDescription) {
            return '';
        }

        $level = CurrentLevel::resolve();

        $output = sprintf(
            '<div class="jankx-account-level-summary__inner" style="display:flex;align-items:flex-start;gap:16px;">'
        );

        if ($showIcon) {
            $output .= sprintf(
                '<div style="width:48px;height:48px;border-radius:50%%;border:2px solid %1$s;background:#ffffff;display:flex;align-items:center;justify-content:center;color:%1$s;flex:0 0 auto;">%2$s</div>',
                esc_attr($level['color']),
                $level['icon'] !== '' ? $level['icon'] : CurrentLevel::defaultIcon($level['slug'])
            );
        }

        if ($showName || $showDescription) {
            $output .= '<div style="flex:1;min-width:0;">';

            if ($showName) {
                $output .= sprintf(
                    '<h3 style="margin:0 0 6px 0;font-size:17px;font-weight:600;color:%s;line-height:1.2;">%s</h3>',
                    esc_attr($level['color']),
                    esc_html($level['name'])
                );
            }

            if ($showDescription && $level['description'] !== '') {
                $output .= sprintf(
                    '<p style="margin:0 0 12px 0;font-size:13px;color:#8f9bb3;line-height:1.5;">%s</p>',
                    esc_html($level['description'])
                );
            }

            $output .= sprintf(
                '<a href="%1$s" style="display:inline-flex;align-items:center;gap:4px;font-size:13px;font-weight:500;color:%2$s;text-decoration:none;">%3$s <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg></a>',
                esc_url($detailUrl ?: '#'),
                esc_attr($level['color']),
                esc_html__('Xem chi tiết', 'jankx')
            );

            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }
}