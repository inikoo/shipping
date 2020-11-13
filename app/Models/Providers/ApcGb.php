<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Tue, 15 Sep 2020 12:45:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\Country;
use App\Models\Event;
use App\Models\Shipment;
use App\Models\ShipperAccount;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;


/**
 * Class ApcGb
 *
 * @property string $slug
 * @property object $shipper
 * @property array  $credentials
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class ApcGb extends ShipperProvider {


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

        $debug = Arr::get($shipperAccount->data, 'debug') == 'Yes';

        $params = array(
            'Orders' => [
                'Order' => $this->getShipmentParameters($request, $shipperAccount)
            ]
        );

        $shipment->boxes = Arr::get($params, 'Orders.Order.ShipmentDetails.NumberOfPieces', null);

        if ($debug) {
            $shipmentData = $shipment->data;
            data_fill($shipmentData, 'debug.request', $params);
            $shipment->data = $shipmentData;
        }
        $shipment->save();

        $apiResponse = $this->callApi(
            $this->api_url.'Orders.json', $this->getHeaders($shipperAccount), json_encode($params)
        );


        if ($debug) {
            $shipmentData = $shipment->data;
            data_fill($shipmentData, 'debug.response', $apiResponse['data']);
            $shipment->data = $shipmentData;
        }


        $shipment->status = 'error';


        $result = [
            'shipment_id' => $shipment->id
        ];


        if ($apiResponse['status'] == 200) {
            if ($apiResponse['data']['Orders']['Messages']['Code'] == 'SUCCESS') {

                $data            = $apiResponse['data']['Orders']['Order'];
                $tracking_number = $data['WayBill'];

                $shipment->status   = 'success';
                $shipment->tracking = $tracking_number;

                $result['tracking_number'] = $tracking_number;
                $result['label_link']      = env('APP_URL').'/async_labels/'.$shipperAccount->id.'/'.$data['OrderNumber'];
                $shipment->save();

                $error_shipments = json_decode($request->get('error_shipments', '[]'));
                if (is_array($error_shipments) and count($error_shipments) > 0) {
                    (new Shipment)->wherein('id', $error_shipments)->update(['status' => 'fixed']);
                }

                return $result;
            }


        }


        $msg = 'Unknown error';
        try {
            $messages = $apiResponse['data']['Orders']['Order']['Messages'];


            $msg = Arr::get($messages, 'Description', 'Unknown error');

            if (isset($messages['ErrorFields'])) {
                $msg = '';
                foreach ($messages['ErrorFields'] as $error) {
                    if ($error['FieldName'] == 'Delivery PostalCode') {
                        $msg .= 'Invalid postcode, ';
                    } else {
                        $msg .= $error['FieldName'].' '.$error['ErrorMessage'].', ';
                    }
                }
                $msg = preg_replace('/, $/', '', $msg);
            }

        } catch (Exception $e) {
            //
        }
        $shipment->error_message = $msg;


        $result['error_message'] = $msg;
        $result['errors']        = [json_encode($apiResponse['data'])];
        $result['status']        = 599;


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
            'Reference'       => Str::limit($request->get('reference'), 30),
            'Delivery'        => [
                'CompanyName'  => Str::limit($name, 30),
                'AddressLine1' => Str::limit(Arr::get($shipTo, 'address_line_1'), 60),
                'AddressLine2' => Str::limit($address2, 60),
                'PostalCode'   => $postalCode,
                'City'         => Str::limit(Arr::get($shipTo, 'locality'), 31, ''),
                'County'       => Str::limit(Arr::get($shipTo, 'administrative_area'), 31, ''),
                'CountryCode'  => $country->code,
                'Contact'      => [
                    'PersonName'  => Str::limit(Arr::get($shipTo, 'contact'), 60),
                    'PhoneNumber' => Str::limit(Arr::get($shipTo, 'phone'), 15, ''),
                    'Email'       => Arr::get($shipTo, 'email'),
                ],
                'Instructions' => Str::limit(preg_replace("/[^A-Za-z0-9 \-]/", '', strip_tags($request->get('note'))), 60),


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


            //AB51 8US. now allow TDAY code,
            if (!preg_match('/^BT51/', $postalCode)) {
                if (preg_match('/^((JE|GG|IM|KW|HS|ZE|IV)\d+)|AB(30|33|34|35|36|37|38)|AB[4-5][0-9]|DD[89]|FK(16|17|18|19|20|21)|PA[2-8][0-9]|PH((15|16|17|18|19)|[2-5][0-9])|KA(27|28)/', $postalCode)) {
                    $params['ProductCode'] = 'TDAY';
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


        return $params;

    }

    function getLabel($labelID, $shipperAccount, $output = '') {

        $apiResponse = $this->callApi(
            $this->api_url.'Orders/'.$labelID.'.json', $this->getHeaders($shipperAccount), json_encode([]), 'GET'
        );

        return base64_decode($apiResponse['data']['Orders']['Order']['Label']['Content']);


    }

    function track($shipment) {


        if (!$shipment->tracking) {
            return false;
        }

        $apiResponse = $this->callApi(
            $this->api_url.'Tracks/'.$shipment->tracking.'.json?searchtype=CarrierWaybill&history=Yes', $this->getHeaders($shipment->shipperAccount), "[]", 'GET'
        );


        $boxes = Arr::get($apiResponse, 'data.Tracks.Track.ShipmentDetails.Items.0.Item');

        if ($boxes != null) {
            if (array_keys($boxes) !== range(0, count($boxes) - 1)) {
                $this->track_box($shipment, $boxes);
            } else {
                foreach ($boxes as $box) {
                    $this->track_box($shipment, $box);
                }
            }

            $shipment->update_state();
        }

        return true;
    }


    private function save_event($eventData, $boxID, $shipment) {


        try {
            $date = Carbon::createFromFormat('d/m/Y H:i:s', Arr::pull($eventData, 'DateTime'), 'Europe/London');
            $date->setTimezone('UTC');

        } catch (Exception $e) {
            return false;
        }


        $code   = Str::of(strtolower(Arr::get($eventData, 'StatusDescription')))->snake();
        $state  = null;
        $status = null;
        switch ($code) {

            case 'ready_to_print':
                $state = 100;
                break;
            case 'label_printed/_done':
                $code  = 'label_printed';
                $state = 100;
                break;
            case 'manifested':
            case 'at_hub':
            case 'at_depot':
            case 'scan':
            case 'not_received_in_depot':
                $state = 200;
                break;
            case 'at_delivery_depot':
            case 'at_sending_depot':
                $code  = 'at_delivery_depot';
                $state = 200;
                break;
            case 'problem-_not_attempted':
                $code  = 'problem_not_attempted';
                $state = 200;
                break;
            case 'out_for_delivery':
                $state = 300;
                break;
            case 'closed/_carded':
                $code  = 'closed_carded';
                $state = 400;
                break;
            case 'not_received_on_trunk':
            case 'held_at_depot':
            case 'check_address':
                $state = 400;
                break;
            case 'updated/resolved':
                $code  = 'updated_resolved';
                $state = 400;
                break;

            case 'delivered':
            case 'customer_refused':
            case 'left_with_neightbour':
            case 'collected_from_depot':
            case 'left_as_instructed':
            case 'return_to_sender':
                $state = 500;
                break;
            case 'cancelled':
                $state = 0;
            default:

        }

        switch (Arr::pull($eventData, 'StatusColor')) {
            case 'green':
                $status = 300;
                break;
            case 'orange':
                $status = 200;
                break;
            case 'red':
                $status = 100;
                break;
        }


        $eventData = array_filter($eventData);

        $event = (new Event)->firstOrCreate(
            [
                'date'        => $date->format('Y-m-d H:i:s'),
                'box'         => $boxID,
                'code'        => $code,
                'shipment_id' => $shipment->id
            ], [
                'state'  => $state,
                'status' => $status,
                'data'   => $eventData
            ]
        );

        return $event->id;

    }

    private function track_box($shipment, $box) {

        $boxID = $box['TrackingNumber'];
        if (array_keys($box['Activity']) !== range(0, count($box['Activity']) - 1)) {
            $this->save_event($box['Activity']['Status'], $boxID, $shipment);
        } else {
            foreach ($box['Activity'] as $eventData) {
                $this->save_event($eventData['Status'], $boxID, $shipment);
            }
        }

    }

    private function getHeaders($shipperAccount) {
        return [
            "remote-user: Basic ".base64_encode($shipperAccount->credentials['email'].':'.$shipperAccount->credentials['password']),
            "Content-Type: application/json"
        ];

    }

}

