<?php

namespace Jankx\Extensions\MembershipLevels\Blocks;

use Jankx\Extensions\MembershipLevels\Block;
use Jankx\Extensions\MembershipLevels\Support\CurrentLevel;

/**
 * Level name line of the summary.
 *
 * The text is dynamic (it comes from the user's level) so this cannot be a
 * static RichText block without freezing the tier name at save time. Instead the
 * editor gets a `text` override, and an empty override means "show the level's
 * own name" — which is what keeps the block correct after a user is promoted.
 */
class LevelSummaryNameBlock extends Block
{
    protected $blockId = 'jankx/level-summary-name';

    /**
     * Only headings h2–h6 are allowed; h1 would fight the page title and the
     * value comes straight from markup.
     */
    const ALLOWED_TAGS = ['h2', 'h3', 'h4', 'h5', 'h6'];

    public function render($attributes, $content = '', $block = null)
    {
        if (!CurrentLevel::exists()) {
            return '';
        }

        $level = CurrentLevel::resolve();

        $override = trim((string) ($attributes['text'] ?? ''));
        $text = $override !== '' ? $override : $level['name'];

        if ($text === '') {
            return '';
        }

        $tag = strtolower((string) ($attributes['tagName'] ?? 'h3'));
        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            $tag = 'h3';
        }

        $fontSize = (int) ($attributes['fontSize'] ?? 17);
        $useLevelColor = !empty($attributes['useLevelColor']);
        $color = $useLevelColor && !empty($level['color']) ? $level['color'] : '';

        $style = 'margin:0 0 6px 0;';
        if ($fontSize > 0) {
            $style .= sprintf('font-size:%dpx;', $fontSize);
        }
        if ($color !== '') {
            $style .= sprintf('color:%s;', $color);
        }

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-level-summary-name',
            'style' => $style,
        ]);

        return sprintf(
            '<%1$s %2$s>%3$s</%1$s>',
            $tag,
            $wrapperAttrs,
            esc_html($text)
        );
    }
}