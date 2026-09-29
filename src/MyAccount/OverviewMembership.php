<?php

namespace Jankx\Extensions\MembershipLevels\MyAccount;

use Jankx\Extensions\MembershipLevels\Engine\RuleEngine;
use Jankx\Extensions\MembershipLevels\MembershipLevelsExtension;

/**
 * Overview "Hạng thành viên" section: a horizontal slider listing every
 * membership level as a selector (switcher). Selecting a level reveals that
 * level's detail panel, including its benefits (phúc lợi) rendered as
 * icon + text.
 */
class OverviewMembership
{
    public static function render($user): void
    {
        if (!($user instanceof \WP_User)) {
            return;
        }

        $levels = MembershipLevelsExtension::getLevels();
        if (empty($levels)) {
            return;
        }

        uasort($levels, static function ($a, $b) {
            return (($a['priority'] ?? 100) <=> ($b['priority'] ?? 100));
        });

        $currentSlug = self::currentSlug($user->ID, $levels);
        $points = (int) get_user_meta($user->ID, 'jankx_points', true);

        echo '<div class="jankx-level-switch" data-jankx-level-switch>';

        // ── Slider (selector) ──
        echo '<div class="jankx-level-switch__slider" role="tablist" aria-label="' . esc_attr__('Chọn hạng thành viên', 'jankx') . '">';
        foreach ($levels as $slug => $level) {
            $isCurrent = $slug === $currentSlug;
            $active = $isCurrent;
            printf(
                '<button type="button" class="jankx-level-switch__chip%s" role="tab" aria-selected="%s" data-level="%s" style="--chip-color: %s;">',
                $active ? ' is-active' : '',
                $active ? 'true' : 'false',
                esc_attr($slug),
                esc_attr($level['color'] ?? '#65A30D')
            );
            echo '<span class="jankx-level-switch__dot" aria-hidden="true"></span>';
            echo '<span class="jankx-level-switch__name">' . esc_html($level['name'] ?? $slug) . '</span>';
            if ($isCurrent) {
                echo '<span class="jankx-level-switch__tag">' . esc_html__('Hạng của bạn', 'jankx') . '</span>';
            }
            echo '</button>';
        }
        echo '</div>';

        // ── Detail panes ──
        echo '<div class="jankx-level-switch__panes">';
        foreach ($levels as $slug => $level) {
            self::renderDetail($slug, $level, $slug === $currentSlug, $levels, $user->ID, $points);
        }
        echo '</div>';

        echo '</div>';
    }

    protected static function renderDetail(string $slug, array $level, bool $isCurrent, array $levels, int $userId, int $points): void
    {
        $color = $level['color'] ?? '#65A30D';
        printf(
            '<div class="jankx-level-detail%s" role="tabpanel" data-level="%s" style="--level-color: %s;"%s>',
            $isCurrent ? ' is-active' : '',
            esc_attr($slug),
            esc_attr($color),
            $isCurrent ? '' : ' hidden'
        );

        // Head: icon + name + description
        echo '<div class="jankx-level-detail__head">';
        echo '<span class="jankx-level-detail__icon" aria-hidden="true">' . self::levelIcon($slug, $level) . '</span>';
        echo '<div class="jankx-level-detail__info">';
        echo '<div class="jankx-level-detail__title">';
        echo '<strong>' . esc_html($level['name'] ?? $slug) . '</strong>';
        if ($isCurrent) {
            echo '<span class="jankx-level-detail__current-tag">' . esc_html__('Hạng hiện tại', 'jankx') . '</span>';
        }
        echo '</div>';
        if (!empty($level['description'])) {
            echo '<p class="jankx-level-detail__desc">' . esc_html($level['description']) . '</p>';
        }
        echo '</div></div>';

        // Progress toward the next level (only on the user's own level)
        if ($isCurrent) {
            echo self::progressHtml($slug, $levels, $userId, $points);
        }

        // Benefits (phúc lợi) — icon + text
        echo '<h4 class="jankx-level-detail__subtitle">' . esc_html__('Quyền lợi', 'jankx') . '</h4>';
        $benefits = self::benefits($level);
        if (!empty($benefits)) {
            echo '<ul class="jankx-level-benefits">';
            foreach ($benefits as $benefit) {
                echo '<li class="jankx-level-benefit">';
                echo '<span class="jankx-level-benefit__icon" aria-hidden="true">' . self::benefitIcon($benefit['icon']) . '</span>';
                echo '<span class="jankx-level-benefit__text">' . esc_html($benefit['text']) . '</span>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<div class="jankx-level-detail__empty">' . esc_html__('Chưa có quyền lợi nào cho hạng này.', 'jankx') . '</div>';
        }

        // Upgrade criteria (other levels)
        if (!$isCurrent && !empty($level['criteria'])) {
            echo '<h4 class="jankx-level-detail__subtitle">' . esc_html__('Điều kiện', 'jankx') . '</h4>';
            echo '<ul class="jankx-level-criteria">';
            foreach (self::criteriaList($level['criteria']) as $line) {
                echo '<li>' . esc_html($line) . '</li>';
            }
            echo '</ul>';
        }

        echo '</div>';
    }

    /**
     * Progress rows toward the next level based on real user stats.
     */
    protected static function progressHtml(string $currentSlug, array $levels, int $userId, int $points): string
    {
        $slugs = array_keys($levels);
        $idx = array_search($currentSlug, $slugs, true);
        $nextSlug = ($idx !== false && isset($slugs[$idx + 1])) ? $slugs[$idx + 1] : '';

        if ($nextSlug === '') {
            return '<div class="jankx-level-progress jankx-level-progress--maxed">'
                . esc_html__('Bạn đang ở hạng cao nhất!', 'jankx')
                . '</div>';
        }

        $next = $levels[$nextSlug];
        $criteria = $next['criteria'] ?? [];
        if (empty($criteria)) {
            return '';
        }

        $stats = [];
        try {
            $stats = (new RuleEngine())->getUserStats($userId);
        } catch (\Throwable $e) {
            $stats = [];
        }

        $defs = MembershipLevelsExtension::getCriteria();

        $html = '<div class="jankx-level-progress">';
        $html .= '<div class="jankx-level-progress__title">'
            . sprintf(esc_html__('Tiếp theo: %s', 'jankx'), esc_html($next['name'] ?? $nextSlug))
            . '</div>';

        foreach ($criteria as $key => $criterion) {
            $min = is_array($criterion) ? (float) ($criterion['min'] ?? 0) : (float) $criterion;
            if ($min <= 0) {
                continue;
            }
            $value = (float) ($stats[$key] ?? 0);
            $type = $defs[$key]['type'] ?? 'number';
            $label = $defs[$key]['label'] ?? $key;
            $pct = min(100, round(($value / $min) * 100));

            $html .= '<div class="jankx-level-progress__row">';
            $html .= '<div class="jankx-level-progress__meta">';
            $html .= '<span class="jankx-level-progress__label">' . esc_html($label) . '</span>';
            $html .= '<span class="jankx-level-progress__value">'
                . esc_html(self::formatStat($value, $type)) . ' / ' . esc_html(self::formatStat($min, $type))
                . '</span>';
            $html .= '</div>';
            $html .= '<div class="jankx-level-progress__bar"><div class="jankx-level-progress__fill" style="width: ' . esc_attr($pct) . '%"></div></div>';
            $html .= '</div>';
        }

        if ($points > 0) {
            $html .= '<div class="jankx-level-progress__points">'
                . sprintf(esc_html__('Bạn hiện có %s điểm tích lũy.', 'jankx'), '<strong>' . esc_html(number_format($points)) . '</strong>')
                . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Normalise level privileges into ['text', 'icon'] pairs.
     * Supports plain strings, arrays with text/label and an optional icon,
     * or a single newline/comma separated string.
     */
    protected static function benefits(array $level): array
    {
        $raw = $level['privileges'] ?? [];

        if (is_string($raw)) {
            $raw = preg_split('/\r\n|\r|\n|,/', $raw) ?: [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $benefits = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $text = trim($item);
                $icon = '';
            } elseif (is_array($item)) {
                $text = trim((string) ($item['text'] ?? $item['label'] ?? $item['title'] ?? $item['name'] ?? ''));
                $icon = (string) ($item['icon'] ?? '');
            } else {
                continue;
            }

            if ($text === '') {
                continue;
            }

            $benefits[] = ['text' => $text, 'icon' => $icon];
        }

        return $benefits;
    }

    /**
     * Render a benefit icon: allowed SVG markup, a plain symbol (emoji/text),
     * or the default check icon.
     */
    protected static function benefitIcon(string $icon): string
    {
        $icon = trim($icon);
        if ($icon === '') {
            return self::defaultBenefitIcon();
        }

        if (strpos($icon, '<') !== false) {
            $clean = wp_kses($icon, self::svgAllowed());
            return trim($clean) !== '' ? $clean : self::defaultBenefitIcon();
        }

        return '<span class="jankx-level-benefit__symbol">' . esc_html($icon) . '</span>';
    }

    protected static function defaultBenefitIcon(): string
    {
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
    }

    protected static function levelIcon(string $slug, array $level): string
    {
        $icon = trim((string) ($level['icon'] ?? ''));
        if ($icon !== '') {
            if (strpos($icon, '<') !== false) {
                $clean = wp_kses($icon, self::svgAllowed());
                if (trim($clean) !== '') {
                    return $clean;
                }
            } else {
                return '<span class="jankx-level-detail__symbol">' . esc_html($icon) . '</span>';
            }
        }

        $icons = [
            'bronze'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
            'silver'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>',
            'gold'    => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            'diamond' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h12l4 6-10 13L2 9z"/><path d="M2 9h20"/></svg>',
        ];

        return $icons[$slug] ?? '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>';
    }

    protected static function criteriaList(array $criteria): array
    {
        $defs = MembershipLevelsExtension::getCriteria();
        $lines = [];

        foreach ($criteria as $key => $criterion) {
            $min = is_array($criterion) ? (float) ($criterion['min'] ?? 0) : (float) $criterion;
            if ($min <= 0) {
                continue;
            }
            $type = $defs[$key]['type'] ?? 'number';
            $label = $defs[$key]['label'] ?? $key;
            $lines[] = sprintf(esc_html__('Từ %s %s', 'jankx'), self::formatStat($min, $type), $label);
        }

        return $lines;
    }

    protected static function formatStat(float $value, string $type): string
    {
        if ($type === 'currency') {
            return number_format($value, 0, ',', '.') . '₫';
        }

        return ($value == (int) $value) ? (string) (int) $value : (string) $value;
    }

    protected static function currentSlug(int $userId, array $levels): string
    {
        $instance = MembershipLevelsExtension::get_instance();
        $slug = $instance
            ? $instance->getUserLevel($userId)
            : (get_user_meta($userId, MembershipLevelsExtension::USER_LEVEL_META, true) ?: '');

        if (!isset($levels[$slug])) {
            $slug = 'bronze';
        }
        if (!isset($levels[$slug])) {
            $slug = (string) array_key_first($levels);
        }

        return $slug;
    }

    protected static function svgAllowed(): array
    {
        $attrs = [
            'width' => true, 'height' => true, 'viewBox' => true, 'fill' => true,
            'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true,
            'stroke-linejoin' => true, 'class' => true, 'aria-hidden' => true,
        ];

        return [
            'svg'       => $attrs,
            'path'      => ['d' => true],
            'circle'    => ['cx' => true, 'cy' => true, 'r' => true],
            'rect'      => ['x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true],
            'polygon'   => ['points' => true],
            'polyline'  => ['points' => true],
            'line'      => ['x1' => true, 'y1' => true, 'x2' => true, 'y2' => true],
            'span'      => ['class' => true],
        ];
    }
}
