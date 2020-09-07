<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Fri, 04 Sep 2020 22:20:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Http\Controllers;

use App\Models\ShipperAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class LabelController extends Controller {


    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request) {


        $validator = Validator::make(
            $request->all(), [
                               'shipper_account_id' => [
                                   'required',
                                   'exists:shipper_accounts,id',
                               ],
                               'reference'          => [
                                   'required'
                               ],
                               'parcels'            => [
                                   'required',
                                   'json'
                               ],
                               'ship_to'            => [
                                   'required',
                                   'json'
                               ],
                               'pick_up'            => [
                                   'required',
                                   'json'
                               ]

                           ]
        );


        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $parcels = json_decode($request->get('parcels'), true);
        $shipTo  = json_decode($request->get('ship_to'), true);
        $pick_up  = json_decode($request->get('pick_up'), true);


        $validator = Validator::make(
            [
                'parcels' => $parcels,
                'shipTo'  => $shipTo,
                'pick_up'  => $pick_up

            ], [
                'parcels.*.reference' => [
                    'sometimes',
                    'required',
                    'numeric'
                ],

                'parcels.*.weight' => [
                    'required',
                    'numeric'
                ],
                'parcels.*.height' => [
                    'required',
                    'numeric'
                ],
                'parcels.*.width'  => [
                    'required',
                    'numeric'
                ],
                'parcels.*.depth'  => [
                    'required',
                    'numeric'
                ],
                'shipTo.country_code'  => [
                    'required','exists:countries,code'
                ],
                'pick_up.date'  => [
                    'sometimes','required','after_or_equal:today'
                ],
                'pick_up.start'  => [
                    'sometimes','required','date_format:H:i'
                ],
                'pick_up.end'  => [
                    'sometimes','required','date_format:H:i'
                ],

            ]
        );


        //'pickup_date'            => [
        //    'required','after_or_equal:today'
        //],


        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }




        /**
         * @var $shipper_account \App\Models\ShipperAccount
         */
        $shipper_account = (new ShipperAccount)->find($request->get('shipper_account_id'))->first();

        $response=$shipper_account->createLabel($request);

        $status=$response['status'];
        unset($response['status']);

        if(count($response['errors'])>0){
            return response()->json(['errors' =>$response['errors']], $status);

        }
        unset($response['errors']);
        return response()->json($response,$status);



    }


}