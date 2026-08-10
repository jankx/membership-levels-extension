<?php
namespace Jankx\Extensions\MembershipLevels\Engine;

use Jankx\Extensions\MembershipLevels\MembershipLevelsExtension;

class RuleEngine
{
    /**
     * Check if a user meets a single criterion.
     */
    public function checkCriterion(int $userId, array $criterion, array $stats): bool
    {
        $type = $criterion['type'] ?? '';
        $min = floatval($criterion['min'] ?? 0);
        $max = floatval($criterion['max'] ?? 0);

        $value = $stats[$type] ?? 0;

        if ($value < $min) {
            return false;
        }

        if ($max > 0 && $value > $max) {
            return false;
        }

        return true;
    }

    /**
     * Evaluate a user's data against a level's criteria.
     * Supports both formats:
     *   - ['total_orders' => ['min' => 3]] (associative, from getLevels)
     *   - [['type' => 'total_orders', 'min' => 3]] (indexed, from CPT meta)
     */
    public function evaluateLevel(int $userId, array $levelCriteria, array $stats): bool
    {
        if (empty($levelCriteria)) {
            return true;
        }

        $normalized = $this->normalizeCriteria($levelCriteria);

        foreach ($normalized as $criterion) {
            if (!$this->checkCriterion($userId, $criterion, $stats)) {
                return false;
            }
        }

        return true;
    }

    protected function normalizeCriteria(array $criteria): array
    {
        $result = [];
        foreach ($criteria as $key => $value) {
            if (is_int($key) && is_array($value)) {
                // Already indexed format: [['type' => 'x', 'min' => 1], ...]
                $result[] = $value;
            } elseif (is_string($key) && is_array($value)) {
                // Associative format: ['total_orders' => ['min' => 3], ...]
                $result[] = array_merge(['type' => $key], $value);
            }
        }
        return $result;
    }

    /**
     * Find the highest level a user qualifies for.
     */
    public function findBestLevel(int $userId, array $levels, array $stats): ?string
    {
        $bestLevel = null;
        $bestPriority = -1;

        foreach ($levels as $slug => $level) {
            $criteria = $level['criteria'] ?? [];

            if (empty($criteria)) {
                continue;
            }

            if ($this->evaluateLevel($userId, $criteria, $stats)) {
                $priority = $level['priority'] ?? 0;
                if ($priority > $bestPriority) {
                    $bestPriority = $priority;
                    $bestLevel = $slug;
                }
            }
        }

        return $bestLevel;
    }

    /**
     * Gather user stats for rule evaluation.
     */
    public function getUserStats(int $userId): array
    {
        $stats = [
            'total_orders' => 0,
            'total_spent' => 0,
            'total_tours_booked' => 0,
            'account_age_days' => 0,
        ];

        $stats['total_orders'] = $this->getOrderCount($userId);
        $stats['total_spent'] = $this->getTotalSpent($userId);
        $stats['total_tours_booked'] = $this->getTourCount($userId);
        $stats['account_age_days'] = $this->getAccountAgeDays($userId);

        return $stats;
    }

    protected function getOrderCount(int $userId): int
    {
        $postTypes = $this->getOrderPostTypes();
        foreach ($postTypes as $pt) {
            if (post_type_exists($pt)) {
                return (int) wp_count_posts($pt)->publish;
            }
        }
        return 0;
    }

    protected function getTotalSpent(int $userId): float
    {
        global $wpdb;
        $postTypes = $this->getOrderPostTypes();

        foreach ($postTypes as $pt) {
            if (post_type_exists($pt)) {
                $total = $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(pm.meta_value)
                     FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     WHERE p.post_type = %s
                     AND p.post_status IN ('publish', 'completed', 'processing')
                     AND p.post_author = %d
                     AND pm.meta_key = '_booking_total'",
                    $pt,
                    $userId
                ));
                return (float) ($total ?? 0);
            }
        }
        return 0;
    }

    protected function getTourCount(int $userId): int
    {
        if (!post_type_exists('jankx_tour')) {
            return 0;
        }

        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE p.post_type = 'jankx_tour'
             AND p.post_status = 'publish'
             AND pm.meta_key = '_tour_booking_user'
             AND pm.meta_value = %d",
            $userId
        ));
    }

    protected function getAccountAgeDays(int $userId): int
    {
        $user = get_userdata($userId);
        if (!$user) {
            return 0;
        }

        $registered = strtotime($user->user_registered);
        $now = current_time('timestamp');

        return max(0, (int) (($now - $registered) / DAY_IN_SECONDS));
    }

    protected function getOrderPostTypes(): array
    {
        $types = [];
        foreach (['jankx_booking', 'jankx_order', 'booking', 'order'] as $pt) {
            if (post_type_exists($pt)) {
                $types[] = $pt;
            }
        }
        return $types;
    }
}
