<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use Symfony\Component\HttpFoundation\Request;
use WHMCS\Module\Gateway\RTPaddle\PaddleAPI;
use WHMCS\Database\Capsule;
use Carbon\Carbon;

class RTPaddle
{
    private static $instance;
    private $paddle;
    private $gatewayModuleName;
    private $gatewayParams;
    public  $isActive;
    private $gatewayCurrency;
    private $customerCurrency;
    private $convoRate;
    private $invoice;
    private $due;
    private $fee;
    private $extraFee;
    private $total;
    public  $request;

    private function __construct()
    {
        $this->setRequest();
        $this->setGateway();
        $this->setInvoice();
    }

    public static function init()
    {
        if (self::$instance == null) {
            self::$instance = new RTPaddle;
        }

        return self::$instance;
    }

    private function setGateway()
    {
        $this->gatewayModuleName = basename(__FILE__, '.php');
        $this->gatewayParams = getGatewayVariables($this->gatewayModuleName);
        $this->isActive = !empty($this->gatewayParams['type']);
        $sandboxEnabled  = !empty($this->gatewayParams['sandbox']);
        $this->paddle = new PaddleAPI($this->gatewayParams['authCode'], $sandboxEnabled);
    }

    private function setRequest()
    {
        $this->request = Request::createFromGlobals();
    }

    private function setInvoice()
    {
        $this->invoice = localAPI('GetInvoice', [
            'invoiceid' => $this->request->get('id'),
        ]);

        $this->setCurrency();
        $this->setDue();
        $this->setFee();
        $this->setExtraFee();
        $this->setTotal();
    }

    private function setCurrency()
    {
        $this->gatewayCurrency = (int) $this->gatewayParams['convertto'];
        $this->customerCurrency = (int) Capsule::table('tblclients')
            ->where('id', '=', $this->invoice['userid'])
            ->value('currency');

        if (!empty($this->gatewayCurrency) && ($this->customerCurrency !== $this->gatewayCurrency)) {
            $this->convoRate = $this->gatewayParams['exchnage_rate'];
        } else {
            $this->convoRate = 1;
        }
    }

    private function setDue()
    {
        $this->due = $this->invoice['balance'];
    }

    private function setFee()
    {
        $this->fee = empty($this->gatewayParams['fee']) ? 0 : (($this->gatewayParams['fee'] / 100) * $this->due);
    }

    private function setExtraFee()
    {
        $this->extraFee = empty($this->gatewayParams['extra_fee']) ? 0 : $this->gatewayParams['extra_fee'];
    }

    private function setTotal()
    {
        $this->total = round(($this->due + $this->fee + $this->extraFee) / $this->convoRate, 2);
    }

    private function checkTransaction($trxId)
    {
        return localAPI('GetTransactions', ['transid' => $trxId]);
    }

    private function logTransaction($payload)
    {
        return logTransaction(
            $this->gatewayParams['name'],
            [
                $this->gatewayModuleName => $payload,
                'request_data' => $this->request->request->all(),
            ],
            $payload['transactionStatus']
        );
    }

    private function addTransaction($trxId)
    {
        $fields = [
            'invoiceid' => $this->invoice['invoiceid'],
            'transid' => $trxId,
            'gateway' => $this->gatewayModuleName,
            'date' => Carbon::now()->toDateTimeString(),
            'amount' => $this->due,
            'fees' => $this->fee + $this->extraFee
        ];
        $add = localAPI('AddInvoicePayment', $fields);

        return array_merge($add, $fields);
    }

    private function initPayment()
    {
        try {

            $requestData = [
                'description' => "Invoice #{$this->invoice['invoiceid']}",
                'product_id' => $this->gatewayParams['productId'],
                'unit_price' => [
                    'amount' => $this->total,
                    'currency_code' => 'USD',
                ],
            ];


            $response = $this->paddle->createPrice($requestData);
            if (isset($response['id'])) {
                return [
                    'status'    => 'success',
                    'message'   => 'Price has been created!',
                    'priceId' => $response['id']
                ];
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    private function verifyPayment()
    {
        $priceId = $this->request->get('priceId');
        $transactionId = $this->request->get('transactionId');
        try {
            $response = $this->paddle->getTransaction($transactionId);
            if (is_array($response) && isset($response['status'])) {
                $this->paddle->updatePrice($priceId, ['status' => 'archived']);
                return [
                    'status'            => trim($response['status']),
                    'amount'            => round(($response['details']['totals']['subtotal'] / 100), 2),
                    'transaction_id'    => $response['id']
                ];
            }
        } catch (Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function createPayment()
    {
        try {
            $response = $this->initPayment();
            if (is_array($response) && isset($response['priceId'])) {
                return $response;
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function makeTransaction()
    {
        try {
            $executePayment = $this->verifyPayment();
            if (isset($executePayment['status']) && ($executePayment['status'] === 'paid' || $executePayment['status'] === 'completed')) {
                $existing = $this->checkTransaction($executePayment['transaction_id']);

                if ($existing['totalresults'] > 0) {
                    return [
                        'status' => 'error',
                        'message' => 'The transaction has already been used.',
                        'errorCode' => 'tau'
                    ];
                }

                if ($executePayment['amount'] < $this->total) {
                    return [
                        'status' => 'error',
                        'message' => 'You\'ve paid less than the required amount.',
                        'errorCode' => 'lpa'
                    ];
                }

                $this->logTransaction($executePayment);

                $trxAddResult = $this->addTransaction($executePayment['transaction_id']);

                if ($trxAddResult['result'] === 'success') {
                    return [
                        'status' => 'success',
                        'message' => 'The payment has been successfully verified.',
                    ];
                }
            }
            return [
                'status' => 'error',
                'message' => 'Something Went Wrong',
                'errorCode' => 'sww'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'errorCode' => 'irs'
            ];
        }
    }
}

$RTPaddle = RTPaddle::init();
if (!$RTPaddle->isActive) {
    die("The gateway is unavailable.");
}

$response = [
    'status'  => 'error',
    'message' => 'Invalid action.'
];

$action = $RTPaddle->request->get('action');

if ($action === 'init') {
    $response = $RTPaddle->createPayment();
}
if ($action === 'verify') {
    $response = $RTPaddle->makeTransaction();
}
header('Content-Type: application/json');
die(json_encode($response));
