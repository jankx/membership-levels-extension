<?php
namespace Jankx\Extensions\MembershipLevels;

use Jankx\Extensions\AbstractExtension;

class MembershipLevelsExtension extends AbstractExtension
{
    protected static $instance;

    const LEVELS_OPTION = 'jankx_membership_levels';
    const CRITERIA_OPTION = 'jankx_membership_criteria';
    const USER_LEVEL_META = 'jankx_membership_level';

    const LEVELS_CACHE_KEY = 'jankx_membership_levels_data';
    const LEVELS_CACHE_TTL = 12 * HOUR_IN_SECONDS;

    /** Per-request memo so a single request never rebuilds the level list twice. */
    protected static array $levelsMemo = [];

    /** Per-request memo for benefit terms, dropped together with the levels cache. */
    protected static array $benefitMemo = [];

    public function __construct()
    {
        $this->register_autoloader();
        parent::__construct();
    }

    protected function register_autoloader()
    {
        spl_autoload_register(function ($class) {
            $prefix = 'Jankx\\Extensions\\MembershipLevels\\';
            $base_dir = __DIR__ . '/src/';
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }
            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        });
    }

    public function init(): void
    {
        self::$instance = $this;
    }

    public static function get_instance(): ?self
    {
        return self::$instance;
    }

    public function register_hooks(): void
    {
        // CPT for membership levels
        (new PostTypes\MembershipLevelPostType())->register();

        // Term meta: SVG icon for membership_benefit taxonomy
        (new Admin\BenefitTermMeta())->register();

        // Seed default levels on first activation
        add_action('admin_init', [$this, 'maybeSeedDefaultLevels']);

        // The level list is cached because every account render reads it.
        // The cache is only dropped when membership_level data changes.
        $this->registerCacheHooks();

        // One-time slug migration: 4-tier (bronze/silver/gold/diamond)
        // -> 3-tier (silver=Bạc, gold=Vàng, platinum=Bạch kim)
        add_action('init', [$this, 'maybeMigrateLevelSlugs'], 60);

        // Benefits seeder lives in the theme: seeders/membership-benefits-data.php
        // (run via `wp eval-file` — see that file's docblock).

        // Gutenberg blocks
        if (did_action('init')) {
            $this->registerBlocks();
        } else {
            add_action('init', [$this, 'registerBlocks']);
        }

        // Sub-page "Hội viên" removed: the membership overview section already
        // renders on the account Overview tab, so the extra menu item (sidebar
        // + header dropdown) only duplicated it.

        // Overview membership section: replace the legacy hardcoded card with
        // the level slider/switcher. Runs on init so it executes after the
        // legacy default callbacks have been registered by my-account.
        if (did_action('init')) {
            $this->registerOverviewHook();
        } else {
            add_action('init', [$this, 'registerOverviewHook'], 50);
        }

        // Admin pages
        if (is_admin()) {
            (new Admin\SettingsPage())->register();
        }

        // REST API
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Hook into order completed to auto-evaluate membership
        add_action('jankx/ecommerce/checkout/completed', [$this, 'onCheckoutCompleted']);
        add_action('jankx/ecommerce/payment/paid', [$this, 'onPaymentPaid']);

        // Manual level assignment via admin
        add_action('show_user_profile', [$this, 'renderUserLevelField']);
        add_action('edit_user_profile', [$this, 'renderUserLevelField']);
        add_action('personal_options_update', [$this, 'saveUserLevelField']);
        add_action('edit_user_profile_update', [$this, 'saveUserLevelField']);
    }

    public function registerBlocks(): void
    {
        $blocksDir = __DIR__ . '/blocks';
        if (!is_dir($blocksDir)) {
            return;
        }

        $blockPath = $blocksDir;
        if (file_exists($blockPath . '/block.json')) {
            $block = new \Jankx\Extensions\MembershipLevels\Blocks\AccountTabMembershipBlock($blockPath);
            $block->setBlockPath($blockPath);
            $block->boot();
            $block->register();
        }

        $childBlocks = [
            'membership-current-tier'  => \Jankx\Extensions\MembershipLevels\Blocks\MembershipCurrentTierBlock::class,
            'membership-privileges'    => \Jankx\Extensions\MembershipLevels\Blocks\MembershipPrivilegesBlock::class,
            'membership-all-levels'    => \Jankx\Extensions\MembershipLevels\Blocks\MembershipAllLevelsBlock::class,
            'account-level-summary'    => \Jankx\Extensions\MembershipLevels\Blocks\AccountLevelSummaryBlock::class,
            // Inner blocks of account-level-summary. Registered at the same time
            // as the parent so the editor can drop them straight in.
            'level-summary-icon'       => \Jankx\Extensions\MembershipLevels\Blocks\LevelSummaryIconBlock::class,
            'level-summary-name'       => \Jankx\Extensions\MembershipLevels\Blocks\LevelSummaryNameBlock::class,
            'level-summary-description' => \Jankx\Extensions\MembershipLevels\Blocks\LevelSummaryDescriptionBlock::class,
            'level-summary-link'       => \Jankx\Extensions\MembershipLevels\Blocks\LevelSummaryLinkBlock::class,
        ];

        foreach ($childBlocks as $dirName => $blockClass) {
            $childPath = $blocksDir . '/' . $dirName;
            if (!file_exists($childPath . '/block.json')) {
                continue;
            }
            $block = new $blockClass($childPath);
            $block->setBlockPath($childPath);
            $block->boot();
            $block->register();
        }
    }

    /**
     * Swap the legacy hardcoded overview membership card for the level
     * slider/switcher provided by this extension.
     */
    public function registerOverviewHook(): void
    {
        remove_action('jankx/my_account/overview/membership', ['\Jankx\Extensions\MyAccount\Shortcode\OverviewTab', 'renderMembership']);
        add_action('jankx/my_account/overview/membership', [\Jankx\Extensions\MembershipLevels\MyAccount\OverviewMembership::class, 'render']);
    }

    /**
     * Seed default membership level CPT posts on first run
     */
    public function maybeSeedDefaultLevels(): void
    {
        // Manual trigger: ?run_seed=1
        if (isset($_GET['run_seed']) && current_user_can('manage_options')) {
            delete_option('jankx_membership_levels_seeded');
        }

        $done = get_option('jankx_membership_levels_seeded', false);
        if ($done) {
            return;
        }

        $this->seedDefaultLevels();
        update_option('jankx_membership_levels_seeded', true);
    }

    protected function seedDefaultLevels(): void
    {
        $defaults = self::getLevels();

        foreach ($defaults as $slug => $level) {
            // Check if already exists by slug meta
            $existing = get_posts([
                'post_type'   => 'membership_level',
                'meta_query'  => [
                    ['key' => '_level_slug', 'value' => $slug],
                ],
                'numberposts' => 1,
                'fields'      => 'ids',
            ]);

            if (!empty($existing)) {
                continue;
            }

            $postId = wp_insert_post([
                'post_type'    => 'membership_level',
                'post_title'   => $level['name'],
                'post_content' => $level['description'] ?? '',
                'post_status'  => 'publish',
            ]);

            if ($postId && !is_wp_error($postId)) {
                update_post_meta($postId, '_level_slug', $slug);
                update_post_meta($postId, '_level_color', $level['color'] ?? '#999999');
                update_post_meta($postId, '_level_priority', $level['priority'] ?? 0);
                update_post_meta($postId, '_level_discount', $level['discount'] ?? 0);
                update_post_meta($postId, '_level_criteria', $level['criteria'] ?? []);
            }
        }
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        $controller = new Api\RestApiController();
        $controller->registerRoutes();
    }

    /**
     * Auto-evaluate membership level after checkout
     */
    public function onCheckoutCompleted($order): void
    {
        $userId = $order->getCustomerId();
        if (!$userId) {
            return;
        }

        $this->evaluateAndSetLevel($userId);
    }

    /**
     * Auto-evaluate membership level after payment
     */
    public function onPaymentPaid($order, $transactionId = ''): void
    {
        $userId = $order->getCustomerId();
        if (!$userId) {
            return;
        }

        $this->evaluateAndSetLevel($userId);
    }

    /**
     * Evaluate criteria and set membership level for a user
     */
    public function evaluateAndSetLevel(int $userId): string
    {
        $engine = new Engine\RuleEngine();
        $levels = self::getLevels();
        $stats = $engine->getUserStats($userId);
        $level = $engine->findBestLevel($userId, $levels, $stats);

        if ($level) {
            $this->setUserLevel($userId, $level);
        }

        return $level ?? '';
    }

    /**
     * Set membership level for a user
     */
    public function setUserLevel(int $userId, string $level): bool
    {
        return update_user_meta($userId, self::USER_LEVEL_META, $level);
    }

    /**
     * Get membership level for a user
     */
    public function getUserLevel(int $userId): string
    {
        return get_user_meta($userId, self::USER_LEVEL_META, true) ?: 'silver';
    }

    /**
     * One-time migration of legacy level slugs (bronze -> silver,
     * diamond -> platinum) after the switch to the 3-tier structure.
     */
    public function maybeMigrateLevelSlugs(): void
    {
        if (get_option('jankx_membership_levels_3tier_migrated')) {
            return;
        }

        $map = [
            'bronze' => 'silver',
            'diamond' => 'platinum',
        ];

        $users = get_users([
            'meta_key' => self::USER_LEVEL_META,
            'meta_value' => array_keys($map),
            'meta_compare' => 'IN',
            'fields' => ['ID'],
        ]);

        foreach ($users as $user) {
            $old = get_user_meta($user->ID, self::USER_LEVEL_META, true);
            if (isset($map[$old])) {
                update_user_meta($user->ID, self::USER_LEVEL_META, $map[$old]);
            }
        }

        update_option('jankx_membership_levels_3tier_migrated', 1, false);
    }

    /**
     * Benefit items (title + description pairs) for a level slug, from the
     * membership_benefit taxonomy. Returns null until the theme seeder has
     * run (seeders/membership-benefits-data.php via `wp eval-file`) so
     * callers fall back to whatever privileges data exists.
     *
     * @return array<int, array{text: string, description: string}>|null
     */
    protected static function benefitTermsFor(string $slug): ?array
    {
        if (!taxonomy_exists(PostTypes\MembershipLevelPostType::BENEFIT_TAXONOMY)) {
            return null;
        }
        if (!get_option('jankx_membership_benefits_seeded')) {
            return null;
        }

        static $cache = [];
        if (array_key_exists($slug, $cache)) {
            return $cache[$slug];
        }

        $posts = get_posts([
            'post_type'   => PostTypes\MembershipLevelPostType::POST_TYPE,
            'numberposts' => 1,
            'post_status' => 'publish',
            'fields'      => 'ids',
            'meta_query'  => [
                ['key' => '_level_slug', 'value' => $slug],
            ],
        ]);

        if (empty($posts)) {
            return $cache[$slug] = [];
        }

        $terms = wp_get_object_terms($posts[0], PostTypes\MembershipLevelPostType::BENEFIT_TAXONOMY, [
            'orderby' => 'term_order',
            'order'   => 'ASC',
        ]);
        if (is_wp_error($terms)) {
            return $cache[$slug] = [];
        }

        $items = [];
        foreach ($terms as $term) {
            $items[] = [
                'text'        => $term->name,
                'description' => (string) $term->description,
            ];
        }

        return $cache[$slug] = $items;
    }

    /**
     * Get all defined membership levels
     *
     * Resolution order per level: built-in defaults, then the historical
     * `jankx_membership_levels` option, then the `membership_level` posts. The
     * CPT is what an admin actually edits (slug, colour, priority, criteria),
     * so it has the final say — without it the frontend kept showing the
     * hardcoded defaults no matter what was saved in admin.
     *
     * The result is cached because every account render reads it; the cache is
     * dropped by flushLevelsCache() whenever membership_level data changes.
     */
    public static function getLevels(): array
    {
        if (isset(self::$levelsMemo[0])) {
            return self::$levelsMemo[0];
        }

        $cached = get_transient(self::LEVELS_CACHE_KEY);
        if (is_array($cached)) {
            return self::$levelsMemo[0] = $cached;
        }

        $defaults = self::defaultLevels();

        $custom = get_option(self::LEVELS_OPTION, []);
        $custom = is_array($custom) ? $custom : [];

        // Merge per level so a saved level without e.g. privileges still
        // inherits the defaults for that level.
        $levels = [];
        foreach ($defaults as $slug => $level) {
            $saved = isset($custom[$slug]) && is_array($custom[$slug])
                ? $custom[$slug]
                : [];
            $levels[$slug] = wp_parse_args($saved, $level);
        }
        foreach ($custom as $slug => $level) {
            if (!isset($levels[$slug]) && is_array($level)) {
                $levels[$slug] = $level;
            }
        }

        // The CPT is the admin-facing source of truth.
        foreach (self::levelsFromPosts($levels) as $slug => $level) {
            $levels[$slug] = isset($levels[$slug]) && is_array($levels[$slug])
                ? array_merge($levels[$slug], $level)
                : $level;
        }

        // One canonical criteria shape for every consumer: a list of
        // {type, min, max} rows, the same form the meta box saves.
        foreach ($levels as $slug => $level) {
            $levels[$slug]['criteria'] = self::normalizeCriteria($level['criteria'] ?? []);
            $levels[$slug]['priority'] = (int) ($level['priority'] ?? 0);
        }

        // Benefits: membership_benefit taxonomy (terms assigned on
        // membership_level posts) — seeded by the theme's
        // seeders/membership-benefits-data.php. Until then privileges stay
        // unset and renderers show their empty state.
        foreach (array_keys($levels) as $slug) {
            $terms = self::benefitTermsFor($slug);
            if ($terms !== null) {
                $levels[$slug]['privileges'] = $terms;
            }
        }

        set_transient(self::LEVELS_CACHE_KEY, $levels, self::LEVELS_CACHE_TTL);

        return self::$levelsMemo[0] = $levels;
    }

    /**
     * Built-in levels. Kept in code so a fresh install (no CPT rows yet) still
     * renders a usable account overview and can seed the CPT from these.
     */
    protected static function defaultLevels(): array
    {
        return [
            'silver' => [
                'name' => 'Bạc',
                'description' => 'Thành viên Bạc',
                'color' => '#C0C0C0',
                'priority' => 10,
                'criteria' => [
                    'total_orders' => ['min' => 3],
                    'total_spent' => ['min' => 5000000],
                ],
            ],
            'gold' => [
                'name' => 'Vàng',
                'description' => 'Thành viên Vàng',
                'color' => '#FFD700',
                'priority' => 20,
                'criteria' => [
                    'total_orders' => ['min' => 10],
                    'total_spent' => ['min' => 20000000],
                ],
            ],
            'platinum' => [
                'name' => 'Bạch kim',
                'description' => 'Thành viên Bạch kim',
                'color' => '#B9F2FF',
                'priority' => 30,
                'criteria' => [
                    'total_orders' => ['min' => 20],
                    'total_spent' => ['min' => 50000000],
                ],
            ],
        ];
    }

    /**
     * Build levels from the membership_level posts.
     *
     * Only the fields an admin can edit are returned; anything missing or
     * invalid is simply left out so the caller keeps the option/default value.
     * $fallback supplies the current name/description for posts with an empty
     * title/content.
     */
    protected static function levelsFromPosts(array $fallback): array
    {
        if (!post_type_exists(PostTypes\MembershipLevelPostType::POST_TYPE)) {
            return [];
        }

        $posts = get_posts([
            'post_type'        => PostTypes\MembershipLevelPostType::POST_TYPE,
            'post_status'      => 'any',
            'numberposts'      => -1,
            'orderby'          => 'menu_order ID',
            'order'            => 'ASC',
            'suppress_filters' => false,
        ]);

        $levels = [];
        foreach ($posts as $post) {
            $slug = sanitize_key((string) (get_post_meta($post->ID, '_level_slug', true) ?: $post->post_name));
            if ($slug === '') {
                continue;
            }

            $level = [];

            $name = trim($post->post_title);
            if ($name !== '') {
                $level['name'] = $name;
            } elseif (!empty($fallback[$slug]['name'])) {
                $level['name'] = $fallback[$slug]['name'];
            }

            $description = trim($post->post_content);
            if ($description !== '') {
                $level['description'] = $description;
            } elseif (!empty($fallback[$slug]['description'])) {
                $level['description'] = $fallback[$slug]['description'];
            }

            // Only trust a colour WordPress recognises as a hex value, so a bad
            // meta value can never leak arbitrary text into the inline style.
            $color = sanitize_hex_color((string) get_post_meta($post->ID, '_level_color', true));
            if ($color) {
                $level['color'] = $color;
            }

            $priority = get_post_meta($post->ID, '_level_priority', true);
            if ($priority !== '' && $priority !== false && $priority !== null) {
                $level['priority'] = (int) $priority;
            }

            $discount = get_post_meta($post->ID, '_level_discount', true);
            if ($discount !== '' && $discount !== false && $discount !== null) {
                $level['discount'] = (float) $discount;
            }

            $criteria = get_post_meta($post->ID, '_level_criteria', true);
            if (is_array($criteria) && $criteria !== []) {
                $level['criteria'] = $criteria;
            }

            $levels[$slug] = $level;
        }

        return $levels;
    }

    /**
     * Normalise criteria into one shape every consumer understands: a list of
     * ['type' => 'total_orders', 'min' => 3, 'max' => 0] rows.
     *
     * Levels have historically been stored as an associative map
     * (['total_orders' => ['min' => 3]]) while the meta box saves a list
     * ([['type' => 'total_orders', 'min' => 3]]), so both are accepted here.
     * Rows with no bound at all are dropped: they match every user and only
     * add noise to the rendered criteria list.
     */
    public static function normalizeCriteria(array $criteria): array
    {
        $normalized = [];

        foreach ($criteria as $key => $row) {
            if (is_int($key) && is_array($row)) {
                $type = (string) ($row['type'] ?? '');
            } elseif (is_string($key) && is_array($row)) {
                $type = $key;
                $row  = ['min' => $row['min'] ?? 0, 'max' => $row['max'] ?? 0];
            } else {
                continue;
            }

            $type = sanitize_key($type);
            if ($type === '') {
                continue;
            }

            $min = (float) ($row['min'] ?? 0);
            $max = (float) ($row['max'] ?? 0);
            if ($min <= 0 && $max <= 0) {
                continue;
            }

            $normalized[] = [
                'type' => $type,
                'min'  => $min,
                'max'  => $max,
            ];
        }

        return $normalized;
    }

    /**
     * Drop the cached level list. Called only when membership_level data (or
     * the criteria/benefit definitions feeding it) changes.
     */
    public static function flushLevelsCache(): void
    {
        self::$levelsMemo  = [];
        self::$benefitMemo = [];
        delete_transient(self::LEVELS_CACHE_KEY);
    }

    protected function registerCacheHooks(): void
    {
        $postType = PostTypes\MembershipLevelPostType::POST_TYPE;

        // Priority 99 so the meta box handler (priority 10) has already written
        // the _level_* meta before the cache is dropped.
        add_action("save_post_{$postType}", [__CLASS__, 'flushLevelsCacheOnSave'], 99, 3);
        add_action('trashed_post', [__CLASS__, 'flushLevelsCacheOnStatusChange'], 99);
        add_action('untrashed_post', [__CLASS__, 'flushLevelsCacheOnStatusChange'], 99);
        add_action('before_delete_post', [__CLASS__, 'flushLevelsCacheOnStatusChange'], 99);

        // Programmatic update_post_meta() calls never fire save_post.
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            add_action($hook, [__CLASS__, 'flushLevelsCacheOnMeta'], 99, 4);
        }

        // Benefit terms attached to a level feed privileges into the cache.
        add_action('set_object_terms', [__CLASS__, 'flushLevelsCacheOnTerms'], 99, 4);
        add_action('created_' . PostTypes\MembershipLevelPostType::BENEFIT_TAXONOMY, [__CLASS__, 'flushLevelsCache']);
        add_action('edited_' . PostTypes\MembershipLevelPostType::BENEFIT_TAXONOMY, [__CLASS__, 'flushLevelsCache']);
        add_action('delete_' . PostTypes\MembershipLevelPostType::BENEFIT_TAXONOMY, [__CLASS__, 'flushLevelsCache']);

        // Criteria definitions and the legacy level option are inputs too.
        foreach ([self::LEVELS_OPTION, self::CRITERIA_OPTION] as $option) {
            add_action('update_option_' . $option, [__CLASS__, 'flushLevelsCache']);
            add_action('add_option_' . $option, [__CLASS__, 'flushLevelsCache']);
            add_action('delete_option_' . $option, [__CLASS__, 'flushLevelsCache']);
        }
    }

    public static function flushLevelsCacheOnSave(int $postId, $post = null, bool $update = false): void
    {
        if (($post instanceof \WP_Post && $post->post_type === PostTypes\MembershipLevelPostType::POST_TYPE)
            || (!$post && get_post_type($postId) === PostTypes\MembershipLevelPostType::POST_TYPE)) {
            self::flushLevelsCache();
        }
    }

    public static function flushLevelsCacheOnStatusChange(int $postId, $status = null): void
    {
        if (get_post_type($postId) === PostTypes\MembershipLevelPostType::POST_TYPE) {
            self::flushLevelsCache();
        }
    }

    public static function flushLevelsCacheOnMeta(int $metaId, int $objectId, string $metaKey, $metaValue = ''): void
    {
        if (strpos($metaKey, '_level_') !== 0) {
            return;
        }
        if (get_post_type($objectId) === PostTypes\MembershipLevelPostType::POST_TYPE) {
            self::flushLevelsCache();
        }
    }

    public static function flushLevelsCacheOnTerms(int $objectId, $terms, $ttIds, string $taxonomy): void
    {
        if ($taxonomy !== PostTypes\MembershipLevelPostType::BENEFIT_TAXONOMY) {
            return;
        }
        if (get_post_type($objectId) === PostTypes\MembershipLevelPostType::POST_TYPE) {
            self::flushLevelsCache();
        }
    }

    /**
     * Get all criteria definitions
     */
    public static function getCriteria(): array
    {
        $defaults = [
            'total_orders' => [
                'label' => 'Tổng số đơn hàng',
                'description' => 'Tổng số đơn hàng đã hoàn thành',
                'type' => 'number',
            ],
            'total_spent' => [
                'label' => 'Tổng chi tiêu',
                'description' => 'Tổng số tiền đã thanh toán',
                'type' => 'currency',
            ],
            'account_age_days' => [
                'label' => 'Tuổi tài khoản (ngày)',
                'description' => 'Số ngày kể từ khi đăng ký',
                'type' => 'number',
            ],
            'last_order_days' => [
                'label' => 'Ngày đặt hàng gần nhất',
                'description' => 'Số ngày kể từ đơn hàng cuối cùng',
                'type' => 'number',
            ],
        ];

        $custom = get_option(self::CRITERIA_OPTION, []);
        return wp_parse_args($custom, $defaults);
    }

    /**
     * Render membership level field on user profile
     */
    public function renderUserLevelField($user): void
    {
        $currentLevel = $this->getUserLevel($user->ID);
        $levels = self::getLevels();
        $levelData = $levels[$currentLevel] ?? $levels[array_key_first($levels)];
        $isAdmin = current_user_can('manage_options');
        ?>
        <h2>Hạng thành viên</h2>
        <table class="form-table">
            <tr>
                <th><label for="jankx_membership_level">Hạng hiện tại</label></th>
                <td>
                    <?php if ($isAdmin): ?>
                        <select id="jankx_membership_level" name="jankx_membership_level">
                            <?php foreach ($levels as $slug => $level): ?>
                                <option value="<?php echo esc_attr($slug); ?>" <?php selected($currentLevel, $slug); ?>>
                                    <?php echo esc_html($level['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Chọn hạng thành viên thủ công. Bỏ trống để hệ thống tự động đánh giá.</p>
                    <?php else: ?>
                        <span style="display:inline-block;width:12px;height:12px;border-radius:50;background:<?php echo esc_attr($levelData['color']); ?>;vertical-align:middle;margin-right:6px;"></span>
                        <strong><?php echo esc_html($levelData['name']); ?></strong>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save membership level field from user profile
     */
    public function saveUserLevelField(int $userId): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['jankx_membership_level'])) {
            $level = sanitize_text_field($_POST['jankx_membership_level']);
            if ($level) {
                $this->setUserLevel($userId, $level);
            }
        }
    }
}
