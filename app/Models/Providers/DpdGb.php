<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Sat, 19 Sep 2020 13:23:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\Shipment;
use App\Models\ShipperAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;


/**
 * Class DpdGb
 *
 * @property string $slug
 * @property object $shipper
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DpdGb extends Shipper_Provider {

    protected $table = 'dpd_gb_shipper_providers';

    protected string $api_url = "https://api.dpd.co.uk/";

    protected $credentials_rules = [
        'username'       => ['required',],
        'password'       => ['required'],
        'account_number' => ['required'],
    ];

    public function createLabel(Shipment $shipment,Request $request, ShipperAccount $shipperAccount) {

        $debug=Arr::get($shipperAccount->data, 'debug') == 'Yes';


        if (Arr::get($shipperAccount->data, 'geoSession') == '' or (gmdate('U') - Arr::get($shipperAccount->data, 'geoSessionDate', 0) > 43200)) {
            $this->login($shipperAccount);
        }

        $headers = [
            "GeoSession: ".Arr::get($shipperAccount->data, 'geoSession'),
            "Content-Type: application/json",
            "Accept: application/json",
            'GeoClient: account/'.$shipperAccount->credentials['account_number']
        ];


        $params = $this->get_shipment_parameters($request, $shipperAccount);
        if ($debug) {
            $shipmentData=$shipment->data;
            data_fill($shipmentData,'debug.request',$params);
            $shipment->data=$shipmentData;
            $shipment->save();
        }

        $apiResponse = $this->call_api(
            $this->api_url.'shipping/shipment', $headers, json_encode($params)
        );

        dd($apiResponse);


    }


    function prepareShipment($shipperAccount, $request, $pickUp, $shipTo, $parcelsData, $cash_on_delivery) {


        $orderData=json_decode($request['order'],true);


       // dd($shipTo);

        //$parcelsData=json_decode($request['order'],true);


        $parcels=[];
        $packageNumber=1;
        foreach($parcelsData as $parcel){

            $items=[];
            foreach($parcel['items'] as $itemKey){
                $items[]=[
                    'productCode'=>Arr::get($orderData,"items.$itemKey.code"),
                    'countryOfOrigin'=>Arr::get($orderData,"items.$itemKey.origin_country_code"),
                    'numberOfItems'=>Arr::get($orderData,"items.$itemKey.qty"),
                    'productItemsDescription'=>Arr::get($orderData,"items.$itemKey.name"),
                    'productTypeDescription'=>Arr::get($orderData,"items.$itemKey.name"),
                    'unitValue'=>Arr::get($orderData,"items.$itemKey.price"),






                ];
            }

            $parcels[]=[
                'packageNumber'=>$packageNumber,
                'parcelProduct'=>$items
            ];

            $packageNumber++;
        }



        //print_r($shipperAccount->tenant->data);
        return [
            'jobId'=>null,
            'collectionOnDelivery'=>false,
            'generateCustomsData'=>'Y',
            'invoice' => [
                'invoiceShipperDetails' => [
                    'contactDetails'=>[
                        'contactName'=>Arr::get($shipperAccount->tenant->data,'contact'),
                        'telephone'=>Arr::get($shipperAccount->tenant->data,'phone'),
                    ],
                    'address'=>[
                        'organisation'=>Arr::get($shipperAccount->tenant->data,'organization'),
                        'countryCode'=>Arr::get($shipperAccount->tenant->data,'address.country_code'),
                        'street'=>Arr::get($shipperAccount->tenant->data,'address.address_line_1'),
                        'locality'=>Arr::get($shipperAccount->tenant->data,'address.dependent_locality'),
                        'town'=>Arr::get($shipperAccount->tenant->data,'address.locality'),
                        'county'=>Arr::get($shipperAccount->tenant->data,'address.administrative_area'),


                    ],
                    'vatNumber'=>Arr::get($shipperAccount->tenant->data,'tax_number'),
                ],
                'invoiceDeliveryDetails'=>[
                    'contactDetails'=>[
                        'contactName'=>Arr::get($shipTo,'contact'),
                        'telephone'=>Arr::get($shipTo,'phone'),
                    ],
                    'address'=>[
                        'organisation'=>Arr::get($shipTo,'organization'),
                        'countryCode'=>Arr::get($shipTo,'country_code'),
                        'street'=>Arr::get($shipTo,'address_line_1'),
                        'locality'=>Arr::get($shipTo,'dependent_locality'),
                        'town'=>Arr::get($shipTo,'locality'),
                        'county'=>Arr::get($shipTo,'administrative_area'),


                    ],
                    'vatNumber'=>Arr::get($shipTo,'tax_number'),
                ]

            ],
            'collectionDate'=>$pickUp['date'].'T'.$pickUp['ready'].':00',
            'consolidate'=>false,
            'consignment'=>[
                'consignmentNumber'=>null,
                'consignmentRef'=>null,
                'parcel'=>$parcels,
                'collectionDetails' => [
                    'contactDetails'=>[
                        'contactName'=>Arr::get($shipperAccount->tenant->data,'contact'),
                        'telephone'=>Arr::get($shipperAccount->tenant->data,'phone'),
                    ],
                    'address'=>[
                        'organisation'=>Arr::get($shipperAccount->tenant->data,'organization'),
                        'countryCode'=>Arr::get($shipperAccount->tenant->data,'address.country_code'),
                        'street'=>Arr::get($shipperAccount->tenant->data,'address.address_line_1'),
                        'locality'=>Arr::get($shipperAccount->tenant->data,'address.dependent_locality'),
                        'town'=>Arr::get($shipperAccount->tenant->data,'address.locality'),
                        'county'=>Arr::get($shipperAccount->tenant->data,'address.administrative_area'),


                    ],
                ],
                'deliveryDetails'=>[
                    'contactDetails'=>[
                        'contactName'=>Arr::get($shipTo,'contact'),
                        'telephone'=>Arr::get($shipTo,'phone'),
                    ],
                    'address'=>[
                        'organisation'=>Arr::get($shipTo,'organization'),
                        'countryCode'=>Arr::get($shipTo,'country_code'),
                        'street'=>Arr::get($shipTo,'address_line_1'),
                        'locality'=>Arr::get($shipTo,'dependent_locality'),
                        'town'=>Arr::get($shipTo,'locality'),
                        'county'=>Arr::get($shipTo,'administrative_area'),


                    ],
                    'notificationDetails'=>[
                        'email'=>Arr::get($shipTo,'email'),
                        'mobile'=>Arr::get($shipTo,'phone'),
                    ]

                ]

            ]
        ];


    }

    function login($shipperAccount) {

        $headers = [
            "Authorization: Basic ".base64_encode($shipperAccount->credentials['username'].':'.$shipperAccount->credentials['password']),
            "Content-Type: application/json",
            "Accept: application/json",
            'GeoClient: account/'.$shipperAccount->credentials['account_number']
        ];


        $params = [];


        $apiResponse = $this->call_api(
            $this->api_url.'user?action=login', $headers, json_encode($params)
        );

        if ($apiResponse['status'] == 200 and !empty($apiResponse['data']['data']['geoSession'])) {
            $shippingAccountData                   = $shipperAccount->data;
            $shippingAccountData['geoSession']     = $apiResponse['data']['data']['geoSession'];
            $shippingAccountData['geoSessionDate'] = gmdate('U');
            $shipperAccount->data                  = $shippingAccountData;
            $shipperAccount->save();

        }


    }


}
