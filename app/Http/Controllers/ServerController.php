<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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
            $htmlContent = "
                <h1>Server Alert</h1>
                <p>The server has detected high resource usage:</p>
                <ul>
                    <li>Disk Usage: {$diskUsage}%</li>
                    <li>RAM Usage: {$ramUsage}%</li>
                </ul>
                <p>Please take the necessary actions to investigate and resolve the issue.</p>
            ";

            Mail::html($htmlContent, function ($message) {
                $message->to('racklineai@gmail.com', 'Admin')
                    ->subject('Server Alert: High Resource Usage Detected');
            });
        }

        return response()->json(['message' => 'Alert email sent if limits exceeded.'], 200);
    }
}
