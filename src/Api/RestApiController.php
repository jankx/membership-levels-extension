<?php
namespace Jankx\Extensions\MembershipLevels\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class RestApiController
{
    const NAMESPACE = 'jankx/v1';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        // Get user level
        register_rest_route(self::NAMESPACE, '/memberships/levels/user/(?P<user_id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getUserLevel'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
        ]);

        // Set user level (manual assign)
        register_rest_route(self::NAMESPACE, '/memberships/levels/user/(?P<user_id>\d+)', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'setUserLevel'],
                'permission_callback' => [$this, 'checkAdminPermission'],
                'args'                => [
                    'level' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        // Evaluate and set level for a user
        register_rest_route(self::NAMESPACE, '/memberships/levels/user/(?P<user_id>\d+)/evaluate', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'evaluateUser'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
        ]);

        // Get all levels
        register_rest_route(self::NAMESPACE, '/memberships/levels', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getLevels'],
                'permission_callback' => '__return_true',
            ],
        ]);

        // Get level by slug
        register_rest_route(self::NAMESPACE, '/memberships/levels/(?P<slug>[a-z0-9_-]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getLevelBySlug'],
                'permission_callback' => '__return_true',
            ],
        ]);

        // Get level statistics
        register_rest_route(self::NAMESPACE, '/memberships/levels/stats', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getLevelStats'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
        ]);
    }

    public function getUserLevel(WP_REST_Request $request): WP_REST_Response
    {
        $userId = intval($request->get_param('user_id'));
        $user = get_userdata($userId);

        if (!$user) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Không tìm thấy user.',
            ], 404);
        }

        $level = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::getUserLevel($userId);
        $levels = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::getLevels();
        $levelData = $levels[$level] ?? null;

        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'user_id' => $userId,
                'level'   => $level,
                'level_info' => $levelData,
            ],
        ]);
    }

    public function setUserLevel(WP_REST_Request $request): WP_REST_Response
    {
        $userId = intval($request->get_param('user_id'));
        $level = sanitize_text_field($request->get_param('level'));

        $user = get_userdata($userId);
        if (!$user) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Không tìm thấy user.',
            ], 404);
        }

        $levels = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::getLevels();
        if (!isset($levels[$level])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Hạng không hợp lệ.',
            ], 400);
        }

        $ext = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::get_instance();
        $ext->setUserLevel($userId, $level);

        // Log manual assignment
        update_user_meta($userId, '_level_last_manual_change', current_time('mysql'));
        update_user_meta($userId, '_level_changed_by', get_current_user_id());

        do_action('jankx_user_level_changed', $userId, $level, 'manual');

        return new WP_REST_Response([
            'success' => true,
            'message' => sprintf('Đã gán hạng %s cho user #%d.', $levels[$level]['name'], $userId),
            'data'    => [
                'user_id' => $userId,
                'level'   => $level,
            ],
        ]);
    }

    public function evaluateUser(WP_REST_Request $request): WP_REST_Response
    {
        $userId = intval($request->get_param('user_id'));
        $user = get_userdata($userId);

        if (!$user) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Không tìm thấy user.',
            ], 404);
        }

        $ext = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::get_instance();
        $result = $ext->evaluateAndSetLevel($userId);
        $newLevel = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::getUserLevel($userId);
        $levels = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::getLevels();

        return new WP_REST_Response([
            'success' => true,
            'message' => $result
                ? sprintf('Đã cập nhật hạng thành viên lên %s.', $levels[$newLevel]['name'])
                : 'Hạng hiện tại vẫn giữ nguyên.',
            'data'    => [
                'user_id'  => $userId,
                'level'    => $newLevel,
                'changed'  => $result,
            ],
        ]);
    }

    public function getLevels(WP_REST_Request $request): WP_REST_Response
    {
        $levels = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::getLevels();

        $data = [];
        foreach ($levels as $slug => $level) {
            $data[] = array_merge($level, ['slug' => $slug]);
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function getLevelBySlug(WP_REST_Request $request): WP_REST_Response
    {
        $slug = sanitize_text_field($request->get_param('slug'));
        $levels = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::getLevels();

        if (!isset($levels[$slug])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Không tìm thấy hạng.',
            ], 404);
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => array_merge($levels[$slug], ['slug' => $slug]),
        ]);
    }

    public function getLevelStats(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $meta = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::USER_LEVEL_META;
        $levels = \Jankx\Extensions\MembershipLevels\MembershipLevelsExtension::getLevels();

        $results = $wpdb->get_results(
            "SELECT meta_value as level, COUNT(*) as count
             FROM {$wpdb->usermeta}
             WHERE meta_key = '{$meta}'
             GROUP BY meta_value",
            ARRAY_A
        );

        $stats = [];
        foreach ($results as $row) {
            $stats[$row['level']] = (int) $row['count'];
        }

        // Ensure all levels appear
        foreach ($levels as $slug => $level) {
            $stats[$slug] = $stats[$slug] ?? 0;
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => $stats,
        ]);
    }

    public function checkAdminPermission(): bool
    {
        return current_user_can('manage_options');
    }
}
