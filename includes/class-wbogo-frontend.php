<?php
defined('ABSPATH') || exit;

class WBOGO_Frontend
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
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('woocommerce_after_add_to_cart_button', [$this, 'render_product_offers']);
    }

    public function enqueue_frontend_assets()
    {
        wp_enqueue_style('wbogo-frontend-css', WBOGO_URL . 'assets/css/frontend.css', [], '1.0');
        wp_enqueue_script('wbogo-frontend-js', WBOGO_URL . 'assets/js/frontend.js', ['jquery'], '1.0', true);
        wp_localize_script('wbogo-frontend-js', 'wbogo_front', [
            'i18n_free' => __('Free', 'wbogo'),
        ]);
    }

    public function render_product_offers()
    {
        global $product;
        if (! $product instanceof WC_Product) {
            return;
        }
        $rules = get_post_meta($product->get_id(), '_wbogo_rules', true);
        if (empty($rules) || ! is_array($rules)) {
            return;
        }
?>
        <div class="wbogo-frontend" aria-hidden="false">
            <h4><?php esc_html_e('BOGO Offers', 'wbogo'); ?></h4>
            <?php foreach ($rules as $rule) : ?>
                <?php if (empty($rule['free_products']) || ! is_array($rule['free_products'])) continue; ?>
                <div class="wbogo-offer">
                    <div class="wbogo-offer-head">
                        <?php printf(esc_html__('Buy %d of this product and get:', 'wbogo'), intval($rule['buy_qty'])); ?>
                    </div>

                    <div class="wbogo-free-items">
                        <?php foreach ($rule['free_products'] as $free) :
                            $fp = wc_get_product(intval($free['id']));
                            if (! $fp) continue;
                            $regular = $fp->get_regular_price();
                        ?>
                            <div class="wbogo-free-item">
                                <div class="wbogo-free-left">
                                    <?php echo wp_kses_post($fp->get_image()); ?>
                                    <div class="wbogo-free-title"><?php echo esc_html($fp->get_name()); ?></div>
                                </div>
                                <div class="wbogo-free-right">
                                    <?php if ($regular !== '') : ?>
                                        <span class="wbogo-regular"><?php echo wp_kses_post(wc_price($regular)); ?></span>
                                    <?php endif; ?>
                                    <span class="wbogo-free-badge"><?php esc_html_e('Free', 'wbogo'); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
<?php
    }
}
