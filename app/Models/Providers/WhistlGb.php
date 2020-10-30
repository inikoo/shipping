<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Sat, 19 Sep 2020 13:23:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\ShipperAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Spatie\ArrayToXml\ArrayToXml;


/**
 * Class DpdGb
 *
 * @property string $slug
 * @property object $shipper
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class WhistlGb extends Shipper_Provider {

    protected $table = 'whistl_gb_shipper_providers';

    protected string $api_url = "https://api.test.parcelhub.net/1.0/";

    protected $credentials_rules = [
        'username' => ['required',],
        'password' => ['required'],
    ];

    function createLabel(Request $request, ShipperAccount $shipperAccount) {


        if (Arr::get($shipperAccount->data, 'accessToken') == '' or (gmdate('U') - Arr::get($shipperAccount->data, 'expiresAt', 0) >=   36000)) {
            $this->login($shipperAccount);
        }
        exit;

        $headers = [
            "GeoSession: ".Arr::get($shipperAccount->data, 'geoSession'),
            "Content-Type: application/json",
            "Accept: application/json",
            'GeoClient: account/'.$shipperAccount->credentials['account_number']
        ];


        $params = $this->get_shipment_parameters($request, $shipperAccount);
        //dd($params);

        $apiResponse = $this->call_api(
            $this->api_url.'shipping/shipment', $headers, json_encode($params)
        );

        dd($apiResponse);


    }

    function prepareShipment($shipperAccount, $request, $pickUp, $shipTo, $parcelsData, $cash_on_delivery) {


        $orderData = json_decode($request['order'], true);


        // dd($shipTo);

        //$parcelsData=json_decode($request['order'],true);


        $parcels       = [];
        $packageNumber = 1;
        foreach ($parcelsData as $parcel) {

            $items = [];
            foreach ($parcel['items'] as $itemKey) {
                $items[] = [
                    'productCode'             => Arr::get($orderData, "items.$itemKey.code"),
                    'countryOfOrigin'         => Arr::get($orderData, "items.$itemKey.origin_country_code"),
                    'numberOfItems'           => Arr::get($orderData, "items.$itemKey.qty"),
                    'productItemsDescription' => Arr::get($orderData, "items.$itemKey.name"),
                    'productTypeDescription'  => Arr::get($orderData, "items.$itemKey.name"),
                    'unitValue'               => Arr::get($orderData, "items.$itemKey.price"),


                ];
            }

            $parcels[] = [
                'packageNumber' => $packageNumber,
                'parcelProduct' => $items
            ];

            $packageNumber++;
        }


        //print_r($shipperAccount->tenant->data);
        return [
            'jobId'                => null,
            'collectionOnDelivery' => false,
            'generateCustomsData'  => 'Y',
            'invoice'              => [
                'invoiceShipperDetails'  => [
                    'contactDetails' => [
                        'contactName' => Arr::get($shipperAccount->tenant->data, 'contact'),
                        'telephone'   => Arr::get($shipperAccount->tenant->data, 'phone'),
                    ],
                    'address'        => [
                        'organisation' => Arr::get($shipperAccount->tenant->data, 'organization'),
                        'countryCode'  => Arr::get($shipperAccount->tenant->data, 'address.country_code'),
                        'street'       => Arr::get($shipperAccount->tenant->data, 'address.address_line_1'),
                        'locality'     => Arr::get($shipperAccount->tenant->data, 'address.dependent_locality'),
                        'town'         => Arr::get($shipperAccount->tenant->data, 'address.locality'),
                        'county'       => Arr::get($shipperAccount->tenant->data, 'address.administrative_area'),


                    ],
                    'vatNumber'      => Arr::get($shipperAccount->tenant->data, 'tax_number'),
                ],
                'invoiceDeliveryDetails' => [
                    'contactDetails' => [
                        'contactName' => Arr::get($shipTo, 'contact'),
                        'telephone'   => Arr::get($shipTo, 'phone'),
                    ],
                    'address'        => [
                        'organisation' => Arr::get($shipTo, 'organization'),
                        'countryCode'  => Arr::get($shipTo, 'country_code'),
                        'street'       => Arr::get($shipTo, 'address_line_1'),
                        'locality'     => Arr::get($shipTo, 'dependent_locality'),
                        'town'         => Arr::get($shipTo, 'locality'),
                        'county'       => Arr::get($shipTo, 'administrative_area'),


                    ],
                    'vatNumber'      => Arr::get($shipTo, 'tax_number'),
                ]

            ],
            'collectionDate'       => $pickUp['date'].'T'.$pickUp['ready'].':00',
            'consolidate'          => false,
            'consignment'          => [
                'consignmentNumber' => null,
                'consignmentRef'    => null,
                'parcel'            => $parcels,
                'collectionDetails' => [
                    'contactDetails' => [
                        'contactName' => Arr::get($shipperAccount->tenant->data, 'contact'),
                        'telephone'   => Arr::get($shipperAccount->tenant->data, 'phone'),
                    ],
                    'address'        => [
                        'organisation' => Arr::get($shipperAccount->tenant->data, 'organization'),
                        'countryCode'  => Arr::get($shipperAccount->tenant->data, 'address.country_code'),
                        'street'       => Arr::get($shipperAccount->tenant->data, 'address.address_line_1'),
                        'locality'     => Arr::get($shipperAccount->tenant->data, 'address.dependent_locality'),
                        'town'         => Arr::get($shipperAccount->tenant->data, 'address.locality'),
                        'county'       => Arr::get($shipperAccount->tenant->data, 'address.administrative_area'),


                    ],
                ],
                'deliveryDetails'   => [
                    'contactDetails'      => [
                        'contactName' => Arr::get($shipTo, 'contact'),
                        'telephone'   => Arr::get($shipTo, 'phone'),
                    ],
                    'address'             => [
                        'organisation' => Arr::get($shipTo, 'organization'),
                        'countryCode'  => Arr::get($shipTo, 'country_code'),
                        'street'       => Arr::get($shipTo, 'address_line_1'),
                        'locality'     => Arr::get($shipTo, 'dependent_locality'),
                        'town'         => Arr::get($shipTo, 'locality'),
                        'county'       => Arr::get($shipTo, 'administrative_area'),


                    ],
                    'notificationDetails' => [
                        'email'  => Arr::get($shipTo, 'email'),
                        'mobile' => Arr::get($shipTo, 'phone'),
                    ]

                ]

            ]
        ];


    }

    function login($shipperAccount) {

        $headers = [
            "Content-Type: application/xml; charset=utf-8",
            "Accept: */*",
        ];

        $params = [
            'grant_type' => 'bearer',
            'username'   => Arr::get($shipperAccount->credentials, 'username'),
            'password'   => Arr::get($shipperAccount->credentials, 'password')

        ];


        $apiResponse = $this->call_api(
            $this->api_url.'TokenV2', $headers, $this->preprocess_parameters($params), 'POST', 'xml'


        );


        print_r($apiResponse);

        if ($apiResponse['status'] == 200 and !empty($apiResponse['data']['access_token'])) {
            $shippingAccountData                 = $shipperAccount->data;
            $shippingAccountData['refreshToken'] = $apiResponse['data']['refreshToken'];
            $shippingAccountData['accessToken']  = $apiResponse['data']['access_token'];
            $shippingAccountData['expiresAt']   = gmdate('U') + $apiResponse['data']['expiresIn'];

            $shipperAccount->data = $shippingAccountData;
            $shipperAccount->save();

        }


    }


    private function preprocess_parameters($params) {

        return ArrayToXml::convert(
            $params, [
            'rootElementName' => 'RequestToken',
            '_attributes'     => [
                'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',

            ],
        ], true, 'UTF-8'
        );

    }


}
