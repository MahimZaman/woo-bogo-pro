<?php
defined('ABSPATH') || exit;

class WBOGO_Admin
{
    private static $instance = null;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_data_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'product_data_panel']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_meta']);
        add_action('wp_ajax_wbogo_product_search', [$this, 'ajax_product_search']);
    }

    public function enqueue_admin_assets($hook)
    {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }
        wp_enqueue_style('wbogo-admin-css', WBOGO_URL . 'assets/css/admin.css', [], '1.0');
        wp_enqueue_script('select2');
        wp_enqueue_script('wbogo-admin-js', WBOGO_URL . 'assets/js/admin.js', ['jquery', 'select2'], '1.0', true);
        wp_localize_script('wbogo-admin-js', 'wbogo_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'i18n_search_placeholder' => __('Search a product or variation', 'wbogo'),
        ]);
    }

    public function add_product_data_tab($tabs)
    {
        $tabs['wbogo_options'] = [
            'label'  => __('BOGO Options', 'wbogo'),
            'target' => 'wbogo_options_data',
            'class'  => ['show_if_simple', 'show_if_variable', 'show_if_grouped'],
        ];
        return $tabs;
    }

    public function product_data_panel()
    {
        global $post;

        $rules = get_post_meta($post->ID, '_wbogo_rules', true);
        if (! is_array($rules)) {
            $rules = [];
        }
?>
        <div id="wbogo_options_data" class="panel woocommerce_options_panel">
            <div class="wbogo-admin-panel">
                <button type="button" class="button wbogo-add-rule" id="wbogo_add_rule_btn"><?php esc_html_e('Add Rule', 'wbogo'); ?></button>
                <div id="wbogo_rules_container" class="wbogo-rules">
                    <?php foreach ($rules as $r_index => $rule) : ?>
                        <div class="wbogo-rule" data-rule-index="<?php echo esc_attr($r_index); ?>">
                            <div class="wbogo-row">
                                <label><?php esc_html_e('Buy Quantity (current product)', 'wbogo'); ?></label>
                                <input type="number" min="1" name="wbogo_rules[<?php echo esc_attr($r_index); ?>][buy_qty]" value="<?php echo esc_attr($rule['buy_qty'] ?? ''); ?>">
                                <button type="button" class="button wbogo-add-free"><?php esc_html_e('Add Free Product', 'wbogo'); ?></button>
                                <button type="button" class="button wbogo-remove-rule"><?php esc_html_e('Remove Rule', 'wbogo'); ?></button>
                            </div>

                            <div class="wbogo-free-list" data-index="0">
                                <?php
                                if (! empty($rule['free_products']) && is_array($rule['free_products'])) :
                                    foreach ($rule['free_products'] as $f_index => $free) :
                                        $selected_product = null;
                                        if (! empty($free['id'])) {
                                            $selected_product = wc_get_product(intval($free['id']));
                                        }
                                ?>
                                        <div class="wbogo-free-row">
                                            <select class="wbogo-product-select" name="wbogo_rules[<?php echo esc_attr($r_index); ?>][free_products][<?php echo esc_attr($f_index); ?>][id]">
                                                <?php if ($selected_product) : ?>
                                                    <option value="<?php echo esc_attr($selected_product->get_id()); ?>" selected>
                                                        <?php
                                                        // show variation title if variation
                                                        if ($selected_product->is_type('variation')) {
                                                            $parent = wc_get_product($selected_product->get_parent_id());
                                                            echo esc_html($parent ? $parent->get_name() : $selected_product->get_name());
                                                            echo ' – ' . wp_kses_post(wc_get_formatted_variation($selected_product, true));
                                                        } else {
                                                            echo esc_html($selected_product->get_name());
                                                        }
                                                        ?>
                                                    </option>
                                                <?php endif; ?>
                                            </select>
                                            <input type="number" min="1" name="wbogo_rules[<?php echo esc_attr($r_index); ?>][free_products][<?php echo esc_attr($f_index); ?>][qty]" value="<?php echo esc_attr($free['qty'] ?? 1); ?>" style="width:80px;">
                                            <a class="remove-free" href="#"><?php esc_html_e('Remove', 'wbogo'); ?></a>
                                        </div>
                                <?php
                                    endforeach;
                                endif;
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <script type="text/template" id="wbogo_rule_template">
                <div class="wbogo-rule" data-rule-index="__RULE_INDEX__">
					<div class="wbogo-row">
						<label><?php echo esc_html__('Buy Quantity (current product)', 'wbogo'); ?></label>
						<input type="number" min="1" name="wbogo_rules[__RULE_INDEX__][buy_qty]" value="1">
						<button type="button" class="button wbogo-add-free"><?php echo esc_html__('Add Free Product', 'wbogo'); ?></button>
						<button type="button" class="button wbogo-remove-rule"><?php echo esc_html__('Remove Rule', 'wbogo'); ?></button>
					</div>
					<div class="wbogo-free-list" data-index="0"></div>
				</div>
			</script>

            <script type="text/template" id="wbogo_free_template">
                <div class="wbogo-free-row">
					<select class="wbogo-product-select" name="wbogo_rules[__RULE_INDEX__][free_products][__INDEX__][id]"></select>
					<input type="number" min="1" name="wbogo_rules[__RULE_INDEX__][free_products][__INDEX__][qty]" value="1" style="width:80px;">
					<a class="remove-free" href="#"><?php echo esc_html__('Remove', 'wbogo'); ?></a>
				</div>
			</script>
        </div>
<?php
    }

    public function save_product_meta($post_id)
    {
        if (isset($_POST['wbogo_rules']) && is_array($_POST['wbogo_rules'])) {
            $raw = wp_unslash($_POST['wbogo_rules']);
            $data = [];

            foreach ($raw as $rule) {
                $buy_qty = isset($rule['buy_qty']) ? intval($rule['buy_qty']) : 0;
                if ($buy_qty <= 0) {
                    continue;
                }
                $free_products = [];
                if (! empty($rule['free_products']) && is_array($rule['free_products'])) {
                    foreach ($rule['free_products'] as $free) {
                        $pid = isset($free['id']) ? intval($free['id']) : 0;
                        $fq  = isset($free['qty']) ? intval($free['qty']) : 1;
                        if ($pid > 0 && $fq > 0) {
                            $free_products[] = [
                                'id'  => $pid,
                                'qty' => $fq,
                            ];
                        }
                    }
                }
                $data[] = [
                    'buy_qty'       => $buy_qty,
                    'free_products' => $free_products,
                ];
            }

            update_post_meta($post_id, '_wbogo_rules', $data);
        } else {
            delete_post_meta($post_id, '_wbogo_rules');
        }
    }

    public function ajax_product_search()
    {
        if (! current_user_can('edit_products')) {
            wp_send_json_error([], 403);
        }

        $q = isset($_GET['q']) ? wc_clean(wp_unslash($_GET['q'])) : '';

        // include simple + variation (and optionally variable)
        $args = [
            'limit'   => 30,
            'status'  => 'publish',
            's'       => $q,
            'return'  => 'objects',
            'type'    => ['simple', 'variation', 'variable'],
        ];

        $products = wc_get_products($args);
        $results  = [];

        foreach ($products as $product) {
            // variation
            if ($product->is_type('variation')) {
                $parent = wc_get_product($product->get_parent_id());
                $label  = $parent ? $parent->get_name() . ' – ' . wc_get_formatted_variation($product, true) : $product->get_name();
            } else {
                $label = $product->get_name();
            }

            $results[] = [
                'id'   => $product->get_id(),
                'text' => wp_kses_post($label),
            ];
        }

        wp_send_json($results);
    }
}
