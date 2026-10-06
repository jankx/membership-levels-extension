<?php

namespace Jankx\Extensions\MembershipLevels\Blocks;

use Jankx\Extensions\MembershipLevels\Block;
use Jankx\Extensions\MembershipLevels\Support\CurrentLevel;

/**
 * Level description line of the summary.
 *
 * The text is dynamic (it comes from the user's level) so this cannot be a
 * static RichText block without freezing the tier description at save time.
 * Instead the editor gets a `text` override, and an empty override means "show
 * the level's own description".
 *
 * The "view details" link used to be rendered from here; it is now its own
 * block (jankx/level-summary-link). Its attributes stay declared in block.json
 * so blocks saved before the split keep validating server-side — they are just
 * ignored when rendering.
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

        $override    = trim((string) ($attributes['text'] ?? ''));
        $description = $override !== '' ? $override : $level['description'];

        if ($description === '') {
            return '';
        }

        $fontSize = (int) ($attributes['fontSize'] ?? 13);

        $style = 'margin:0;font-size:' . $fontSize . 'px;color:#8f9bb3;line-height:1.5;';

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-level-summary-description',
            'style' => $style,
        ]);

        return sprintf(
            '<div class="jankx-level-summary-description-wrap"><p %1$s>%2$s</p></div>',
            $wrapperAttrs,
            esc_html($description)
        );
    }
}
