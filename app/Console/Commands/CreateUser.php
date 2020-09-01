<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Tue, 01 Sep 2020 21:30:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */


/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Thu, 27 Aug 2020 14:16:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;

class CreateUser extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add:user {username}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create user';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {


        $user           = new User;
        $user->username = $this->argument('username');
        $user->save();


        return 0;


    }


}


