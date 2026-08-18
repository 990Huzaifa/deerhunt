<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    public function index(Request $request): JsonResponse 
    {
        return response()->json(['message' => 'Wallet system has been removed.'], 410);
    }
}
