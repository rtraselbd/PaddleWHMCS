<?php

namespace WHMCS\Module\Gateway\RTPaddle;

use WHMCS\Module\Gateway\RTPaddle\HTTPClient;

class PaddleAPI extends HTTPClient
{
    public const ACTIVE = 'active';
    public const ARCHIVED = 'archived';

    private $baseUrl;
    private $authCode;

    public function __construct($authCode, $sandbox = false, $verbose = false)
    {
        parent::__construct($verbose);

        $this->baseUrl = $sandbox ? 'https://sandbox-api.paddle.com/' : 'https://api.paddle.com/';
        $this->authCode = $authCode;
        $this->setHttpHeader('Authorization', 'Bearer ' . $this->authCode);
        $this->setHttpHeader('Paddle-Version', '1');
    }

    public function createPrice(array $data): array
    {
        if (!isset($data['description'], $data['product_id'], $data['unit_price'], $data['unit_price']['amount'], $data['unit_price']['currency_code'])) {
            return [
                'status'  => 'error',
                'message' => 'Required fields (description, product_id and unit_price) are missing',
            ];
        }

        $unitPrice = [
            'amount' => $this->usdToCents($data['unit_price']['amount']),
            'currency_code' => $data['unit_price']['currency_code'] ?? 'USD'
        ];

        $requestData = [
            'description'           => $data['description'],
            'product_id'            => $data['product_id'],
            'unit_price'            => $unitPrice,
            'billing_cycle'         => $data['billing_cycle'] ?? '',
            'trial_period'          => $data['trial_period'] ?? '',
            'tax_mode'              => $data['tax_mode'] ?? '',
            'unit_price_overrides'  => $data['unit_price_overrides'] ?? '',
            'quantity'              => $data['quantity'] ?? '',
            'custom_data'           => $data['custom_data'] ?? '',
        ];

        try {
            return $this->post($this->baseUrl . "prices", array_filter($requestData));
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function updatePrice($price_id, array $data): array
    {
        $status = $data['status'] ?? '';
        if (!empty($status) && ($status !== self::ACTIVE && $status !== self::ARCHIVED)) {
            return [
                'status'  => 'error',
                'message' => 'Invalid status value',
            ];
        }

        $requestData = [
            'description'           => $data['description'] ?? '',
            'product_id'            => $data['product_id'] ?? '',
            'unit_price'            => $data['unit_price'] ?? '',
            'billing_cycle'         => $data['billing_cycle'] ?? '',
            'trial_period'          => $data['trial_period'] ?? '',
            'tax_mode'              => $data['tax_mode'] ?? '',
            'unit_price_overrides'  => $data['unit_price_overrides'] ?? '',
            'quantity'              => $data['quantity'] ?? '',
            'status'                => $status,
            'custom_data'           => $data['custom_data'] ?? '',
        ];

        $url = $this->baseUrl . "prices/{$price_id}";

        try {
            return $this->patch($url, array_filter($requestData));
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function getTransaction($transaction_id): array
    {
        $url = $this->baseUrl . "transactions/{$transaction_id}";
        try {
            return $this->get($url);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
