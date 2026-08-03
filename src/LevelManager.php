<?php
namespace Jankx\Extensions\MembershipLevels;

class LevelManager
{
    const USER_LEVEL_META_KEY = 'jankx_membership_level';

    protected static $levels = [];
    protected static $instance;

    public function __construct()
    {
        self::$instance = $this;
        $this->registerDefaultLevels();
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    protected function registerDefaultLevels(): void
    {
        $this->registerLevel('bronze', [
            'name'        => 'Bronze',
            'description' => 'New member. Accumulate points to upgrade.',
            'priority'    => 1,
            'color'       => '#CD7F32',
            'icon'        => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
            'privileges'  => [],
        ]);

        $this->registerLevel('silver', [
            'name'        => 'Silver',
            'description' => 'Exclusive deals and offers just for you.',
            'priority'    => 2,
            'color'       => '#94A3B8',
            'icon'        => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>',
            'privileges'  => [],
        ]);

        $this->registerLevel('gold', [
            'name'        => 'Gold',
            'description' => 'Premium benefits and VIP service.',
            'priority'    => 3,
            'color'       => '#F59E0B',
            'icon'        => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            'privileges'  => [],
        ]);
    }

    /**
     * Register a membership level
     */
    public function registerLevel(string $slug, array $args): void
    {
        $defaults = [
            'name'        => '',
            'description' => '',
            'priority'    => 100,
            'color'       => '#666',
            'icon'        => '',
            'privileges'  => [],
        ];

        self::$levels[$slug] = wp_parse_args($args, $defaults);
    }

    /**
     * Get all registered levels sorted by priority
     */
    public function getLevels(): array
    {
        $levels = self::$levels;
        uasort($levels, fn($a, $b) => ($a['priority'] ?? 100) <=> ($b['priority'] ?? 100));
        return $levels;
    }

    /**
     * Get a specific level
     */
    public function getLevel(string $slug): ?array
    {
        return self::$levels[$slug] ?? null;
    }

    /**
     * Get user's current level
     */
    public function getUserLevel(int $userId): string
    {
        $level = get_user_meta($userId, self::USER_LEVEL_META_KEY, true);
        if (empty($level) || !isset(self::$levels[$level])) {
            return 'bronze';
        }
        return $level;
    }

    /**
     * Set user's level
     */
    public function setUserLevel(int $userId, string $levelSlug): bool
    {
        if (!isset(self::$levels[$levelSlug])) {
            return false;
        }
        return (bool) update_user_meta($userId, self::USER_LEVEL_META_KEY, $levelSlug);
    }

    /**
     * Get user's level info
     */
    public function getUserLevelInfo(int $userId): array
    {
        $slug = $this->getUserLevel($userId);
        $level = $this->getLevel($slug);
        $level['slug'] = $slug;
        return $level;
    }

    /**
     * Check if user has at least a certain level
     */
    public function userHasLevel(int $userId, string $minimumLevel): bool
    {
        $currentSlug = $this->getUserLevel($userId);
        $current = $this->getLevel($currentSlug);
        $minimum = $this->getLevel($minimumLevel);

        if (!$current || !$minimum) {
            return false;
        }

        return ($current['priority'] ?? 0) >= ($minimum['priority'] ?? 0);
    }

    /**
     * Check if a level has a specific privilege
     */
    public function levelHasPrivilege(string $levelSlug, string $privilege): bool
    {
        $level = $this->getLevel($levelSlug);
        if (!$level) {
            return false;
        }
        return in_array($privilege, $level['privileges'] ?? []);
    }

    /**
     * Check if user has a specific privilege
     */
    public function userHasPrivilege(int $userId, string $privilege): bool
    {
        $levelSlug = $this->getUserLevel($userId);
        return $this->levelHasPrivilege($levelSlug, $privilege);
    }
}
