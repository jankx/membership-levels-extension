<?php
namespace Jankx\Extensions\MembershipLevels\Admin;

/**
 * Manages the SVG icon term meta for the membership_benefit taxonomy.
 *
 * Each benefit term can store an inline SVG string that is rendered inside
 * the membership level overview cards. Because every level has its own
 * colour setting, the SVG is injected as raw markup so CSS `color` /
 * `currentColor` can tint the icon automatically.
 *
 * Meta key : _benefit_svg_icon
 * Sanitise  : wp_kses() — only safe SVG tags & attributes are kept.
 * Escape    : wp_kses() on output (same allow-list).
 */
class BenefitTermMeta
{
    const META_KEY = '_benefit_svg_icon';
    const TAXONOMY = 'membership_benefit';

    /**
     * Allowed SVG tags / attributes passed to wp_kses().
     * Intentionally restrictive: presentation SVG only, no scripts.
     */
    private static function svgAllowedTags(): array
    {
        $common_attrs = [
            'class'            => true,
            'id'               => true,
            'style'            => true,
            'fill'             => true,
            'stroke'           => true,
            'stroke-width'     => true,
            'stroke-linecap'   => true,
            'stroke-linejoin'  => true,
            'opacity'          => true,
            'transform'        => true,
            'display'          => true,
            'visibility'       => true,
            'color'            => true,
            'aria-hidden'      => true,
            'role'             => true,
            'focusable'        => true,
            'tabindex'         => true,
        ];

        return [
            'svg'      => array_merge($common_attrs, [
                'xmlns'           => true,
                'viewBox'         => true,
                'width'           => true,
                'height'          => true,
                'preserveAspectRatio' => true,
                'xml:space'       => true,
                'version'         => true,
            ]),
            'path'     => array_merge($common_attrs, [
                'd'               => true,
                'clip-rule'       => true,
                'fill-rule'       => true,
            ]),
            'circle'   => array_merge($common_attrs, [
                'cx' => true, 'cy' => true, 'r' => true,
            ]),
            'ellipse'  => array_merge($common_attrs, [
                'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true,
            ]),
            'rect'     => array_merge($common_attrs, [
                'x' => true, 'y' => true,
                'width' => true, 'height' => true,
                'rx' => true, 'ry' => true,
            ]),
            'line'     => array_merge($common_attrs, [
                'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true,
            ]),
            'polyline' => array_merge($common_attrs, ['points' => true]),
            'polygon'  => array_merge($common_attrs, ['points' => true]),
            'g'        => $common_attrs,
            'defs'     => $common_attrs,
            'use'      => array_merge($common_attrs, [
                'href' => true, 'xlink:href' => true,
                'x' => true, 'y' => true, 'width' => true, 'height' => true,
            ]),
            'symbol'   => array_merge($common_attrs, [
                'viewBox' => true, 'width' => true, 'height' => true,
            ]),
            'title'    => ['id' => true],
            'desc'     => ['id' => true],
            'text'     => array_merge($common_attrs, [
                'x' => true, 'y' => true,
                'dx' => true, 'dy' => true,
                'text-anchor' => true, 'font-size' => true,
                'font-family' => true, 'font-weight' => true,
            ]),
            'tspan'    => array_merge($common_attrs, [
                'x' => true, 'y' => true,
                'dx' => true, 'dy' => true,
            ]),
            'clipPath' => array_merge($common_attrs, ['clipPathUnits' => true]),
            'mask'     => array_merge($common_attrs, [
                'maskUnits' => true, 'maskContentUnits' => true,
                'x' => true, 'y' => true, 'width' => true, 'height' => true,
            ]),
            'linearGradient' => array_merge($common_attrs, [
                'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true,
                'gradientUnits' => true, 'gradientTransform' => true,
                'spreadMethod' => true,
            ]),
            'radialGradient' => array_merge($common_attrs, [
                'cx' => true, 'cy' => true, 'r' => true,
                'fx' => true, 'fy' => true,
                'gradientUnits' => true, 'gradientTransform' => true,
                'spreadMethod' => true,
            ]),
            'stop' => array_merge($common_attrs, [
                'offset' => true, 'stop-color' => true, 'stop-opacity' => true,
            ]),
        ];
    }

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    public function register(): void
    {
        // Register the term meta so it is known to the REST API (future-proof).
        add_action('init', [$this, 'registerTermMeta']);

        // Admin UI — add/edit term forms.
        add_action(self::TAXONOMY . '_add_form_fields',  [$this, 'renderAddField']);
        add_action(self::TAXONOMY . '_edit_form_fields', [$this, 'renderEditField']);

        // Save on both create and update.
        add_action('created_' . self::TAXONOMY, [$this, 'saveMeta']);
        add_action('edited_'  . self::TAXONOMY, [$this, 'saveMeta']);

        // Enqueue live preview script on taxonomy admin screens.
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }

    public function registerTermMeta(): void
    {
        register_term_meta(self::TAXONOMY, self::META_KEY, [
            'type'              => 'string',
            'description'       => 'Inline SVG icon for the membership benefit.',
            'single'            => true,
            'sanitize_callback' => [$this, 'sanitizeSvg'],
            'show_in_rest'      => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // Admin UI
    // -------------------------------------------------------------------------

    /**
     * Renders the SVG icon field inside the "Add new benefit" form.
     */
    public function renderAddField(): void
    {
        wp_nonce_field('jankx_benefit_svg_icon', 'jankx_benefit_svg_nonce');
        ?>
        <div class="form-field term-svg-icon-wrap">
            <label for="benefit_svg_icon"><?php esc_html_e('Icon SVG', 'jankx'); ?></label>
            <textarea id="benefit_svg_icon" name="benefit_svg_icon"
                      rows="6" class="large-text code"
                      placeholder='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">...</svg>'></textarea>
            <p class="description">
                <?php esc_html_e('Dán chuỗi SVG inline. Nên dùng currentColor cho fill/stroke để icon tự đổi màu theo từng hạng thành viên.', 'jankx'); ?>
            </p>
            <?php $this->renderPreview(''); ?>
        </div>
        <?php
    }

    /**
     * Renders the SVG icon field inside the "Edit benefit" form.
     *
     * @param \WP_Term $term
     */
    public function renderEditField(\WP_Term $term): void
    {
        $svg = $this->getTermSvg($term->term_id);
        wp_nonce_field('jankx_benefit_svg_icon', 'jankx_benefit_svg_nonce');
        ?>
        <tr class="form-field term-svg-icon-wrap">
            <th scope="row">
                <label for="benefit_svg_icon"><?php esc_html_e('Icon SVG', 'jankx'); ?></label>
            </th>
            <td>
                <textarea id="benefit_svg_icon" name="benefit_svg_icon"
                          rows="8" class="large-text code"><?php echo esc_textarea($svg); ?></textarea>
                <p class="description">
                    <?php esc_html_e('Dán chuỗi SVG inline. Nên dùng currentColor cho fill/stroke để icon tự đổi màu theo từng hạng thành viên.', 'jankx'); ?>
                </p>
                <?php $this->renderPreview($svg); ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Renders a live preview box (populated by JS on input, or server-side on edit).
     */
    private function renderPreview(string $svg): void
    {
        ?>
        <div class="jankx-svg-preview" style="margin-top:8px;">
            <p style="margin:0 0 4px;font-weight:600;font-size:12px;color:#50575e;">
                <?php esc_html_e('Xem trước (currentColor = #65A30D):', 'jankx'); ?>
            </p>
            <div id="jankx-svg-preview-box"
                 style="display:inline-flex;align-items:center;justify-content:center;
                        width:48px;height:48px;border:1px solid #ddd;border-radius:6px;
                        background:#f6f7f7;color:#65A30D;padding:6px;box-sizing:border-box;">
                <?php echo wp_kses($svg, self::svgAllowedTags()); ?>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Save
    // -------------------------------------------------------------------------

    public function saveMeta(int $termId): void
    {
        if (!isset($_POST['jankx_benefit_svg_nonce']) ||
            !wp_verify_nonce($_POST['jankx_benefit_svg_nonce'], 'jankx_benefit_svg_icon')) {
            return;
        }

        if (!current_user_can('manage_categories')) {
            return;
        }

        $raw = wp_unslash($_POST['benefit_svg_icon'] ?? '');
        $svg = $this->sanitizeSvg($raw);

        update_term_meta($termId, self::META_KEY, $svg);
    }

    // -------------------------------------------------------------------------
    // Sanitise
    // -------------------------------------------------------------------------

    public function sanitizeSvg(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        return wp_kses($trimmed, self::svgAllowedTags());
    }

    // -------------------------------------------------------------------------
    // Public helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the sanitised SVG string for a benefit term, or '' if not set.
     */
    public static function getTermSvg(int $termId): string
    {
        $value = get_term_meta($termId, self::META_KEY, true);
        return is_string($value) ? $value : '';
    }

    /**
     * Renders (echoes) the SVG icon for a benefit term wrapped in a <span>.
     * Pass $color to override the CSS color so currentColor picks it up.
     *
     * @param int    $termId
     * @param string $color   Hex or CSS colour; defaults to empty (inherits).
     * @param array  $attrs   Extra HTML attributes for the wrapper <span>.
     */
    public static function renderTermIcon(int $termId, string $color = '', array $attrs = []): void
    {
        $svg = self::getTermSvg($termId);
        if ($svg === '') {
            return;
        }

        $style = $color ? sprintf('color:%s;', esc_attr($color)) : '';

        $attrStr = '';
        foreach ($attrs as $k => $v) {
            $attrStr .= sprintf(' %s="%s"', esc_attr($k), esc_attr($v));
        }

        printf(
            '<span class="benefit-icon"%s%s>%s</span>',
            $style ? sprintf(' style="%s"', $style) : '',
            $attrStr,
            wp_kses($svg, self::svgAllowedTags())
        );
    }

    // -------------------------------------------------------------------------
    // Scripts
    // -------------------------------------------------------------------------

    public function enqueueScripts(string $hook): void
    {
        // Only load on the taxonomy edit/add screens.
        if (!in_array($hook, ['edit-tags.php', 'term.php'], true)) {
            return;
        }

        $taxParam = $_GET['taxonomy'] ?? '';
        if ($taxParam !== self::TAXONOMY) {
            return;
        }

        // Inline JS: live preview as the user types.
        $js = <<<'JS'
(function () {
    var textarea = document.getElementById('benefit_svg_icon');
    var preview  = document.getElementById('jankx-svg-preview-box');
    if (!textarea || !preview) return;

    function update() {
        preview.innerHTML = textarea.value;
    }

    textarea.addEventListener('input', update);
    textarea.addEventListener('change', update);
}());
JS;
        wp_add_inline_script('jquery', $js);
    }
}
