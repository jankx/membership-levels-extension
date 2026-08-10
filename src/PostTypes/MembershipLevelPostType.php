<?php
namespace Jankx\Extensions\MembershipLevels\PostTypes;

class MembershipLevelPostType
{
    const POST_TYPE = 'membership_level';

    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'saveMetaBox']);
    }

    public function register_post_type(): void
    {
        if (post_type_exists(self::POST_TYPE)) {
            return;
        }

        $labels = [
            'name'               => __('Hạng thành viên', 'jankx'),
            'singular_name'      => __('Hạng thành viên', 'jankx'),
            'menu_name'          => __('Hạng thành viên', 'jankx'),
            'add_new'            => __('Thêm hạng mới', 'jankx'),
            'add_new_item'       => __('Thêm hạng thành viên mới', 'jankx'),
            'edit_item'          => __('Sửa hạng thành viên', 'jankx'),
            'new_item'           => __('Hạng thành viên mới', 'jankx'),
            'view_item'          => __('Xem hạng thành viên', 'jankx'),
            'search_items'       => __('Tìm hạng thành viên', 'jankx'),
            'not_found'          => __('Không tìm thấy hạng thành viên', 'jankx'),
            'all_items'          => __('Tất cả hạng thành viên', 'jankx'),
        ];

        register_post_type(self::POST_TYPE, [
            'labels'       => $labels,
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => true,
            'menu_icon'    => 'dashicons-admin-users',
            'menu_position' => 25,
            'show_in_rest' => true,
            'supports'     => ['title', 'editor', 'thumbnail'],
            'capability_type' => 'post',
            'map_meta_cap'    => false,
            'capabilities'    => [
                'create_posts' => 'do_not_allow',
            ],
        ]);
    }

    public function addMetaBoxes(): void
    {
        add_meta_box(
            'jankx_level_settings',
            __('Cài đặt hạng', 'jankx'),
            [$this, 'renderMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'jankx_level_criteria',
            __('Tiêu chí đạt hạng', 'jankx'),
            [$this, 'renderCriteriaMetaBox'],
            self::POST_TYPE,
            'normal',
            'default'
        );
    }

    public function renderMetaBox($post): void
    {
        wp_nonce_field('jankx_level_settings', 'jankx_level_settings_nonce');

        $slug = get_post_meta($post->ID, '_level_slug', true);
        $color = get_post_meta($post->ID, '_level_color', true) ?: '#65A30D';
        $priority = get_post_meta($post->ID, '_level_priority', true) ?: 0;
        $discount = get_post_meta($post->ID, '_level_discount', true) ?: 0;
        ?>
        <table class="form-table">
            <tr>
                <th><label for="level_slug">Slug (mã định danh)</label></th>
                <td>
                    <input type="text" id="level_slug" name="level_slug" value="<?php echo esc_attr($slug); ?>"
                           class="regular-text" required pattern="[a-z0-9_-]+" placeholder="e.g. silver">
                    <p class="description">Chỉ viết thường, số, gạch dưới. Dùng để identify trong hệ thống.</p>
                </td>
            </tr>
            <tr>
                <th><label for="level_color">Màu sắc</label></th>
                <td>
                    <input type="color" id="level_color" name="level_color" value="<?php echo esc_attr($color); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="level_priority">Ưu tiên (thứ tự xếp hạng)</label></th>
                <td>
                    <input type="number" id="level_priority" name="level_priority" value="<?php echo esc_attr($priority); ?>"
                           min="0" step="1">
                    <p class="description">Cao hơn = hạng cao hơn. Bronze=0, Silver=10, Gold=20...</p>
                </td>
            </tr>
            <tr>
                <th><label for="level_discount">Giảm giá (%)</label></th>
                <td>
                    <input type="number" id="level_discount" name="level_discount" value="<?php echo esc_attr($discount); ?>"
                           min="0" max="100" step="0.1">
                    <p class="description">Phần trăm giảm giá cho hạng này.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function renderCriteriaMetaBox($post): void
    {
        wp_nonce_field('jankx_level_criteria', 'jankx_level_criteria_nonce');

        $criteria = get_post_meta($post->ID, '_level_criteria', true) ?: [];
        $allCriteria = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::getCriteria();
        ?>
        <table class="widefat" id="level-criteria-table">
            <thead>
                <tr>
                    <th>Tiêu chí</th>
                    <th>Giá trị tối thiểu</th>
                    <th>Giá trị tối đa (0 = không giới hạn)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($criteria)): ?>
                    <?php foreach ($criteria as $i => $c): ?>
                        <tr class="criteria-row">
                            <td>
                                <select name="criteria[<?php echo $i; ?>][type]" class="regular-text">
                                    <?php foreach ($allCriteria as $key => $def): ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($c['type'] ?? '', $key); ?>>
                                            <?php echo esc_html($def['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="criteria[<?php echo $i; ?>][min]" value="<?php echo esc_attr($c['min'] ?? ''); ?>" min="0" class="regular-text"></td>
                            <td><input type="number" name="criteria[<?php echo $i; ?>][max]" value="<?php echo esc_attr($c['max'] ?? 0); ?>" min="0" class="regular-text"></td>
                            <td><button type="button" class="button button-link-delete jankx-remove-criterion">Xóa</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <button type="button" class="button" id="jankx-add-criterion">+ Thêm tiêu chí</button>

        <script>
        document.getElementById('jankx-add-criterion').addEventListener('click', function() {
            var tbody = document.querySelector('#level-criteria-table tbody');
            var index = tbody.querySelectorAll('tr').length;
            var row = document.createElement('tr');
            row.className = 'criteria-row';
            row.innerHTML = '<td><select name="criteria[' + index + '][type]" class="regular-text">';
            <?php foreach ($allCriteria as $key => $def): ?>
                row.querySelector('select').innerHTML += '<option value="<?php echo esc_js($key); ?>"><?php echo esc_js($def["label"]); ?></option>';
            <?php endforeach; ?>
            row.innerHTML += '</select></td>'
                + '<td><input type="number" name="criteria[' + index + '][min]" value="" min="0" class="regular-text"></td>'
                + '<td><input type="number" name="criteria[' + index + '][max]" value="0" min="0" class="regular-text"></td>'
                + '<td><button type="button" class="button button-link-delete jankx-remove-criterion">Xóa</button></td>';
            tbody.appendChild(row);
        });

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('jankx-remove-criterion')) {
                e.target.closest('tr').remove();
            }
        });
        </script>
        <?php
    }

    public function saveMetaBox(int $postId): void
    {
        if (!isset($_POST['jankx_level_settings_nonce']) ||
            !wp_verify_nonce($_POST['jankx_level_settings_nonce'], 'jankx_level_settings')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $slug = sanitize_text_field($_POST['level_slug'] ?? '');
        $color = sanitize_hex_color($_POST['level_color'] ?? '#65A30D');
        $priority = intval($_POST['level_priority'] ?? 0);
        $discount = floatval($_POST['level_discount'] ?? 0);

        update_post_meta($postId, '_level_slug', $slug);
        update_post_meta($postId, '_level_color', $color);
        update_post_meta($postId, '_level_priority', $priority);
        update_post_meta($postId, '_level_discount', $discount);

        // Save criteria
        if (isset($_POST['jankx_level_criteria_nonce']) &&
            wp_verify_nonce($_POST['jankx_level_criteria_nonce'], 'jankx_level_criteria')) {
            $criteria = [];
            $raw = $_POST['criteria'] ?? [];
            if (is_array($raw)) {
                foreach ($raw as $row) {
                    $type = sanitize_text_field($row['type'] ?? '');
                    if ($type) {
                        $criteria[] = [
                            'type' => $type,
                            'min' => floatval($row['min'] ?? 0),
                            'max' => floatval($row['max'] ?? 0),
                        ];
                    }
                }
            }
            update_post_meta($postId, '_level_criteria', $criteria);
        }
    }
}
