<?php

namespace Jankx\Extensions\MembershipLevels\Support;

use Jankx\Extensions\MembershipLevels\MembershipLevelsExtension;

/**
 * Resolves the level data the summary blocks render from.
 *
 * The summary used to be one block that reached into MembershipLevelsExtension
 * directly. Splitting it into separate icon/name/description blocks means four
 * classes need the same answer to "what level is this user, and what does that
 * level look like". Centralising it here keeps them consistent and means a site
 * can override the whole thing with one filter instead of four code edits.
 *
 * @package Jankx\Extensions\MembershipLevels\Support
 */
class CurrentLevel
{
    /**
     * Fallback used when the extension instance is unavailable or the stored
     * level slug no longer exists (a level was deleted while the user still had
     * it assigned).
     */
    const FALLBACK_SLUG = 'silver';

    /**
     * Resolve level data for a user.
     *
     * @param int $userId Defaults to the current user.
     * @return array{slug:string,name:string,description:string,color:string,icon:string}
     */
    public static function resolve(int $userId = 0): array
    {
        if ($userId <= 0) {
            $userId = get_current_user_id();
        }

        $levels = MembershipLevelsExtension::getLevels();
        $slug   = self::slug($userId, $levels);

        $level = $levels[$slug] ?? [];
        if (!$level) {
            $level = $levels[array_key_first($levels)] ?? [];
        }

        return [
            'slug'        => $slug,
            'name'        => (string) ($level['name'] ?? ucfirst($slug)),
            'description' => (string) ($level['description'] ?? ''),
            'color'       => (string) ($level['color'] ?? '#CD7F32'),
            // A level may carry a hand-picked icon; otherwise fall back to the
            // built-in artwork for the slug.
            'icon'        => (string) ($level['icon'] ?? ''),
        ];
    }

    /**
     * The user's level slug, filtered so a site can override the lookup.
     */
    protected static function slug(int $userId, array $levels): string
    {
        $extension = MembershipLevelsExtension::get_instance();

        $slug = $extension
            ? (string) $extension->getUserLevel($userId)
            : (string) get_user_meta($userId, MembershipLevelsExtension::USER_LEVEL_META, true);

        /**
         * Filter the level slug resolved for the summary blocks.
         *
         * @param string $slug    Level slug.
         * @param int    $userId  User being resolved.
         * @param array  $levels  All known levels.
         */
        $slug = (string) apply_filters('jankx/membership/summary_level_slug', $slug, $userId, $levels);

        return $slug !== '' ? $slug : self::FALLBACK_SLUG;
    }

    /**
     * Whether there is anything worth rendering at all.
     */
    public static function exists(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Built-in SVG artwork per level, used when the level defines no icon.
     *
     * These are stroke-based so they inherit `currentColor` from the level
     * colour and stay legible against the white badge.
     */
    public static function defaultIcon(string $slug): string
    {
        $icons = [
            'bronze'   => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
            'silver'   => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>',
            'gold'     => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            'platinum' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h12l4 6-10 13L2 9z"/><path d="M2 9h20"/></svg>',
            'diamond'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h12l4 6-10 13L2 9z"/><path d="M2 9h20"/></svg>',
        ];

        return $icons[$slug] ?? $icons[self::FALLBACK_SLUG];
    }
}