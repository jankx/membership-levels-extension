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
        return get_user_meta($userId, self::USER_LEVEL_META, true) ?: 'bronze';
    }

    /**
     * Get all defined membership levels
     */
    public static function getLevels(): array
    {
        $defaults = [
            'bronze' => [
                'name' => 'Bronze',
                'description' => 'Thành viên mới',
                'color' => '#CD7F32',
                'priority' => 0,
                'criteria' => [],
            ],
            'silver' => [
                'name' => 'Silver',
                'description' => 'Thành viên bạc',
                'color' => '#C0C0C0',
                'priority' => 10,
                'criteria' => [
                    'total_orders' => ['min' => 3],
                    'total_spent' => ['min' => 5000000],
                ],
            ],
            'gold' => [
                'name' => 'Gold',
                'description' => 'Thành viên vàng',
                'color' => '#FFD700',
                'priority' => 20,
                'criteria' => [
                    'total_orders' => ['min' => 10],
                    'total_spent' => ['min' => 20000000],
                ],
            ],
            'diamond' => [
                'name' => 'Diamond',
                'description' => 'Thành viên kim cương',
                'color' => '#B9F2FF',
                'priority' => 30,
                'criteria' => [
                    'total_orders' => ['min' => 20],
                    'total_spent' => ['min' => 50000000],
                ],
            ],
        ];

        $custom = get_option(self::LEVELS_OPTION, []);
        return wp_parse_args($custom, $defaults);
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
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        $currentLevel = $this->getUserLevel($user->ID);
        $levels = self::getLevels();
        ?>
        <h2>Hạng thành viên</h2>
        <table class="form-table">
            <tr>
                <th><label for="jankx_membership_level">Hạng hiện tại</label></th>
                <td>
                    <select id="jankx_membership_level" name="jankx_membership_level">
                        <?php foreach ($levels as $slug => $level): ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($currentLevel, $slug); ?>>
                                <?php echo esc_html($level['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Chọn hạng thành viên thủ công. Bỏ trống để hệ thống tự động đánh giá.</p>
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
        if (!current_user_can('edit_user', $userId)) {
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
