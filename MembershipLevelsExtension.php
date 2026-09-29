<?php
namespace Jankx\Extensions\MembershipLevels;

use Jankx\Extensions\AbstractExtension;

class MembershipLevelsExtension extends AbstractExtension
{
    protected static $instance;

    const LEVELS_OPTION = 'jankx_membership_levels';
    const CRITERIA_OPTION = 'jankx_membership_criteria';
    const USER_LEVEL_META = 'jankx_membership_level';

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

        // Seed default levels on first activation
        add_action('admin_init', [$this, 'maybeSeedDefaultLevels']);

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

        // Register sub-page with My Account
        add_action('jankx/my_account/register_sub_pages', [$this, 'registerAccountSubPage']);

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

    public function registerAccountSubPage(): void
    {
        if (!class_exists('\Jankx\Extensions\MyAccount\MyAccountExtension')) {
            return;
        }

        \Jankx\Extensions\MyAccount\MyAccountExtension::registerSubPageClass(new \Jankx\Extensions\MembershipLevels\MyAccount\MembershipSubPage());
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
     */
    public static function getLevels(): array
    {
        $defaults = [
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

        $custom = get_option(self::LEVELS_OPTION, []);

        // Merge per level so a saved level without e.g. privileges still
        // inherits the defaults for that level.
        $levels = [];
        foreach ($defaults as $slug => $level) {
            $saved = (is_array($custom) && isset($custom[$slug]) && is_array($custom[$slug]))
                ? $custom[$slug]
                : [];
            $levels[$slug] = wp_parse_args($saved, $level);
        }
        if (is_array($custom)) {
            foreach ($custom as $slug => $level) {
                if (!isset($levels[$slug]) && is_array($level)) {
                    $levels[$slug] = $level;
                }
            }
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

        return $levels;
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
