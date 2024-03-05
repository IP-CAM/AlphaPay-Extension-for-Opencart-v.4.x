<?php

namespace Opencart\Catalog\Model\Extension\AlphaPay\Payment;


class AlphaPay extends \Opencart\System\Engine\Model {

    public function getMethods (array $address = []): array {
        $title = $this->config->get('payment_alphapay_title');

        $option_data['alphapay'] = [
            'code' => 'alphapay.alphapay',
            'name' => ($title ?: 'ALPHAPAY')
        ];


        $method_data = [
            'code'       => 'alphapay',
            'name'       => ($title ?: 'ALPHAPAY'),
            'option'     => $option_data,
            'sort_order' => $this->config->get('payment_alphapay_sort_order')
        ];

        return $method_data;
    }
}
