<?php

namespace Jankx\Extensions\MembershipLevels\Blocks;

use Jankx\Extensions\MembershipLevels\Block;
use Jankx\Extensions\MembershipLevels\Support\CurrentLevel;

/**
 * Badge icon of the level summary.
 *
 * Accepts an SVG Icon / Icon Picker / Advanced Image as an inner block, so the
 * badge can show real artwork instead of the built-in level glyph. When the slot
 * is empty it falls back to the level's own icon, which means dropping the block
 * in never produces a blank space.
 */
class LevelSummaryIconBlock extends Block
{
    protected $blockId = 'jankx/level-summary-icon';

    public function render($attributes, $content = '', $block = null)
    {
        if (!CurrentLevel::exists()) {
            return '';
        }

        $level = CurrentLevel::resolve();

        $size       = (int) ($attributes['size'] ?? 48);
        $iconSize   = (int) ($attributes['iconSize'] ?? 22);
        $borderWidth = (int) ($attributes['borderWidth'] ?? 2);
        $background = (string) ($attributes['backgroundColor'] ?? '#ffffff');
        $useLevelColor = !empty($attributes['useLevelColor']);
        $iconName   = (string) ($attributes['iconName'] ?? '');

        // Respect the level colour unless the author picked one in the editor.
        $color = $useLevelColor && !empty($level['color']) ? $level['color'] : '#CD7F32';

        $style = sprintf(
            'width:%1$dpx;height:%1$dpx;display:inline-flex;align-items:center;justify-content:center;border-radius:50%%;background:%2$s;color:%3$s;',
            $size,
            $background,
            $color
        );

        if ($borderWidth > 0) {
            $style .= sprintf('border:%1$dpx solid %2$s;', $borderWidth, $color);
        }

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-level-summary-icon',
            'style' => $style,
        ]);

        // An inner block was dropped in: let it speak for itself. We only keep
        // the badge frame around it, so the slot stays visually consistent.
        $inner = trim((string) $content);
        if ($inner !== '') {
            return sprintf(
                '<div %s><span class="jankx-level-summary-icon__inner" style="display:inline-flex;align-items:center;justify-content:center;">%s</span></div>',
                $wrapperAttrs,
                $inner
            );
        }

        $icon = $level['icon'] !== '' ? $level['icon'] : CurrentLevel::defaultIcon($level['slug']);

        // A named icon from the theme's SVG library wins over built-in artwork.
        if ($iconName !== '') {
            $named = $this->namedIcon($iconName, $iconSize);
            if ($named !== '') {
                $icon = $named;
            }
        }

        return sprintf('<div %s>%s</div>', $wrapperAttrs, $icon);
    }

    /**
     * Resolve an icon by name from the parent theme's SVG library.
     */
    protected function namedIcon(string $iconName, int $size): string
    {
        if (!class_exists('\Jankx\Foundation\Application')) {
            return '';
        }

        try {
            $app = \Jankx\Foundation\Application::getInstance();
            if (!$app || !$app->bound('font-icons.svg')) {
                return '';
            }

            $provider = $app->make('font-icons.svg');
            if (!method_exists($provider, 'getIconHtml')) {
                return '';
            }

            $html = (string) $provider->getIconHtml($iconName);

            return $html === '' ? '' : $this->scaleIcon($html, $size);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Rewrite width/height so a chosen icon matches the badge proportion.
     */
    protected function scaleIcon(string $html, int $size): string
    {
        return preg_replace(
            '/(width|height)="[0-9.]+"/i',
            sprintf('$1="%d"', $size),
            $html,
            2
        ) ?? $html;
    }
}