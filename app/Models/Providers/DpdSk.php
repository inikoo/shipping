<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Fri, 04 Sep 2020 17:07:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\Country;
use App\Models\ShipperAccount;
use App\Models\Tenant;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;


/**
 * Class DpdSk
 *
 * @property string $slug
 * @property object $shipper
 * @property array  $credentials
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DpdSk extends Model {


    protected $table = 'dpd_sk_shipper_providers';

    protected $casts = [
        'data' => 'array'
    ];

    protected $attributes = [
        'data' => '{}',
    ];


    protected $fillable = [
        'slug',
    ];

    public function shipper() {
        return $this->morphOne('App\Models\Shipper', 'provider');
    }


    public function createShipperAccount(Request $request) {

        $credentials_rules = [
            'apiKey'   => ['required'],
            'username' => [
                'required',
                'email'
            ],
            'delisId'  => ['required'],
            'pickupID' => ['required'],
            'bankID'   => [],

        ];

        $credentials_validator = Validator::make(
            $request->all(), $credentials_rules
        );

        if ($credentials_validator->fails()) {
            return response()->json(['errors' => $credentials_validator->errors()]);
        }

        $credentials = [];
        foreach ($credentials_rules as $credential_field => $foo) {
            $credentials[$credential_field] = $request->get($credential_field);
        }
        $credentials = array_filter($credentials);

        $tenant = (new Tenant)->where('slug', $request->get('tenant'))->first();

        $shipperAccount              = new ShipperAccount;
        $shipperAccount->slug        = $request->get('shipper');
        $shipperAccount->label       = $request->get('label');
        $shipperAccount->shipper_id  = $this->shipper->id;
        $shipperAccount->tenant_id   = $tenant->id;
        $shipperAccount->credentials = $credentials;
        $shipperAccount->save();

        return $shipperAccount;


    }

    public function createLabel(Request $request, ShipperAccount $shipperAccount) {

        $result = [
            'errors' => [],
            'status' => 200


        ];

        $this->credentials = $shipperAccount->credentials;
        $parcels           = json_decode($request->get('parcels'), true);
        $shipTo            = json_decode($request->get('ship_to'), true);
        $pickUp            = json_decode($request->get('pick_up'), true);
        $cash_on_delivery  = json_decode($request->get('cod', '{}'), true);

        $shipment = $this->prepareShipment(
            $request, $pickUp, $shipTo, $parcels, $cash_on_delivery

        );

        $params = array(
            'jsonrpc' => '2.0',
            'method'  => 'create',
            'params'  => array(
                'DPDSecurity' => array(
                    'SecurityToken' => array(
                        'ClientKey' => $this->credentials['apiKey'],
                        'Email'     => $this->credentials['username'],
                    ),
                ),
                'shipment'    => array(
                    0 => $shipment

                ),
            ),
            'id'      => 'null',
        );

        $curl = curl_init();

        curl_setopt_array(
            $curl, array(
                     CURLOPT_URL            => "https://api.dpdportal.sk/shipment",
                     CURLOPT_RETURNTRANSFER => true,
                     CURLOPT_ENCODING       => "",
                     CURLOPT_MAXREDIRS      => 10,
                     CURLOPT_TIMEOUT        => 0,
                     CURLOPT_FOLLOWLOCATION => true,
                     CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                     CURLOPT_CUSTOMREQUEST  => "POST",
                     CURLOPT_POSTFIELDS     => json_encode($params),
                     CURLOPT_HTTPHEADER     => array(
                         "Content-Type: application/json"
                     ),
                 )
        );


        $response = json_decode(curl_exec($curl), true);

        curl_close($curl);

        if ($response == null) {
            $result['errors'][] = ['fail' => 'The API server returned an empty, unknown, or unexplained response'];
            $result['status']   = 530;

            return $result;
        }

        if (!empty($response['error'])) {

            if ($response['error']['code'] == 103) {
                $result['errors'][] = ['authentication' => $response['error']['message']];
                $result['status']   = 401;
            } elseif ($response['error']['code'] == 401 or $response['error']['code'] == 519) {

                $msg = $response['error']['message'];


                if (isset($response['error']['data']['additional_details'])) {
                    $msg .= ' '.$response['error']['data']['additional_details'];
                }

                $result['errors'][] = ['invalid' => $msg];
                $result['status']   = 422;
            }


        } else {

            $res = array_pop($response['result'])[0];
            if (!$res['success']) {
                $result['status'] = 422;
                foreach ($res['messages'] as $error) {

                    $result['errors'][] = [$res['ackCode'] => $error['value'].' ('.$error['envelope'].($error['element'] != '' ? '.'.$error['element'] : '').')'];

                }
            } else {
                $result['tracking_number'] = substr($res['mpsid'], 0, -8);
                $result['label_link']      = $res['label'];

            }
        }

        return $result;


    }

    private function prepareShipment($request, $pickUp, $shipTo, $parcels, $cash_on_delivery) {

        try {
            $pickup_date = new Carbon(Arr::get($pickUp, 'date'));
        } catch (Exception $e) {
            $pickup_date = new Carbon();
        }

        $pickUpTimeWindow = [];

        if (Arr::get($pickUp, 'start')) {
            $pickUpTimeWindow['beginning'] = preg_replace('/:/', '', Arr::get($pickUp, 'start'));
        }
        if (Arr::get($pickUp, 'end')) {
            $pickUpTimeWindow['end'] = preg_replace('/:/', '', Arr::get($pickUp, 'end'));
        }
        if ($pickUpTimeWindow == []) {
            $pickUpTimeWindow['end'] = '1600';
        }

        if (Arr::get($shipTo, 'organization') != '') {
            $type       = 'b2b';
            $name       = Arr::get($shipTo, 'organization');
            $nameDetail = Arr::get($shipTo, 'contact');
        } else {
            $type       = 'b2c';
            $name       = Arr::get($shipTo, 'contact');
            $nameDetail = '';
        }

        $country = (new Country)->where('code', $shipTo['country_code'])->first();

        $services = [];


        if (!empty($cash_on_delivery)) {

            $order_id = preg_replace("/[^0-9]/", "", $request->get('reference'));
            if ($order_id == '') {
                $order_id = rand(1, 100);
            }

            if (Arr::get($cash_on_delivery, 'accept_card', 'No') == 'Yes') {
                $paymentMethod = 1;
            } else {
                $paymentMethod = 0;
            }

            $services = [
                'cod' => [
                    'amount'         => $cash_on_delivery['amount'],
                    'currency'       => $cash_on_delivery['currency'],
                    'bankAccount'    => [
                        'id' => $this->credentials['bankID'],
                    ],
                    'variableSymbol' => $order_id,
                    'paymentMethod'  => $paymentMethod,
                ]
            ];
        }


        return array(
            'reference'        => $request->get('reference'),
            'delisId'          => $this->credentials['delisId'],
            'note'             => $request->get('note'),
            'product'          => $request->get('shipping_product', 1),
            'pickup'           => array(
                'date'       => $pickup_date->format('Ymd'),
                'timeWindow' => $pickUpTimeWindow
            ),
            'addressSender'    => array(
                'id' => $this->credentials['pickupID'],
            ),
            'addressRecipient' => array(
                'type'         => $type,
                'name'         => $name,
                'nameDetail'   => $nameDetail,
                'street'       => Arr::get($shipTo, 'address_line_1'),
                'streetDetail' => Arr::get($shipTo, 'address_line_2'),
                'zip'          => trim(Arr::get($shipTo, 'sorting_code').' '.Arr::get($shipTo, 'postal_code')),
                'country'      => $country->code_iso_numeric,
                'city'         => Arr::get($shipTo, 'locality'),
                'phone'        => Arr::get($shipTo, 'phone'),
                'email'        => Arr::get($shipTo, 'email'),
                'note'         => '',
            ),
            'parcels'          => ['parcel' => $parcels],
            'services'         => $services
        );


    }

}
