<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Database\QueryException;

class AuthController extends Controller
{
    public function signin(Request $request): JsonResponse
    {
        try{
            
            $validator = Validator::make($request->all(),[
                'email' => 'required|email',
                'password' => 'required',
            ],[
                'email.required' => 'Email is required',
                'email.email' => 'Invalid email format',
                'password.required' => 'Password is required',
            ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            
            
            // Conditions
            if (!Admin::where('email', $request->email)->exists())throw new Exception('Invalid email address or password', 400);


            $admin = Admin::where('email', $request->email)->first();
            if (!Hash::check($request->password, $admin->password)) throw new Exception('Invalid email address or password', 400);

            // $admin->tokens()->delete();
            $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

            return response()->json([
                'token' => $token,
                'admin' => $admin,
            ], 200);
    

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage(),], 403);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage(),], $e->getCode() ?: 500);
        }
    }

    public function logout(Request $request)
    {
        try{
            $admin = Auth::guard('admin')->user();
            $admin->tokens()->delete();
            return response()->json(['message' => 'Logout successfully'], 200);
        }catch(Exception $e){
            return response()->json(['server error' => $e->getMessage(),], 500);
        }
    }
}
