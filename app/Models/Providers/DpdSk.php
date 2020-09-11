<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Fri, 04 Sep 2020 17:07:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\Country;
use App\Models\ShipperAccount;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Carbon\Carbon;


/**
 * Class DpdSk
 *
 * @property string $slug
 * @property object $shipper
 * @property array  $credentials
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DpdSk extends Shipper_Provider {


    protected $table = 'dpd_sk_shipper_providers';

    protected $api_url ="https://api.dpdportal.sk/shipment";

    protected $credentials_rules = [
        'apiKey'   => ['required'],
        'username' => [
            'required',
            'email'
        ],
        'delisId'  => ['required'],
        'pickupID' => ['required'],
        'bankID'   => [],

    ];



    public function createLabel(Request $request, ShipperAccount $shipperAccount) {

        $params = array(
            'jsonrpc' => '2.0',
            'method'  => 'create',
            'params'  => array(
                'DPDSecurity' => array(
                    'SecurityToken' => array(
                        'ClientKey' => $shipperAccount->credentials['apiKey'],
                        'Email'     => $shipperAccount->credentials['username'],
                    ),
                ),
                'shipment'    => [$this->get_shipment_parameters($request,$shipperAccount)],
            ),
            'id'      => 'null',
        );




        $apiResponse = $this->call_api($this->api_url, $params);



        $result=[];
        if (!empty($apiResponse['data']['error'])) {

            if ($apiResponse['data']['error']['code'] == 103) {
                $result['errors'][] = ['authentication' => $apiResponse['data']['error']['message']];
                $result['status']   = 401;
            } elseif ($apiResponse['data']['error']['code'] == 401 or $apiResponse['data']['error']['code'] == 519) {

                $msg = $apiResponse['data']['error']['message'];


                if (isset($apiResponse['data']['error']['data']['additional_details'])) {
                    $msg .= ' '.$apiResponse['data']['error']['data']['additional_details'];
                }

                $result['errors'][] = ['invalid' => $msg];
                $result['status']   = 422;
            }


        } else {

            $res = array_pop($apiResponse['data']['result'])[0];
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

     function prepareShipment( $shipperAccount,$request, $pickUp, $shipTo, $parcels, $cash_on_delivery) {

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
                        'id' => $shipperAccount->credentials['bankID'],
                    ],
                    'variableSymbol' => $order_id,
                    'paymentMethod'  => $paymentMethod,
                ]
            ];
        }


        return array(
            'reference'        => $request->get('reference'),
            'delisId'          => $shipperAccount->credentials['delisId'],
            'note'             => $request->get('note'),
            'product'          => $request->get('shipping_product', 1),
            'pickup'           => array(
                'date'       => $pickup_date->format('Ymd'),
                'timeWindow' => $pickUpTimeWindow
            ),
            'addressSender'    => array(
                'id' => $shipperAccount->credentials['pickupID'],
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

/*
 *
 *   return [
            'ClientNumber'    => $this->credentials['client_number'],
            'ClientReference' => $request->get('reference'),
            'CODAmount'       => 0,
            'CODReference'    => $request->get('reference'),
            'Content'         => 'CONTENT',
            'Count'           => 1,
            'DeliveryAddress' => array(
                'City'           => Arr::get($shipTo, 'locality'),
                'ContactEmail'   => Arr::get($shipTo, 'email'),
                'ContactName'    => Arr::get($shipTo, 'contact'),
                'ContactPhone'   => Arr::get($shipTo, 'phone','+36701234567'),
                'CountryIsoCode' => Arr::get($shipTo, 'country_code'),
                'HouseNumber'     => '1',
                'Name'           => Arr::get($shipTo, 'organization', Arr::get($shipTo, 'contact')),
                'Street'         => trim(Arr::get($shipTo, 'address_line_1').' '.Arr::get($shipTo, 'address_line_2')),
                'ZipCode'        => trim(Arr::get($shipTo, 'sorting_code').' '.Arr::get($shipTo, 'postal_code')),
                 'HouseNumberInfo' => '1',
            ),
            'PickupAddress'   => array(
                'City'            => 'Alsónémedi',
                'ContactEmail'    => 'something@anything.hu',
                'ContactName'     => 'Contact Name',
                'ContactPhone'    => '+36701234567',
                'CountryIsoCode'  => 'HU',
                'HouseNumber'     => '2',
                'Name'            => 'Pickup Address',
                'Street'          => 'Európa u.',
                'ZipCode'         => '2351',
                'HouseNumberInfo' => '/a',
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
 */
