<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Exception;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    public function index(Request $request): JsonResponse 
    {
        try{
            $user = Auth::user();
            // check subscription status
            // $check = $user->subscriptions()->whereIn('plan', ['unlimited_cred_monthly','unlimited_cred_yearly','unlimited-credt-monthly','unlimited-credt-yearly','unlimited-cred-monthly','unlimited-cred-yearly'])->where('status', 'active')->first();
            // if ($check) {
            //     return response()->json(['message' => 'You are on an unlimited plan. No wallet needed.'], 200);
            // }
            
            $wallet = Auth::user()->wallet;
            // if not active exists so set null
            $product_id = $user->subscriptions()->where('status', 'active')->value('plan');
            $wallet['product_id'] = $product_id;
            
            return response()->json($wallet);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}
