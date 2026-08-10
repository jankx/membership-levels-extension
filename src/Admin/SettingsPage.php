<?php
namespace Jankx\Extensions\MembershipLevels\Admin;

class SettingsPage
{
    const OPTION_GROUP = 'jankx_membership_settings';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPages']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenuPages(): void
    {
        // Main membership page (lists all users with their levels)
        add_submenu_page(
            'edit.php?post_type=membership_level',
            __('Quản lý thành viên', 'jankx'),
            __('Quản lý thành viên', 'jankx'),
            'manage_options',
            'jankx-membership-users',
            [$this, 'renderUsersPage']
        );

        // Settings page
        add_submenu_page(
            'edit.php?post_type=membership_level',
            __('Cài đặt hạng', 'jankx'),
            __('Cài đặt', 'jankx'),
            'manage_options',
            'jankx-membership-settings',
            [$this, 'renderSettingsPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting(self::OPTION_GROUP, 'jankx_membership_auto_assign', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true,
        ]);

        register_setting(self::OPTION_GROUP, 'jankx_membership_default_level', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'bronze',
        ]);

        register_setting(self::OPTION_GROUP, 'jankx_membership_evaluate_on', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitizeEvaluateOn'],
            'default' => ['checkout_completed', 'payment_paid'],
        ]);
    }

    public function sanitizeEvaluateOn($value): array
    {
        $allowed = ['checkout_completed', 'payment_paid', 'manual'];
        return array_filter(array_intersect((array) $value, $allowed));
    }

    /**
     * Render settings page
     */
    public function renderSettingsPage(): void
    {
        $autoAssign = get_option('jankx_membership_auto_assign', true);
        $defaultLevel = get_option('jankx_membership_default_level', 'bronze');
        $evaluateOn = get_option('jankx_membership_evaluate_on', ['checkout_completed', 'payment_paid']);
        $levels = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::getLevels();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Cài đặt hệ thống hạng thành viên', 'jankx'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Tự động phân hạng', 'jankx'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="jankx_membership_auto_assign" value="1" <?php checked($autoAssign, true); ?>>
                                <?php esc_html_e('Bật tự động đánh giá và phân hạng khi có đơn hàng mới', 'jankx'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Hạng mặc định', 'jankx'); ?></th>
                        <td>
                            <select name="jankx_membership_default_level">
                                <?php foreach ($levels as $slug => $level): ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($defaultLevel, $slug); ?>>
                                        <?php echo esc_html($level['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Hạng được gán khi user đăng ký tài khoản mới.', 'jankx'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Kích hoạt đánh giá', 'jankx'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="jankx_membership_evaluate_on[]" value="checkout_completed"
                                           <?php checked(in_array('checkout_completed', $evaluateOn)); ?>>
                                    <?php esc_html_e('Sau khi checkout xong', 'jankx'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="jankx_membership_evaluate_on[]" value="payment_paid"
                                           <?php checked(in_array('payment_paid', $evaluateOn)); ?>>
                                    <?php esc_html_e('Sau khi thanh toán thành công', 'jankx'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="jankx_membership_evaluate_on[]" value="manual"
                                           <?php checked(in_array('manual', $evaluateOn)); ?>>
                                    <?php esc_html_e('Cho phép thủ công trên profile user', 'jankx'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Thống kê hạng thành viên', 'jankx'); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Hạng', 'jankx'); ?></th>
                        <th><?php esc_html_e('Số lượng thành viên', 'jankx'); ?></th>
                        <th><?php esc_html_e('Tiêu chí', 'jankx'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($levels as $slug => $level): ?>
                        <tr>
                            <td>
                                <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:<?php echo esc_attr($level['color']); ?>;vertical-align:middle;margin-right:6px;"></span>
                                <strong><?php echo esc_html($level['name']); ?></strong>
                            </td>
                            <td><?php echo $this->countUsersByLevel($slug); ?></td>
                            <td><?php echo esc_html($this->formatCriteria($level['criteria'] ?? [])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render users management page
     */
    public function renderUsersPage(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'usermeta';
        $levelMeta = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::USER_LEVEL_META;
        $levels = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::getLevels();

        // Handle manual level assignment
        if (isset($_POST['jankx_bulk_assign']) && wp_verify_nonce($_POST['_wpnonce'], 'jankx_bulk_assign_level')) {
            $userId = intval($_POST['user_id'] ?? 0);
            $level = sanitize_text_field($_POST['new_level'] ?? '');
            if ($userId && $level && isset($levels[$level])) {
                $ext = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::get_instance();
                $ext->setUserLevel($userId, $level);
                echo '<div class="notice notice-success"><p>Đã cập nhật hạng thành viên.</p></div>';
            }
        }

        // Handle auto-evaluate all
        if (isset($_POST['jankx_evaluate_all']) && wp_verify_nonce($_POST['_wpnonce'], 'jankx_evaluate_all_users')) {
            $ext = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::get_instance();
            $users = get_users(['fields' => ['ID'], 'number' => 100]);
            $count = 0;
            foreach ($users as $user) {
                $ext->evaluateAndSetLevel($user->ID);
                $count++;
            }
            echo '<div class="notice notice-success"><p>Đã đánh giá lại hạng cho ' . $count . ' thành viên.</p></div>';
        }

        // List users with levels
        $users = get_users(['number' => 50, 'orderby' => 'registered', 'order' => 'DESC']);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Quản lý hạng thành viên', 'jankx'); ?></h1>

            <form method="post" style="margin-bottom:20px;">
                <?php wp_nonce_field('jankx_evaluate_all_users'); ?>
                <button type="submit" name="jankx_evaluate_all" value="1" class="button button-secondary">
                    <?php esc_html_e('Đánh giá lại tất cả', 'jankx'); ?>
                </button>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Thành viên', 'jankx'); ?></th>
                        <th><?php esc_html_e('Email', 'jankx'); ?></th>
                        <th><?php esc_html_e('Hạng hiện tại', 'jankx'); ?></th>
                        <th><?php esc_html_e('Đơn hàng', 'jankx'); ?></th>
                        <th><?php esc_html_e('Tổng chi tiêu', 'jankx'); ?></th>
                        <th><?php esc_html_e('Thao tác', 'jankx'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $userLevel = get_user_meta($user->ID, $levelMeta, true) ?: 'bronze';
                        $orderCount = $this->getUserOrderCount($user->ID);
                        $totalSpent = $this->getUserTotalSpent($user->ID);
                        $levelData = $levels[$userLevel] ?? $levels['bronze'];
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($user->display_name); ?></strong><br>
                                <small>ID: <?php echo $user->ID; ?></small>
                            </td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td>
                                <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:<?php echo esc_attr($levelData['color']); ?>;vertical-align:middle;margin-right:4px;"></span>
                                <?php echo esc_html($levelData['name']); ?>
                            </td>
                            <td><?php echo $orderCount; ?></td>
                            <td><?php echo number_format($totalSpent, 0, ',', '.'); ?>đ</td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('jankx_bulk_assign_level'); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                    <select name="new_level" style="width:120px;">
                                        <?php foreach ($levels as $slug => $lvl): ?>
                                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($userLevel, $slug); ?>>
                                                <?php echo esc_html($lvl['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="jankx_bulk_assign" value="1" class="button button-small">Lưu</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    protected function countUsersByLevel(string $level): int
    {
        global $wpdb;
        $meta = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::USER_LEVEL_META;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
            $meta,
            $level
        ));
    }

    protected function formatCriteria(array $criteria): string
    {
        if (empty($criteria)) {
            return '—';
        }

        $allCriteria = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::getCriteria();
        $parts = [];
        foreach ($criteria as $key => $c) {
            // Handle associative format: ['total_orders' => ['min' => 3]]
            if (is_string($key)) {
                $type = $key;
                $min = $c['min'] ?? 0;
                $max = $c['max'] ?? 0;
            } else {
                // Handle indexed format: [['type' => 'total_orders', 'min' => 3]]
                $type = $c['type'] ?? '';
                $min = $c['min'] ?? 0;
                $max = $c['max'] ?? 0;
            }

            $label = $allCriteria[$type]['label'] ?? $type;
            $minFormatted = number_format($min, 0, ',', '.');
            if (!empty($max) && $max > 0) {
                $maxFormatted = number_format($max, 0, ',', '.');
                $parts[] = "{$label}: {$minFormatted} - {$maxFormatted}";
            } else {
                $parts[] = "{$label}: >= {$minFormatted}";
            }
        }
        return implode(', ', $parts);
    }

    protected function getUserOrderCount(int $userId): int
    {
        $postTypes = ['jankx_booking', 'booking', 'jankx_order'];
        foreach ($postTypes as $pt) {
            if (post_type_exists($pt)) {
                return count(get_posts([
                    'post_type' => $pt,
                    'post_status' => ['publish', 'completed'],
                    'meta_query' => [
                        ['key' => '_customer_id', 'value' => $userId],
                    ],
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                ]));
            }
        }
        return 0;
    }

    protected function getUserTotalSpent(int $userId): float
    {
        $postTypes = ['jankx_booking', 'booking', 'jankx_order'];
        global $wpdb;
        foreach ($postTypes as $pt) {
            if (post_type_exists($pt)) {
                $total = $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(meta_value) FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     WHERE p.post_type = %s
                     AND p.post_status IN ('publish','completed')
                     AND pm.meta_key = '_booking_total'
                     AND pm.post_id IN (
                         SELECT post_id FROM {$wpdb->postmeta}
                         WHERE meta_key = '_customer_id' AND meta_value = %d
                     )",
                    $pt,
                    $userId
                ));
                return (float) $total;
            }
        }
        return 0;
    }
}
