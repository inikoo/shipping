<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Tue, 15 Sep 2020 12:45:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\Country;
use App\Models\ShipperAccount;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;


/**
 * Class ApcGb
 *
 * @property string $slug
 * @property object $shipper
 * @property array  $credentials
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class ApcGb extends Shipper_Provider {


    protected $table = 'apc_gb_shipper_providers';

    protected $credentials_rules = [
        'email'    => [
            'required',
            'email'
        ],
        'password' => ['required'],

    ];


    function __construct(array $attributes = []) {
        parent::__construct($attributes);

        $this->api_url = env('APC_API_URL', "https://apc.hypaship.com/api/3.0/");

    }

    public function createLabel(Request $request, ShipperAccount $shipperAccount) {


        $headers = [
            "remote-user: Basic ".base64_encode($shipperAccount->credentials['email'].':'.$shipperAccount->credentials['password']),
            "Content-Type: application/json"
        ];


        $params      = array(
            'Orders' => [
                'Order' => $this->get_shipment_parameters($request, $shipperAccount)
            ]
        );
        $apiResponse = $this->call_api(
            $this->api_url.'Orders.json', $headers, $params
        );


        $result = [];


        if ($apiResponse['status'] == 200) {
            if ($apiResponse['data']['Orders']['Messages']['Code'] == 'SUCCESS') {
                $data = $apiResponse['data']['Orders']['Order'];

                $result['tracking_number'] = $data['WayBill'];
                $result['label_link']      = env('APP_URL').'/async_labels/'.$shipperAccount->id.'/'.$data['OrderNumber'];
                $result['shipment_id']     = $data['OrderNumber'];

                return $result;
            }


        }
        $result['errors'] = [json_encode($apiResponse['data'])];


        return $result;


    }

    function prepareShipment($shipperAccount, $request, $pickUp, $shipTo, $parcelsData, $cash_on_delivery) {


        try {
            $pickup_date = new Carbon(Arr::get($pickUp, 'date'));
        } catch (Exception $e) {
            $pickup_date = new Carbon();
        }


        if (Arr::get($shipTo, 'organization') != '') {
            $name = Arr::get($shipTo, 'organization');
        } else {
            $name = Arr::get($shipTo, 'contact');
        }

        $country = (new Country)->where('code', $shipTo['country_code'])->first();

        $address2 = Arr::get($shipTo, 'address_line_2');

        if (in_array(
            $country->code, [
                              'GB',
                              'IM',
                              'JE',
                              'GG'
                          ]
        )) {
            $postalCode = Arr::get($shipTo, 'postal_code');
        } else {
            $postalCode = 'INT';
            $address2   = trim($address2.' '.trim(Arr::get($shipTo, 'sorting_code').' '.Arr::get($shipTo, 'postal_code')));
        }


        $items = [];
        foreach ($parcelsData as $parcelData) {
            array_push(
                $items, [
                          'Type'   => 'ALL',
                          'Weight' => $parcelData['weight'],
                          'Length' => $parcelData['depth'],
                          'Width'  => $parcelData['width'],
                          'Height' => $parcelData['height']
                      ]
            );

        }


        $params = [
            'CollectionDate'  => $pickup_date->format('d/m/Y'),
            'ReadyAt'         => Arr::get($pickUp, 'ready', '16:30'),
            'ClosedAt'        => Arr::get($pickUp, 'end', '17:00'),
            // 'ProductCode'     => $request->get('service_type', 'ND16'),
            'Reference'       => $request->get('reference'),
            'Delivery'        => [
                'CompanyName'  => $name,
                'AddressLine1' => Arr::get($shipTo, 'address_line_1'),
                'AddressLine2' => $address2,
                'PostalCode'   => $postalCode,
                'City'         => Arr::get($shipTo, 'locality'),
                'County'       => Arr::get($shipTo, 'administrative_area'),
                'CountryCode'  => $country->code,
                'Contact'      => [
                    'PersonName'   => Arr::get($shipTo, 'contact'),
                    'PhoneNumber'  => Arr::get($shipTo, 'phone'),
                    'Email'        => Arr::get($shipTo, 'email'),
                    'Instructions' => $request->get('note'),
                ],

            ],
            'ShipmentDetails' => [
                'NumberOfPieces' => count($parcelsData),
                'Items'          => ['Item' => $items]
            ]
        ];


        if ($request->get('service_type') != '') {
            if ($request->get('service_type') != 'Auto') {
                $params['ProductCode'] = $request->get('service_type');
            }
        } else {

            $productCode = '';

            if (count($parcelsData) == 1) {

                $dimensions = [
                    $parcelsData[0]['height'],
                    $parcelsData[0]['width'],
                    $parcelsData[0]['depth']
                ];
                rsort($dimensions);


                if ($parcelsData[0]['weight'] <= 5 and $dimensions[0] <= 45 and $dimensions[1] <= 35 and $dimensions[2] <= 20) {
                    $productCode = 'LW16';
                }



                if ($parcelsData[0]['weight'] <= 5 and $dimensions[0] <= 45 and $dimensions[1] <= 35 and $dimensions[2] <= 20) {
                    $productCode = 'LW16';
                }


            }

            if (preg_match('/^BT/', $postalCode)) {
                $productCode = 'ROAD';
            }

            if($productCode!=''){
                $params['ProductCode'] = $productCode;

            }

        }


        return $params;

    }

    function get_label($labelID, $shipperAccount) {

        $headers = [
            "remote-user: Basic ".base64_encode($shipperAccount->credentials['email'].':'.$shipperAccount->credentials['password']),
            "Content-Type: application/json"
        ];

        $apiResponse = $this->call_api(
            $this->api_url.'Orders/'.$labelID.'.json', $headers, [], 'GET'
        );

        return base64_decode($apiResponse['data']['Orders']['Order']['Label']['Content']);


    }

}

