<?php
defined('ABSPATH') || exit;

class WBOGO_Cart
{
    private static $instance = null;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Guard to prevent re-entrancy/loops */
    private $processing = false;

    public function __construct()
    {
        add_action('woocommerce_before_calculate_totals', [$this, 'sync_free_items'], 20);
        add_action('woocommerce_cart_loaded_from_session', [$this, 'force_zero_price_session'], 20);
        add_filter('woocommerce_cart_item_price', [$this, 'display_price_for_free_items'], 10, 3);
        add_filter('woocommerce_cart_item_quantity', [$this, 'cart_quantity_display'], 10, 3);
        add_filter('woocommerce_cart_item_remove_link', [$this, 'remove_link_for_free'], 10, 2);
        add_filter('woocommerce_get_item_data', [$this, 'show_item_data'], 10, 2);
    }

    public function force_zero_price_session($cart)
    {
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (! empty($cart_item['wbogo_free'])) {
                $product = $cart_item['data'];
                if ($product instanceof WC_Product) {
                    $product->set_price(0);
                }
            }
        }
    }

    public function sync_free_items($cart)
    {
        // guard: avoid recursion
        if ($this->processing) {
            return;
        }
        $this->processing = true;

        // always operate on WC()->cart
        $cart_obj = $cart;
        if (! $cart_obj) {
            $this->processing = false;
            return;
        }

        // Build expected freebies keyed as parent_rule_free
        $expected = [];

        foreach ($cart_obj->get_cart() as $cart_item_key => $cart_item) {
            // skip freebies when deciding expected items
            if (! empty($cart_item['wbogo_free'])) {
                continue;
            }

            $pid = isset($cart_item['product_id']) ? intval($cart_item['product_id']) : 0;
            $qty = isset($cart_item['quantity']) ? intval($cart_item['quantity']) : 0;
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }

            $rules = get_post_meta($pid, '_wbogo_rules', true);
            if (empty($rules) || ! is_array($rules)) {
                continue;
            }

            foreach ($rules as $r_index => $rule) {
                $buy_qty = intval($rule['buy_qty']);
                if ($buy_qty <= 0) {
                    continue;
                }
                $multiplier = intval(floor($qty / $buy_qty));
                if ($multiplier <= 0) {
                    continue;
                }
                if (empty($rule['free_products']) || ! is_array($rule['free_products'])) {
                    continue;
                }
                foreach ($rule['free_products'] as $free) {
                    $free_id  = intval($free['id']);
                    $free_qty = max(0, intval($free['qty'])) * $multiplier;
                    if ($free_id <= 0 || $free_qty <= 0) {
                        continue;
                    }
                    // prevent circular: don't add if free product equals parent
                    if ($free_id === $pid) {
                        continue;
                    }
                    $key = $pid . '_' . $r_index . '_' . $free_id;
                    $expected[$key] = [
                        'parent'     => $pid,
                        'rule'       => $r_index,
                        'product_id' => $free_id,
                        'qty'        => $free_qty,
                    ];
                }
            }
        }

        // Find existing freebies
        $existing = [];
        foreach ($cart_obj->get_cart() as $cart_item_key => $cart_item) {
            if (! empty($cart_item['wbogo_free']) && ! empty($cart_item['wbogo_parent'])) {
                $key = $cart_item['wbogo_parent'] . '_' . $cart_item['wbogo_rule'] . '_' . $cart_item['product_id'];
                $existing[$key] = [
                    'cart_key' => $cart_item_key,
                    'qty'      => $cart_item['quantity'],
                ];
            }
        }

        // Add or update expected freebies.
        // Collect additions in an array first, then add after loop to reduce side-effects.
        $to_add = [];
        foreach ($expected as $k => $exp) {
            if (isset($existing[$k])) {
                // update quantity if mismatch
                if ($existing[$k]['qty'] !== $exp['qty']) {
                    $cart_obj->set_quantity($existing[$k]['cart_key'], $exp['qty'], true);
                }
            } else {
                $to_add[$k] = $exp;
            }
        }

        // Add new free items (outside primary iteration)
        if (! empty($to_add)) {
            foreach ($to_add as $k => $exp) {
                $cart_item_data = [
                    'wbogo_free'   => true,
                    'wbogo_parent' => $exp['parent'],
                    'wbogo_rule'   => $exp['rule'],
                    '_wbogo_key'   => wp_generate_uuid4(),
                ];

                // Use add_to_cart but ensure we're not in an infinite loop.
                // add_to_cart may alter cart contents; that's why the guard exists.
                $added = $cart_obj->add_to_cart($exp['product_id'], $exp['qty'], 0, [], $cart_item_data);

                // If add_to_cart returned false, skip.
                if (! $added) {
                    // continue - nothing to do
                }
            }
        }

        // Remove freebies that are no longer expected
        foreach ($existing as $k => $ex) {
            if (! isset($expected[$k])) {
                $cart_obj->remove_cart_item($ex['cart_key']);
            }
        }

        // Ensure free items are priced at zero
        foreach ($cart_obj->get_cart() as $key => $ci) {
            if (! empty($ci['wbogo_free'])) {
                if (isset($ci['data']) && $ci['data'] instanceof WC_Product) {
                    $ci['data']->set_price(0);
                    // ensure cart item references updated product object
                    $cart_obj->cart_contents[$key]['data'] = $ci['data'];
                }
            }
        }

        $this->processing = false;
    }

    public function display_price_for_free_items($price_html, $cart_item, $cart_item_key)
    {
        if (! empty($cart_item['wbogo_free'])) {
            $p = wc_get_product($cart_item['product_id']);
            if ($p) {
                $regular = $p->get_regular_price();
                $regular_html = '';
                if ($regular !== '') {
                    $regular_html = '<span class="wbogo-regular">' . wp_kses_post(wc_price($regular)) . '</span> ';
                }
                $free_label = '<span class="wbogo-free">' . esc_html__('Free', 'wbogo') . '</span>';
                return $regular_html . $free_label;
            }
        }
        return $price_html;
    }

    public function cart_quantity_display($product_quantity, $cart_item_key, $cart_item)
    {
        if (! empty($cart_item['wbogo_free'])) {
            $qty = intval($cart_item['quantity']);
            $hidden = '<input type="hidden" name="cart[' . esc_attr($cart_item_key) . '][qty]" value="' . esc_attr($qty) . '">';
            return $hidden . esc_html($qty);
        }
        return $product_quantity;
    }

    public function remove_link_for_free($link, $cart_item_key)
    {
        $cart = WC()->cart->get_cart();
        if (isset($cart[$cart_item_key]) && ! empty($cart[$cart_item_key]['wbogo_free'])) {
            return '';
        }
        return $link;
    }

    public function show_item_data($item_data, $cart_item)
    {
        if (! empty($cart_item['wbogo_free'])) {
            $item_data[] = [
                'key'   => __('BOGO', 'wbogo'),
                'value' => __('Free item', 'wbogo'),
            ];
        }
        return $item_data;
    }
}
