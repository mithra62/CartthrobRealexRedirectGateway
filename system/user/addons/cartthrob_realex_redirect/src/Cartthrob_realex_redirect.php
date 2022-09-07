<?php

use CartThrob\PaymentGateways\AbstractPaymentGateway;

class Cartthrob_realex_redirect extends AbstractPaymentGateway
{
    public $title = 'realex_redirect_title';
    public $overview = "realex_redirect_overview";
    public $settings = [
        [
            'name' => 'mode',
            'short_name' => 'mode',
            'type' => 'select',
            'default' => 'test',
            'options' => [
                'test' => 'realex_mode_test',
                'live' => 'realex_mode_live',
            ],
        ],
        [
            'name' => 'realex_redirect_settings_merchant_id',
            'short_name' => 'your_merchant_id',
            'type' => 'text'
        ],
        [
            'name' => 'realex_redirect_settings_your_secret',
            'short_name' => 'your_secret',
            'type' => 'text'
        ],
        [
            'name' => 'realex_redirect_success_template',
            'short_name' => 'success_template',
            'type' => 'text',
            'note' => 'realex_backup_template_note',
            'attributes' => [
                'class' => 'templates',
            ],
        ],
        [
            'name' => 'realex_redirect_failure_template',
            'short_name' => 'failure_template',
            'type' => 'text',
            'note' => 'realex_backup_template_note',
            'attributes' => [
                'class' => 'templates',
            ],
        ],
    ];

    public $required_fields = [];

    public $fields = [
        'first_name',
        'last_name',
        'address',
        'address2',
        'city',
        'zip',
        'country_code',
        'shipping_first_name',
        'shipping_last_name',
        'shipping_address',
        'shipping_address2',
        'shipping_city',
        'shipping_zip',
        'shipping_country_code',
        'phone',
        'email_address',
    ];

    public function initialize()
    {
        $this->overview = $this->lang('realex_redirect_overview') .
            "<pre>" . $this->response_script(ucfirst(get_class($this))) . "</pre>";

    }

    /**
     * process_payment
     *
     * @param string $credit_card_number
     * @return mixed | array | bool An array of error / success messages  is returned, or FALSE if all fails.
     * @author Chris Newton
     * @access public
     * @since 1.0.0
     */
    public function process_payment($credit_card_number)
    {
        $timestamp = strftime("%Y%m%d%H%M%S");
        mt_srand((double)microtime() * 1000000);

        $rounded_total = round($this->order('total') * 100);

        $currency_code = $this->order('currency_code') ? $this->order('currency_code') : "GBP";

        $hash_string = $timestamp . "." . $this->plugin_settings('your_merchant_id') . "." . $this->order('order_id') . "." . $rounded_total . "." . $currency_code;
        $hash = sha1($hash_string);
        $hash = $hash . "." . $this->plugin_settings('your_secret');
        $hash = sha1($hash);

        $post_array = [
            'MERCHANT_ID' => $this->plugin_settings('your_merchant_id'),
            'ORDER_ID' => $this->order('order_id'),
            'CURRENCY' => $currency_code,
            'AMOUNT' => $rounded_total,
            'TIMESTAMP' => $timestamp,
            'SHA1HASH' => $hash,
            'AUTO_SETTLE_FLAG' => 1,
            'CUST_NUM' => $this->order('member_id'),
            'ct_order_id' => $this->order('order_id'),
        ];

        $this->gateway_exit_offsite($post_array, 'https://epage.payandshop.com/epage.cgi');
        exit;
    }

    // END

    function extload($post = [])
    {
        $auth = [
            'authorized' => false,
            'error_message' => null,
            'failed' => true,
            'processing' => false,
            'declined' => false,
            'transaction_id' => null
        ];

        $order_id = $this->xss_clean($this->arr($post, "ct_order_id"));

        $xss_clean = true;

        if (!$order_id) {
            die($this->lang('realex_order_id_not_specified'));
        }

        $this->relaunch_cart(null, $order_id);

        if (!isset($post['TIMESTAMP']) ||
            !isset($post['ORDER_ID']) ||
            !isset($post['RESULT']) ||
            !isset($post['MESSAGE']) ||
            !isset($post['PASREF']) ||
            !isset($post['AUTHCODE'])) {
            $auth['authorized'] = false;
            $auth['declined'] = false;
            $auth['transaction_id'] = null;
            $auth['failed'] = true;
            $auth['error_message'] = $this->lang("realex_redirect_no_data_sent");

            $this->gateway_order_update($auth, $this->order('entry_id'), $return_url = NULL);
            echo $this->parse_template($this->fetch_template($this->plugin_settings('failure_template')));
            exit;
        }

        $hash_string = $post['TIMESTAMP'] . "."
            . $this->plugin_settings('your_merchant_id') . "."
            . $post['ORDER_ID'] . "."
            . $post['RESULT'] . "."
            . $post['MESSAGE'] . "."
            . $post['PASREF'] . "."
            . $post['AUTHCODE'];

        $hash = sha1($hash_string);
        $hash = $hash . "." . $this->plugin_settings('your_secret');
        $hash = sha1($hash);

        if ($hash != $post['SHA1HASH']) {
            $auth['authorized'] = false;
            $auth['declined'] = false;
            $auth['transaction_id'] = null;
            $auth['failed'] = true;
            $auth['error_message'] = $this->lang('realex_redirect_hashes_dont_match');


        } elseif ($post['RESULT'] == "00") {
            $auth['authorized'] = true;
            $auth['declined'] = false;
            $auth['transaction_id'] = $order_id;
            $auth['failed'] = false;
            $auth['error_message'] = '';


        } else {
            $auth['authorized'] = false;
            $auth['declined'] = true;
            $auth['transaction_id'] = null;
            $auth['failed'] = false;
            $auth['error_message'] = $post['MESSAGE'];


        }


        if (!$this->order('return')) {
            $this->update_order(['return' => $this->plugin_settings('order_complete_template')]);
        }

        $this->checkout_complete_offsite($auth, $order_id, 'template');

        exit;

    } // END

    function arr($array, $key)
    {
        if (isset($array[$key])) {
            return $array[$key];
        } else {
            return null;
        }
    }
}
// END Class
