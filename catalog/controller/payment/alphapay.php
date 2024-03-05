<?php
namespace Opencart\Catalog\Controller\Extension\AlphaPay\Payment;

require_once DIR_EXTENSION . 'alphapay/system/library/alphapay/src/Payment.php';

class AlphaPay extends \Opencart\System\Engine\Controller
{
    const API_KEY = 'payment_alphapay_apikey';
    const MERCHANT_ID = 'payment_alphapay_merchant_id';
    const LIFETIME = 'payment_alphapay_lifetime';

    const URL_CALLBACK = 'extension/payment/alphapay/callback';
    const URL_RETURN = 'extension/payment/alphapay/return_back';

    const ALPHAPAY_ERROR_STATUSES = [
        'fail',
        'system_fail',
        'wrong_amount',
        'cancel',
    ];

    const ALPHAPAY_PENDING_STATUSES = [
        'process',
        'check',
    ];

    const PAID_STATUSES = [
        'paid',
        'paid_over',
    ];

    /**
     * @return mixed
     */
    public function index()
    {
        $this->load->language('extension/payment/alphapay');
        $this->load->model('checkout/order');
        $base_url = $this->config->get('config_url');
        $data = [];
        $data['button_confirm'] = $this->language->get('button_confirm');
        //$data['action'] = $this->url->link('extension/payment/alphapay/checkout', '', true);
        $data['action'] = $base_url.'index.php?route=extension/alphapay/payment/alphapay.checkout';

        return $this->load->view('extension/alphapay/payment/alphapay', $data);
    }

    /**
     * @return void
     */
    public function checkout()
    {
        $this->language->load('extension/payment/alphapay');
        $this->load->model('checkout/order');

        $orderId = $this->session->data['order_id'];
        $orderInfo = $this->model_checkout_order->getOrder($orderId);
        $merchantId = $this->config->get(self::MERCHANT_ID);
        $total = (string) $this->currency->format(
            $orderInfo['total'],
            $orderInfo['currency_code'],
            $orderInfo['currency_value'],
            false
        );

        $paymentData = [
            'amount' => $total,
            'currency' => $this->session->data['currency'],
            'merchant' => $merchantId,
            'order_id' => (string) $orderId,
            'url_return' => $this->url(self::URL_RETURN),
            'url_callback' => $this->url(self::URL_CALLBACK),
            'lifetime' => $this->config->get(self::LIFETIME),
        ];

        $jsonResponse = [
            'state' => 'ok',
        ];

        try {
            $paymentClient = $this->initPaymentClient();
            $response = $paymentClient->create($paymentData);
            $jsonResponse['url'] = $response['url'];

            $paymentStatus = $this->config->get('payment_alphapay_order_status_id');
            $this->model_checkout_order->addHistory(
                $this->session->data['order_id'],
                $paymentStatus ? $paymentStatus : 1,
                'Created new link order'
            );
        } catch (\RequestBuilderException $e) {
            if ($errors = $e->getErrors()) {
                foreach ($errors as $error) {
                    $this->log->write("Method {$e->getMethod()} error: " . $error);
                }
            }

            $jsonResponse['state'] = 'error';
            $jsonResponse['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($jsonResponse));
    }

    /**
     * @return void
     */
    public function return_back()
    {
        $this->cart->clear();
        $this->response->redirect($this->url->link('checkout/success', '', true));
    }

    /**
     * @return void
     */
    public function callback()
    {
        $this->load->library('log');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$this->isSignValid($data)) {
            $this->log->write("Signature is invalid. Please, check your API key.");
            exit;
        }

        $orderId = $data['order_id'];
        if (!$orderId) {
            exit;
        }

        $this->load->model('checkout/order');
        $orderInfo = $this->model_checkout_order->getOrder($orderId);
        if (!$orderInfo) {
            $this->log->write("Order with id '$orderId' does not exist.");
            exit;
        }

        $alphapayOrderStatus = $data['status'];

        $ocOrderStatus = null;
        if (in_array($alphapayOrderStatus, self::ALPHAPAY_ERROR_STATUSES)) {
            $ocOrderStatus = 'payment_alphapay_invalid_status_id';
        } elseif (in_array($alphapayOrderStatus, self::ALPHAPAY_PENDING_STATUSES)) {
            $ocOrderStatus = 'payment_alphapay_pending_status_id';
        } elseif (in_array($alphapayOrderStatus, self::PAID_STATUSES)) {
            $ocOrderStatus = 'payment_alphapay_paid_status_id';
        }

        if ($ocOrderStatus && $ocStatus = $this->config->get($ocOrderStatus)) {
            $this->load->model('checkout/order');
            $comment = 'AlphaPay Order id: ' . $orderId . '; Status: ' . $alphapayOrderStatus;
            $this->model_checkout_order->addHistory($orderId, $ocStatus, $comment);
        } elseif (!$ocOrderStatus) {
            $this->log->write("Something went wrong. AlphaPay status : $alphapayOrderStatus; AlphaPay order: $orderId");
        }
    }

    /**
     * @param array $data
     * @return bool
     */
    private function isSignValid(array &$data)
    {
        $apiKey = $this->config->get(self::API_KEY);
        $signature = $data['sign'];
        if (!$signature) {
            return false;
        }

        unset($data['sign']);

        $hash = md5(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)) . $apiKey);
        if (!hash_equals($hash, $signature)) {
            return false;
        }

        return true;
    }

    /**
     * @return Payment
     */
    private function initPaymentClient()
    {
        $this->load->model('setting/setting');
        $apiKey = $this->config->get(self::API_KEY);
        $merchantId = $this->config->get(self::MERCHANT_ID);

        return new \Payment($apiKey, $merchantId);
    }

    /**
     * @param $route
     * @return string
     */
    private function url($route)
    {
        $protocol = ($_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];

        return "$protocol://$host/index.php?route=$route";
    }
}
