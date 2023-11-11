<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function RTPaddle_config()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Paddle Payment Gateway',
        ],
        'productId' => [
            'FriendlyName' => 'Product ID',
            'Type' => 'text',
            'Size' => '40',
            'Description'  => '<br>Enter Paddle Product Id',
        ],
        'sellerId' => [
            'FriendlyName' => 'Vendor ID / Seller ID',
            'Type' => 'text',
            'Size' => '40',
            'Description'  => '<br>Collect Paddle Vendor ID / Seller ID from Developer Tools',
        ],
        'authCode' => [
            'FriendlyName' => 'Auth Code',
            'Type' => 'text',
            'Size' => '40',
            'Description'  => '<br>Collect Paddle Auth Code from Developer Tools > Authentication',
        ],
        'fee' => [
            'FriendlyName' => 'Fee (%)',
            'Type' => 'text',
            'Size' => '40',
            'Default'      => 0.30,
            'Description'  => '<br>Gateway fee if you want to add',
        ],
        'extra_fee' => [
            'FriendlyName' => 'Extra Fee (USD)',
            'Type' => 'text',
            'Size' => '40',
            'Default'      => 3,
            'Description'  => '<br>Gateway extra fee if you want to add',
        ],
        'exchnage_rate'      => [
            'FriendlyName' => 'Exchange Rate',
            'Type'         => 'text',
            'Default'      => 110,
            'Description'  => '<br>1 USD = ? BDT',
        ],
        'sandbox' => [
            'FriendlyName' => 'Sandbox Mode',
            'Type' => 'yesno',
            'Description' => 'Tick this to Run on Sandbox MODE',
        ],
    ];
}

function RTPaddle_link($params)
{
    $paddleScripts = RTPaddle_scriptsHandle($params);
    $errorMessage = RTPaddle_errormessage();
    $markup       = <<<HTML
    <button class="btn btn-primary" id="rt_paddle_button">Pay with Paddle</button>
    <style type="text/css">
        #rt_paddle_button { max-width: 175px; height: auto;}
        #rt_paddle_button:hover { cursor: pointer; }
        #rt_paddle_button.loading { opacity: 0.5; pointer-events: none;}
        #paddle_button { display: none; }
    </style>
    $paddleScripts
    $errorMessage
HTML;
    return $markup;
}


function RTPaddle_errormessage()
{
    $errors = [
        'cancelled' => 'Payment has cancelled',
        'closed'    => 'You did not completed the payment process.',
        'irs'       => 'Invalid response from Paddle API.',
        'tau'       => 'The transaction has been already used.',
        'lpa'       => 'You\'ve paid less than amount is required.',
        'sww'       => 'Something went wrong'
    ];

    $message = null;

    if (!empty($_REQUEST['errorCode'])) {
        $error   = isset($errors[$_REQUEST['errorCode']]) ? $errors[$_REQUEST['errorCode']] : 'Unknown Error!';
        $message = '<div class="alert alert-danger" style="margin-top: 10px;" role="alert">' . $error . '</div>';
    }
    return $message;
}


function RTPaddle_scriptsHandle($params)
{
    $script         = 'https://cdn.paddle.com/paddle/v2/paddle.js';
    $paddleApiUrl    = $params['systemurl'] . 'modules/gateways/callback/' . $params['paymentmethod'] . '.php';
    $returnurl      = $params['returnurl'];
    $invoiceid      = $params['invoiceid'];
    $sandboxEnabled  = !empty($params['sandbox']) ? 'on' : 'off';

    $markup = <<<HTML
<script type="text/javascript" src="$script"></script>
<script>
    window.addEventListener('load', function () {
        const RTPaddleButton = $('#rt_paddle_button');
        const paddleApiUrl = "$paddleApiUrl";
        const returnurl = "$returnurl";
        const invoiceid = "$invoiceid";
        const sandboxEnabled = '{$sandboxEnabled}';
        const sellerID = {$params['sellerId']};
        const customerEmail = '{$params['clientdetails']['email']}';
        const countryCode = '{$params['clientdetails']['countrycode']}';

        RTPaddleButton.on('click', function (e) {
            e.preventDefault();
            RTPaddleButton.addClass('loading');
            $.ajax({
                method: "POST",
                url: paddleApiUrl,
                data: {
                    action: 'init',
                    id: invoiceid
                }
            }).done(function (response) {
                if (response.status === 'error') {
                    window.location = returnurl + '&errorCode=irs';
                }
                else {
                    PaddleHandle(response);
                }
            }).fail(function () {
                window.location = returnurl + '&errorCode=500';
            });
        });

        function PaddleHandle(params) {
            if (sandboxEnabled == 'on') {
                Paddle.Environment.set('sandbox');
            }

            Paddle.Setup({
                seller: sellerID,
                eventCallback: function(eventresponse) {
                    paddleEventHandle(eventresponse, params.priceId);
                }
            });

            Paddle.Checkout.open({
                settings: {
                    displayMode: 'overlay',
                    theme: 'light',
                    locale: 'en',
                    successUrl: returnurl
                },
                items: [{
                    priceId: params.priceId,
                    quantity: 1
                }],
                customer: {
                    email: customerEmail,
                    address: {
                        countryCode: countryCode
                    }
                }
            });
        }
        
        function paddleEventHandle(params, priceId) {
            if (params.name == 'checkout.closed') {
                window.location = returnurl + '&errorCode=closed';
            }
        
            if (params.name == 'checkout.error') {
                window.location = returnurl + '&errorCode=irs';
            }
        
            if (params.type == 'checkout.warning') {
                window.location = returnurl + '&errorCode=irs';
            }
        
            if (params.name == 'checkout.completed') {
                $.ajax({
                    method: "POST",
                    url: paddleApiUrl,
                    data: {
                        action: 'verify',
                        id: invoiceid,
                        priceId: priceId,
                        transactionId: params.data.transaction_id
                    }
                }).done(function(response) {
                    if (response.status == 'success') {
                        window.location = returnurl;
                    } else {
                        window.location = returnurl + '&errorCode=' + response.errorCode;
                    }
                }).fail(function() {
                    window.location = returnurl + '&errorCode=500';
                });
            }
        }
    });
</script>
HTML;

    return $markup;
}
