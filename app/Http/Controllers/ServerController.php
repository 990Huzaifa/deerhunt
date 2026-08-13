<?php

namespace App\Http\Controllers;

use App\Services\BrevoService;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function serverAlert(Request $request)
    {
        // limints here
        $diskLimit = 85; // in percentage
        $ramLimit = 80; // in percentage

        $diskUsage = $request->disk_usage;
        $ramUsage = $request->ram_usage;

        if( $diskUsage > $diskLimit || $ramUsage > $ramLimit ){
            // send mail here
            $brevoServise = new BrevoService();
    
            $brevoServise->sendServerAlertMail($diskUsage, $ramUsage);

        }

        return response()->json(['message' => 'Alert email sent if limits exceeded.'], 200);
    }
}
