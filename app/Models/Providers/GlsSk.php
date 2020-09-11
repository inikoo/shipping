<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 02 Sep 2020 18:39:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\PdfLabel;
use App\Models\ShipperAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Class GlsSk
 *
 * @property string $slug
 * @property object $shipper
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class GlsSk extends Shipper_Provider {

    protected $table = 'gls_sk_shipper_providers';

    protected $api_url = 'https://api.test.mygls.hu/ParcelService.svc/json/';


    protected $credentials_rules = [
        'username'      => [
            'required',
            'email'
        ],
        'password'      => ['required'],
        'client_number' => [
            'required',
            'numeric'
        ],

    ];

    public function createLabel(Request $request, ShipperAccount $shipperAccount) {


        $params = [
            'Username'   => $shipperAccount->credentials['username'],
            'Password'   => array_values(unpack('C*', hex2bin($shipperAccount->credentials['password']))),
            'ParcelList' => [
                $this->get_shipment_parameters($request, $shipperAccount)
            ]
        ];

        print_r($this->get_shipment_parameters($request, $shipperAccount));

        $apiResponse = $this->call_api($this->api_url.'PrintLabels', $params);


        $result = [];
        if (count($apiResponse['data']['PrintLabelsErrorList']) > 0) {
            $result['errors'] = $apiResponse['data']['PrintLabelsErrorList'];
        } elseif (count($apiResponse['data']['Labels']) > 0) {

            $pdfData     = implode(array_map('chr', $apiResponse['data']['Labels']));
            $pdfChecksum = md5($pdfData);
            $pdfLabel    = new PdfLabel(
                [
                    'checksum' => $pdfChecksum,
                    'data'     => base64_encode($pdfData)
                ]
            );
            $shipperAccount->pdf_labels()->save($pdfLabel);

            $result['tracking_number'] = $apiResponse['data']['PrintLabelsInfoList'][0]['ParcelNumber'];
            $result['shipment_id']     = $apiResponse['data']['PrintLabelsInfoList'][0]['ParcelId'];
            $result['label_link']      = env('APP_URL').'/labels/'.$pdfChecksum;

        }


        return $result;


    }


    function prepareShipment($shipperAccount, $request, $pickUp, $shipTo, $parcels, $cash_on_delivery) {

        // Still in testing

        $tenant = $shipperAccount->tenant;

        $shipTo = array_filter($shipTo);

        $pickupDate = "/Date(".(strtotime(Arr::get($pickUp, 'date')." 23:59:59") * 1000).")/";

        return [
            'ClientNumber'    => $shipperAccount->credentials['client_number'],
            'ClientReference' => $request->get('reference'),
            'CODAmount'       => 0,
            'CODReference'    => 'COD REFERENCE',
            'Content'         => 'CONTENT',
            'Count'           => 1,
            'DeliveryAddress' => array(
                'City'            => Arr::get($shipTo, 'locality'),
                'ContactEmail'    => Arr::get($shipTo, 'email'),
                'ContactName'     => Arr::get($shipTo, 'contact'),
                //  'ContactPhone'   => Arr::get($shipTo, 'phone',Arr::get($tenant->data,'phone')),
               'ContactPhone'    => '+36701234567',
                'CountryIsoCode'  => Arr::get($shipTo, 'country_code'),
                'HouseNumber'     => '',
                'Name'            => Arr::get($shipTo, 'organization', Arr::get($shipTo, 'contact')),
                'Street'          => trim(Arr::get($shipTo, 'address_line_1').' '.Arr::get($shipTo, 'address_line_2')),
                'ZipCode'         => trim(Arr::get($shipTo, 'sorting_code').' '.Arr::get($shipTo, 'postal_code')),
                'HouseNumberInfo' => '',
            ),
            'PickupAddress'   => array(
                'City'            => Arr::get($tenant->data['address'], 'locality'),
                'ContactEmail'    => Arr::get($tenant->data, 'email'),
                'ContactName'     => Arr::get($tenant->data, 'contact'),
                'ContactPhone'    => Arr::get($tenant->data, 'phone'),


                // 'CountryIsoCode'            => Arr::get($tenant->data['address'], 'country_code'),
                'CountryIsoCode'  => 'HU',
                'HouseNumber'     => '',
                'Name'            => 'Pickup Address',
                'Street'          => trim(Arr::get($tenant->data['address'], 'address_line_1').' '.Arr::get($tenant->data['address'], 'address_line_2')),
                //'ZipCode'         => trim(Arr::get($tenant->data['address'], 'sorting_code').' '.Arr::get($tenant->data['address'], 'postal_code')),
                'ZipCode'         => '2351',

                'HouseNumberInfo' => '',
            ),
            'PickupDate'      => $pickupDate,
            'ServiceList'     => array(
                0 => array(
                    'Code'         => 'PSD',
                    'PSDParameter' => array(
                        'StringValue' => '2351-CSOMAGPONT',
                    ),
                ),
            ),

        ];

    }


}
