<?php
    class VuamiSellix extends PaymentGatewayModule
    {
        

        function __construct()
        {
            $this->name             = __CLASS__;

            parent::__construct();
        }

        public function config_fields()
        {
            return [
                'sellix_apikey'          => [
                    'name'              => "API Key",
                    'description'       => '<a target="_blank" href="https://dashboard.sellix.io/settings/security">API Key</a>',
                    'type'              => "text",
                    'value'             => $this->config["settings"]["sellix_apikey"] ?? '',
                    'placeholder'       => "Enter api key..",
                ],
                'sellix_webhook'          => [
                    'name'              => "Webhook Secret",
                    'description'       => '<a target="_blank" href="https://dashboard.sellix.io/settings/shop/general">Webhook secret</a>',
                    'type'              => "password",
                    'value'             => $this->config["settings"]["sellix_webhook"] ?? '',
                    'placeholder'       => "Webhook secret..",
                ],
                'sellix_confirmation'          => [
                    'name'              => "Confirmations",
                    'description'       => 'Select number of confirmations',
                    'type'              => "dropdown",
                    'options' => [
                        '1' => '1',
                        '2' => '2',
                        '3' => '3',
                        '4' => '4',
                        '5' => '5',
                        '6' => '6',
                    ],
                    'value'             => $this->config["settings"]["sellix_confirmation"] ?? 2,
                ]
            ];
        }

        public function area($params=[])
        {
            
            // Invoice Parameters
             $checkoutId = $this->checkout["id"];
             $description = 'Invoice Payment';
             $amount = $params['amount'];
             $currencyCode = $params['currency'];
             // Client Parameters
             $firstname  = $this->clientInfo->name;
             $lastname   = $this->clientInfo->surname;
             $email      = $this->clientInfo->email;
             $address1   = $this->clientInfo->address->address;
             $address2   = '';
             $city       = $this->clientInfo->address->city;
             $state      = $this->clientInfo->address->counti;
             $postcode   = $this->clientInfo->address->zipcode;
             $country    = $this->clientInfo->address->country_code;
             $phone      = $this->clientInfo->phone;
 
             // System Parameters
             $langPayNow = $this->l_payNow;
             $returnUrl = $this->links["return"];
             $callbackUrl = $this->links["callback"];

             $sellixapikey = $this->config["settings"]["sellix_apikey"] ?? '';
             $confirmations = $this->config["settings"]["sellix_confirmation"] ?? 2;
             $webhooksecr = $this->config["settings"]["sellix_webhook"] ?? '';

            $data = [
                'title' => $checkoutId,
                'quantity' => '1',
                'currency' => $currencyCode,
                'gateway' => null,
                'value' => $amount,
                'confirmations' => $confirmations,
                'email' => $email,
                'webhook'=> $callbackUrl,
                'white_label'=>true,
                'return_url' => $returnUrl
                
            ];
            
            $curlpay = curl_init('https://dev.sellix.io/v1/payments');
	        curl_setopt($curlpay, CURLOPT_POST, true);
	        curl_setopt($curlpay, CURLOPT_POSTFIELDS, json_encode($data));
	        curl_setopt($curlpay, CURLOPT_USERAGENT, 'Sellix (PHP ' . PHP_VERSION . ')');
	        curl_setopt($curlpay, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $sellixapikey]);
	        curl_setopt($curlpay, CURLOPT_TIMEOUT, 20);
	        curl_setopt($curlpay, CURLOPT_RETURNTRANSFER, true);
	        $rawresponse = curl_exec($curlpay);
	        curl_close($curlpay);
            $response = json_decode($rawresponse);
            //echo $rawresponse;
            if(!empty($response->status == 200)){
                $status = 'Payment created: Success';

                $htmlOutput = '<form target="_blank" method="POST" action="https://checkout.sellix.io/payment/'.$response->data->invoice->uniqid.'">';
                $htmlOutput .= '<input type="hidden" name="action" value="paynow" />';
                $htmlOutput .= '<input type="submit" class="btn btn-primary" name="pay" value="' . $langPayNow . '" formtarget="_blank" />';
                $htmlOutput .= '</form>';

                //$this->checkout["id"] = $response->data->invoice->uniqid;

            }else{
                $status = 'Payment creatation: Failed';

                $htmlOutput = '<div class="alert alert-danger" role="alert">Unable to pay with selected payment method.</div>';
            }

            return $htmlOutput;
            
        }

        
        
        public function callback()
        {
            $payload = file_get_contents('php://input');

            $data = json_decode($payload);
            if($data->event != "order:paid"){
                exit('Invalid event, only paid accepted!');
            }
            
            
            $secret = $this->config["settings"]["sellix_webhook"] ?? '';
            
            $header_signature = $_SERVER['HTTP_X_SELLIX_SIGNATURE'];
            $signature = hash_hmac('sha512', $payload, $secret);
            
            if (hash_equals($signature, $header_signature)) {

                $invoiceId = $data->data->developer_title;
                
                if(!$invoiceId){
                    $this->error = 'ERROR: invoiceId is empty';
                    return false;
                }

                $checkout       = $this->get_checkout($invoiceId);

                // Checkout invalid error
                if(!$checkout)
                {
                    $this->error = 'Checkout ID unknown';
                    return false;
                }

                // You introduce checkout to the system
                $this->set_checkout($checkout);
                
                if($data->data->status == 'COMPLETED'){
                    $paymentAmount = $data->data->total;
                    $paidCurrency = $data->data->currency;

                    return [
                        'status' => 'successful',
                        'message' => [
                            'Merchant Transaction ID' => $invoiceId,
                        ],
                        'callback_message' => 'Transaction Successful',
                        'paid' => [
                            'amount'        => $paymentAmount,
                            'currency'      => $paidCurrency,
                        ],
                    ];

                }else{

                }
                
                

            }else{
                
                die('There is some problem, please contact support.');
            }
        }

        

    }