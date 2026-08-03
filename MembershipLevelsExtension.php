<?php
namespace Jankx\Extensions\MembershipLevels;

use Jankx\Extensions\AbstractExtension;

class MembershipLevelsExtension extends AbstractExtension
{
    protected static $instance;

    protected static $levelManager;

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
        self::$levelManager = new LevelManager();
    }

    public static function get_instance(): ?self
    {
        return self::$instance;
    }

    public static function getLevelManager(): LevelManager
    {
        return self::$levelManager;
    }

    public function register_hooks(): void
    {
        // Inject membership info into My Account page
        add_action('jankx/my_account/after_sidebar', [$this, 'renderMembershipBadge']);

        // Add REST API endpoint for level management
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        if (is_admin()) {
            add_action('show_user_profile', [$this, 'renderUserLevelField']);
            add_action('edit_user_profile', [$this, 'renderUserLevelField']);
            add_action('personal_options_update', [$this, 'saveUserLevelField']);
            add_action('edit_user_profile_update', [$this, 'saveUserLevelField']);
        }
    }

    /**
     * Render membership badge in My Account sidebar
     */
    public function renderMembershipBadge(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $userId = get_current_user_id();
        $levelInfo = self::$levelManager->getUserLevelInfo($userId);
        ?>
        <div class="jankx-membership-badge" style="--badge-color: <?php echo esc_attr($levelInfo['color']); ?>; background: linear-gradient(135deg, <?php echo esc_attr($levelInfo['color']); ?>15, <?php echo esc_attr($levelInfo['color']); ?>05); border: 1px solid <?php echo esc_attr($levelInfo['color']); ?>30; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 48px; height: 48px; border-radius: 50%; background: <?php echo esc_attr($levelInfo['color']); ?>20; display: flex; align-items: center; justify-content: center; color: <?php echo esc_attr($levelInfo['color']); ?>;">
                    <?php echo $levelInfo['icon']; ?>
                </div>
                <div style="flex: 1;">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: <?php echo esc_attr($levelInfo['color']); ?>;">
                        <?php echo esc_html($levelInfo['name']); ?>
                    </h3>
                    <p style="margin: 4px 0 0; font-size: 13px; color: #666; line-height: 1.4;">
                        <?php echo esc_html($levelInfo['description']); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('jankx/v1', '/membership-levels', [
            'methods'             => 'GET',
            'callback'            => [$this, 'restGetLevels'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('jankx/v1', '/membership-levels/(?P<user_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'restGetUserLevel'],
            'permission_callback' => [$this, 'restPermissionCheck'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
    }

    public function restGetLevels(): \WP_REST_Response
    {
        $levels = self::$levelManager->getLevels();
        return new \WP_REST_Response($levels, 200);
    }

    public function restGetUserLevel(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = (int) $request->get_param('user_id');
        $levelInfo = self::$levelManager->getUserLevelInfo($userId);
        return new \WP_REST_Response($levelInfo, 200);
    }

    public function restPermissionCheck(): bool
    {
        return current_user_can('edit_users');
    }

    /**
     * Render level field in admin user profile
     */
    public function renderUserLevelField($user): void
    {
        $currentLevel = self::$levelManager->getUserLevel($user->ID);
        $levels = self::$levelManager->getLevels();
        ?>
        <table class="form-table">
            <tr>
                <th><label for="jankx_membership_level"><?php _e('Membership Level', 'jankx'); ?></label></th>
                <td>
                    <select name="jankx_membership_level" id="jankx_membership_level">
                        <?php foreach ($levels as $slug => $level): ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($currentLevel, $slug); ?>>
                                <?php echo esc_html($level['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Select the membership level for this user.', 'jankx'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save level field from admin user profile
     */
    public function saveUserLevelField($userId): void
    {
        if (!isset($_POST['jankx_membership_level'])) {
            return;
        }

        if (!current_user_can('edit_user', $userId)) {
            return;
        }

        $level = sanitize_text_field($_POST['jankx_membership_level']);
        self::$levelManager->setUserLevel($userId, $level);
    }
}
