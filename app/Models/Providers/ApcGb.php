<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Tue, 15 Sep 2020 12:45:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\Country;
use App\Models\Shipment;
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

    public function createLabel(Shipment $shipment, Request $request, ShipperAccount $shipperAccount) {

        $debug=Arr::get($shipperAccount->data, 'debug') == 'Yes';

        $headers = [
            "remote-user: Basic ".base64_encode($shipperAccount->credentials['email'].':'.$shipperAccount->credentials['password']),
            "Content-Type: application/json"
        ];


        $params            = array(
            'Orders' => [
                'Order' => $this->getShipmentParameters($request, $shipperAccount)
            ]
        );

        if ($debug) {
            $shipmentData=$shipment->data;
            data_fill($shipmentData,'debug.request',$params);
            $shipment->data=$shipmentData;
            $shipment->save();
        }



        $apiResponse = $this->callApi(
            $this->api_url.'Orders.json', $headers, json_encode($params)
        );


        if ($debug) {
            $shipmentData=$shipment->data;
            data_fill($shipmentData,'debug.response', $apiResponse['data']);
            $shipment->data=$shipmentData;
        }


        $shipment->status   = 'error';


        $result = [];


        if ($apiResponse['status'] == 200) {
            if ($apiResponse['data']['Orders']['Messages']['Code'] == 'SUCCESS') {

                $shipment->status = 'success';


                $data = $apiResponse['data']['Orders']['Order'];

                $result['tracking_number'] = $data['WayBill'];
                $result['label_link']      = env('APP_URL').'/async_labels/'.$shipperAccount->id.'/'.$data['OrderNumber'];
                $result['shipment_id']     = $shipment->id;

                return $result;
            }


        }
        $result['errors'] = [json_encode($apiResponse['data'])];
        $shipment->save();


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
                    'Instructions' => preg_replace("/[^A-Za-z0-9 \-]/", '', $request->get('note')),
                ],

            ],
            'ShipmentDetails' => [
                'NumberOfPieces' => count($parcelsData),
                'Items'          => ['Item' => $items]
            ]
        ];


        if ($request->get('service_type') != '') {
            $params['ProductCode'] = $request->get('service_type');

            if ($params['ProductCode'] == 'MP16' or $params['ProductCode'] == 'CP16') {
                $params['ShipmentDetails']['NumberOfPieces'] = 1;

                $weight = $params['ShipmentDetails']['Items']['Item'][0]['Weight'];
                unset($params['ShipmentDetails']['Items']['Item']);
                $params['ShipmentDetails']['Items']['Item'][0]['Type']   = 'ALL';
                $params['ShipmentDetails']['Items']['Item'][0]['Weight'] = $weight;


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


            if ($productCode == '') {
                $productCode = 'ND16';

            }

            $params['ProductCode'] = $productCode;

        }


        if (preg_match('/^BT/', $postalCode)) {
            $components = preg_split('/\s/', $postalCode);
            $postalCode = 'RD1';
            if (count($components) == 2) {
                $number = preg_replace('/[^0-9]/', '', $components[0]);
                if ($number > 17) {
                    $postalCode = 'RD2';
                }
            }
            $params['Delivery']['PostalCode'] = $postalCode;
            $params['ProductCode']            = 'ROAD';
        }

        if (preg_match('/^(ZE|KW)/', $postalCode)) {
            $params['ProductCode'] = 'TDAY';
        }

        return $params;

    }

    function getLabel($labelID, $shipperAccount) {

        $headers = [
            "remote-user: Basic ".base64_encode($shipperAccount->credentials['email'].':'.$shipperAccount->credentials['password']),
            "Content-Type: application/json"
        ];

        $apiResponse = $this->callApi(
            $this->api_url.'Orders/'.$labelID.'.json', $headers, json_encode([]), 'GET'
        );

        return base64_decode($apiResponse['data']['Orders']['Order']['Label']['Content']);


    }

}

