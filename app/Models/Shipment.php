<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Sat, 31 Oct 2020 15:23:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;


/**
 * Class Shipment
 *
 * @property integer        $id
 * @property integer        $shipper_account_id
 * @property string         $status
 * @property string         $reference
 * @property string         $tracking
 * @property string         $error_message
 * @property array          $data
 * @property integer        $boxes
 * @property integer        $min_state
 * @property integer        $max_state
 * @property ShipperAccount $shipperAccount
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Shipment extends Model {

    protected $casts = [
        'data' => 'array'
    ];

    protected $attributes = [
        'data' => '{}'
    ];

    protected $guarded = [];


    public function shipperAccount() {
        return $this->belongsTo('App\Models\ShipperAccount');
    }


    public function pdfLabel() {
        return $this->hasOne('App\Models\PdfLabel');
    }

    public function track() {

        if (!$this->tracking) {
            return;
        }

        if ($this->shipperAccount->track($this)) {
            $this->update(
                [
                    'tracked_count' => DB::raw('tracked_count+1'),
                    'tracked_at'    => Carbon::now()
                ]
            );
        }


    }

    function update_state() {

        $rows = DB::table('events')->select(DB::raw('count(*) as num, max(state) as state'))->where('shipment_id', '=', $this->id)->groupBy('box')->get();

        $min_state = null;
        $max_state = null;
        foreach ($rows as $row) {
            $min_state = ($min_state == null ? $row->state : min($min_state, $row->state));
            $max_state = ($max_state == null ? $row->state : max($max_state, $row->state));
        }

        $this->min_state = $min_state;
        $this->max_state = $max_state;
        $this->save();

    }

}
