<?php
namespace Opencart\Admin\Controller\Extension\AlphaPay\Payment;

class AlphaPay extends \Opencart\System\Engine\Controller
{
    private $error = [];

    const API_KEY = 'payment_alphapay_apikey';
    const MERCHANT_ID = 'payment_alphapay_merchant_id';
    const TITLE = 'payment_alphapay_title';
    const LIFETIME = 'payment_alphapay_lifetime';
    const ORDER_STATUS = 'payment_alphapay_order_status_id';
    const PENDING_STATUS = 'payment_alphapay_pending_status_id';
    const PAID_STATUS = 'payment_alphapay_paid_status_id';
    const INVALID_STATUS = 'payment_alphapay_invalid_status_id';
    const STATUS = 'payment_alphapay_status';
    const SORT_ORDER = 'payment_alphapay_sort_order';

    const FIELDS = [
        self::API_KEY,
        self::MERCHANT_ID,
        self::LIFETIME,
        self::TITLE,
        self::ORDER_STATUS,
        self::PENDING_STATUS,
        self::PAID_STATUS,
        self::INVALID_STATUS,
        self::STATUS,
        self::SORT_ORDER
    ];

    /**
     * @return void
     */
    public function index()
    {
        $data = $this->load->language('extension/alphapay/payment/alphapay');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_payment'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/alphapay', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['action'] = $this->url->link('extension/alphapay/payment/alphapay.save', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        foreach (self::FIELDS as $field) {
            $data[$field] = null;

            if (isset($this->request->post[$field]))
                $data[$field] = $this->request->post[$field];

            if ($this->config->get($field) !== null)
                $data[$field] = $this->config->get($field);
        }

        $this->load->model('localisation/order_status');

        $data['order_statuses'][] = ['order_status_id' => false, 'name' => '—'];
        $data['order_statuses'] = array_merge(
            $data['order_statuses'],
            $this->model_localisation_order_status->getOrderStatuses()
        );

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/alphapay/payment/alphapay', $data));
    }

    /**
     * @return bool
     */
    public function validate()
    {
        $postStr = explode("&", file_get_contents('php://input'));
        $post = [];
        foreach ($postStr as $ele) {
            $row = explode("=", $ele);
            $post[$row[0]] = $row[1];
        }

        if (!$post[self::API_KEY]) {
            $this->error['apikey'] = $this->language->get('error_apikey');
        }

        if (!$post[self::MERCHANT_ID]) {
            $this->error['merchant_id'] = $this->language->get('error_merchant_id');
        }

        return !$this->error;
    }

    public function install(): void {

    }

    public function uninstall(): void {

    }

    public function save(): void {
        $data = $this->load->language('extension/alphapay/payment/alphapay');
        $json = [];
        $post = $this->getKeyValueArray(file_get_contents('php://input'));
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_alphapay', $post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $validationErrors = array("warning", "merchant_id", "apikey");
        foreach ($validationErrors as $error) $data['error_' . $error] = (isset($this->error[$error])) ? $this->error[$error] : "";

        if (!$this->user->hasPermission('modify', 'extension/alphapay/payment/alphapay')) {
            $json['error'] = $this->language->get('error_permission');
        }

        $json['success'] = $this->language->get('text_success');
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    protected function getKeyValueArray($inputString) {
        $postStr = explode("&", $inputString);
        $post = [];

        foreach ($postStr as $ele) {
            $row = explode("=", $ele);
            $key = isset($row[0]) ? $row[0] : "";
            $val = isset($row[1]) ? $row[1] : "";
            if ($row[0] !== "")
            {
                $post[$key] = isset($val) ? $val : "";
            }
        }

        return $post;
    }
}
