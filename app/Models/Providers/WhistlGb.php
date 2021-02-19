<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Sat, 19 Sep 2020 13:23:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\PdfLabel;
use App\Models\Shipment;
use App\Models\ShipperAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\ArrayToXml\ArrayToXml;
use Exception;


/**
 * Class DpdGb
 *
 * @property string $slug
 * @property object $shipper
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class WhistlGb extends ShipperProvider {

    protected $table = 'whistl_gb_shipper_providers';

    protected string $api_url = "https://api.parcelhub.net/1.0/";

    protected $credentials_rules = [
        'username' => ['required',],
        'password' => ['required'],
    ];


    function createLabel(Shipment $shipment, Request $request, ShipperAccount $shipperAccount) {

        $debug = Arr::get($shipperAccount->data, 'debug') == 'Yes';

        $params = $this->getShipmentParameters($request, $shipperAccount);

        $number_boxes = count(Arr::get($params, 'Packages', []));
        if ($number_boxes == 0) {
            $number_boxes = null;
        }
        $shipment->boxes = $number_boxes;

        if ($debug) {
            $shipmentData = $shipment->data;
            data_fill($shipmentData, 'debug.request', $params);
            $shipment->data = $shipmentData;
        }
        $shipment->save();


        $apiResponse = $this->callApi(
            $this->api_url.'Shipment?RequestedLabelFormat=PDF&RequestedLabelSize=6', $this->getHeaders($shipperAccount), $this->preprocess_parameters(
            'Shipment', [
            'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
            'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
            'xmlns'     => 'http://api.parcelhub.net/schemas/api/parcelhub-api-v0.4.xsd'
        ], $params
        ), 'POST', 'xml'
        );


        $response = $apiResponse['data'];


        if ($debug) {
            $shipmentData = $shipment->data;
            data_fill($shipmentData, 'debug.response', $response);
            $shipment->data = $shipmentData;
            $shipment->save();
        }
        $result = [
            'shipment_id' => $shipment->id
        ];

        if ($apiResponse['status'] == 200) {

            $shipment->status = 'success';

            $pdfChecksum   = '';
            $number_labels = 0;
            foreach ($response['Packages'] as $package_index => $package) {
                foreach ($package['PackageShippingInfo']['Labels'] as $label_index => $label) {
                    $number_labels++;
                    $pdfData     = $label['LabelData'];
                    $pdfChecksum = md5($pdfData);
                    $pdfLabel    = new PdfLabel(
                        [
                            'checksum' => $pdfChecksum,
                            'data'     => $pdfData
                        ]
                    );
                    $shipment->pdfLabel()->save($pdfLabel);
                }
            }


            $result['tracking_number'] = Arr::get($apiResponse, 'data.ShippingInfo.CourierTrackingNumber').'001';
            //$result['tracking_number'] = Arr::get($apiResponse, 'data.ShippingInfo.CourierTrackingNumber');
            $result['label_link'] = env('APP_URL').'/labels/'.$pdfChecksum;

            $error_shipments = json_decode($request->get('error_shipments', '[]'));
            if (is_array($error_shipments) and count($error_shipments) > 0) {
                (new Shipment)->wherein('id', $error_shipments)->update(['status' => 'fixed']);
            }


        } else {
            $shipment->status = 'error';


            $msg = 'Unknown error';
            try {
                $msg = $response['Message'];
            } catch (Exception $e) {
                //
            }
            $shipment->error_message = $msg;
            $result['error_message'] = $msg;

            $result['errors'] = [json_encode($response)];
            $result['status'] = 599;
        }


        $shipment->save();

        return $result;


    }

    /**
     * @param $shipperAccount
     * @param $request
     * @param $pickUp
     * @param $shipTo
     * @param $parcels
     * @param $order
     *
     * @return array
     */
    function prepareShipment($shipperAccount, $request, $pickUp, $shipTo, $parcels, $order) {


        $packages = [];
        foreach ($parcels as $parcel) {


            $packages[] = [
                'Package' => [
                    'Dimensions' => [
                        'Length' => max(floor(Arr::get($parcel, 'depth')), 1),
                        'Width'  => max(floor(Arr::get($parcel, 'width')), 1),
                        'Height' => max(floor(Arr::get($parcel, 'height')), 1),
                    ],
                    'Weight'     => max(Arr::get($parcel, 'weight'), 0.001),
                    'Contents'   => 'Goods'
                ]
            ];
        }

        $serviceInfo = json_decode($request->get('service_type'), true);


        $shipTo = array_filter($shipTo);

        return [
            'Account'             => Arr::get($shipperAccount->credentials, 'account'),
            'CollectionDetails'   => [
                'CollectionDate'      => $pickUp['date'],
                'CollectionReadyTime' => $pickUp['ready'].':00',
            ],
            'DeliveryAddress'     => [
                'ContactName' => Arr::get($shipTo, 'contact', 'Householder'),
                'CompanyName' => Arr::get($shipTo, 'organization'),
                'Email'       => Arr::get($shipTo, 'email'),
                'Phone'       => trim(Arr::get($shipTo, 'phone')),
                'Address1'    => Arr::get($shipTo, 'address_line_1'),
                'Address2'    => Arr::get($shipTo, 'address_line_2'),
                'City'        => Arr::get($shipTo, 'locality'),
                'Area'        => Arr::get($shipTo, 'administrative_area'),
                'Postcode'    => Arr::get($shipTo, 'postal_code'),
                'Country'     => Arr::get($shipTo, 'country_code'),
                'AddressType' => 'Business'


            ],
            'Reference1'          => $request->get('reference'),
            'Reference2'          => $request->get('reference2'),
            'SpecialInstructions' => Str::limit(strip_tags($request->get('note')), 35, ''),
            'ContentsDescription' => 'Goods',
            'Packages'            => $packages,
            'ServiceInfo'         => $serviceInfo


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


        $apiResponse = $this->callApi(
            $this->api_url.'TokenV2', $headers, $this->preprocess_parameters(
            'RequestToken', [
            'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
            'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',

        ], $params
        ), 'POST', 'xml'


        );


        if ($apiResponse['status'] == 200 and !empty($apiResponse['data']['access_token'])) {
            $shippingAccountData                 = $shipperAccount->data;
            $shippingAccountData['refreshToken'] = $apiResponse['data']['refreshToken'];
            $shippingAccountData['accessToken']  = $apiResponse['data']['access_token'];
            $shippingAccountData['expiresAt']    = gmdate('U') + $apiResponse['data']['expiresIn'];

            $shipperAccount->data = $shippingAccountData;
            $shipperAccount->save();

        }


    }


    private function preprocess_parameters($rootElement, $attributes, $params) {

        return ArrayToXml::convert(
            $params, [
            'rootElementName' => $rootElement,
            '_attributes'     => $attributes,
        ], true, 'UTF-8'
        );

    }

    function getServices($request, $shipperAccount) {


        $apiResponse = $this->callApi(
            $this->api_url.'Service/?AccountId='.Arr::get($shipperAccount->credentials, 'account'), $this->getHeaders($shipperAccount), '{}', 'GET', 'xml'
        );

        $services = [];
        if ($apiResponse['status'] == 200) {

            foreach ($apiResponse['data']['Service'] as $serviceData) {
                $services[] = [
                    'id'   => [
                        $serviceData['ServiceIds']['ServiceId'],
                        $serviceData['ServiceIds']['ServiceCustomerUID'],
                        $serviceData['ServiceIds']['ServiceProviderId'],

                    ],
                    'code' => $serviceData['ServiceName'],
                    'name' => $serviceData['ServiceDesc'],
                    'type' => '',
                    'data' => $serviceData


                ];
            }
        }


        return ['services' => $services];


    }

    private function getHeaders($shipperAccount) {
        if (Arr::get($shipperAccount->data, 'accessToken') == '' or (Arr::get($shipperAccount->data, 'expiresAt', gmdate('U')) - gmdate('U') < 1800)) {
            $this->login($shipperAccount);
        }


        return [
            "Content-Type: application/xml; charset=utf-8",
            "Accept: */*",
            "Authorization: bearer ".Arr::get($shipperAccount->data, 'accessToken')
        ];

    }

}
