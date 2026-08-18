<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\SetPostLocationJob;
use App\Models\PasswordResetToken;
use App\Services\AppStoreConnectAuth;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Database\QueryException;
use Str;


class AuthController extends Controller
{
    public function signin(Request $request): JsonResponse
    {
        try{
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required',
            ],[
                'email.required' => 'Email is required',
                'email.email' => 'Invalid email format',
                'password.required' => 'Password is required',
            ]);

            if($validator->fails()) return response()->json(['error' => $validator->errors()->first()], 422);

            $user = User::where('email', $request->email)->first();
            if(!$user) throw new Exception('User not found',404);
            if(!$user->is_active) throw new Exception('Account is deactivated. Please contact support.',403);
            if($user->is_delete == 1) throw new Exception('This account has been deleted',403);
            if(!Hash::check($request->password, $user->password)) throw new Exception('Invalid password',401);
            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;
            $user->update([
                'last_login_at' => now(),
                'fcm_token' => $request->fcm_token ?? null, // Update FCM token if provided
                'device_id' => $request->device_id,
                'app_version' => $request->app_version ?? null
            ]);

            return response()->json(['token' => $token,'user' => $user], 200);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        }
    }

    public function signup(Request $request): JsonResponse
    {
        try{
            $validator = Validator::make($request->all(), [
                'full_name' => 'required|string',
                'email' => 'required|email|unique:users',
                'password' => 'required',
                'device_id' => 'required',
                'listen_from' => 'required',

                'state' => 'nullable|string',
                'county' => 'nullable|string',
            ],[
                'full_name.required' => 'Full name is required',
                'email.required' => 'Email is required',
                'email.email' => 'Invalid email format',
                'email.unique' => 'Email already exists',
                'password.required' => 'Password is required',
                'device_id.required' => 'Device ID is required',
                'listen_from.required' => 'Listen from is required',

                'state.string' => 'State must be a string',
                'county.string' => 'County must be a string',
            ]);
            if($validator->fails()) throw new Exception($validator->errors()->first(),422);
            DB::beginTransaction();

            // Generate the username here
            $username = $this->generateUsername($request->full_name);

            $user = User::create([
                'full_name' => $request->full_name,
                'username'  => $username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'device_id' => $request->device_id,
                'app_version' => $request->app_version ?? null,
                'fcm_token' => $request->fcm_token ?? null,
                'listen_from' => $request->listen_from,
                'state' => $request->state ?? null ,
                'county' => $request->county ?? null,
            ]);
            DB::commit();
            return response()->json(["message" => "Account Register successfully"], 200);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function socialLoginSignup(Request $request): JsonResponse
    {
        try{
            $validator = Validator::make($request->all(), [
                'provider' => 'required|in:google,apple,facebook',
                'email' => 'nullable|email',
                'full_name' => 'required',
                'device_id' => 'required',
                'google_id' => 'required_if:provider,google',
                'apple_id' => 'required_if:provider,apple',
                'facebook_id' => 'required_if:provider,facebook',
            ]);
            if($validator->fails()) throw new Exception($validator->errors()->first(),422);


            $user = null;
            if($request->provider == 'google'){
                $user = User::where('google_id', $request->google_id)->orWhere('email', $request->email)->first();
            }elseif($request->provider == 'apple'){
                $user = User::where('apple_id', $request->apple_id)->orWhere('email', $request->email)->first();
            }elseif($request->provider == 'facebook'){
                $user = User::where('facebook_id', $request->facebook_id)->orWhere('email', $request->email)->first();
            }


            $already_registered = false;
            if($user){
                $already_registered = true;
            }
            // Generate the username here
            $username = $this->generateUsername($request->full_name);
            // if not found the register a user with the provider data
            DB::beginTransaction();
            if(!$user){
                $user = User::create([
                    'email' => $request->email,
                    'username' => $username,
                    'full_name' => $request->full_name,
                    'device_id' => $request->device_id,
                    'app_version' => $request->app_version ?? null,
                    'google_id' => $request->google_id ?? null,
                    'apple_id' => $request->apple_id ?? null,
                    'facebook_id' => $request->facebook_id ?? null,
                    'fcm_token' => $request->fcm_token,
                ]);
            }
            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;
            $user->update([
                'fcm_token' => $request->fcm_token,
                'device_id' => $request->device_id,
                'app_version' => $request->app_version ?? null,
                'google_id' => $request->google_id ?? null,
                'apple_id' => $request->apple_id ?? null,
                'facebook_id' => $request->facebook_id ?? null,
                'last_login_at' => now(),
            ]);
            DB::commit();
            return response()->json(['token' => $token,'user' => $user,'already_registered' => $already_registered], 200);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 500);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], $e->getCode()?: 500);
        }
    }

    public function accountCheck(Request $request): JsonResponse
    {
        try{
            $validator = Validator::make($request->all(), [
                'email' => 'nullable|email',
                'social_id' => 'nullable|string',
            ]);
            if($validator->fails()) throw new Exception($validator->errors()->first(),422);

            $user = null;
            if($request->email){
                $user = User::where('email', $request->email)->first();
            }elseif($request->social_id){
                $user = User::where('google_id', $request->social_id)->first();
                if(!$user){
                    $user = User::where('apple_id', $request->social_id)->first();
                }
            }

            if(!$user) return response()->json(['user' => null], 200);
            // delete and create new token and set up last login at
            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;

            $fcm_token = $request->fcm_token ?? $user->fcm_token;


            $user->update(['last_login_at' => now(), 'fcm_token' => $fcm_token]);
            return response()->json(['token' => $token,'user' => $user], 200);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        }
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $validator = validator(
                $request->all(),
                [
                    'email' => 'required|email|exists:users',
                ],
                [
                    'email.required' => 'Email Address required',
                    'email.email' => 'Invalid Email',
                    'email.exists' => 'Invalid Email Address',
                ]
            );

            if ($validator->fails())
                throw new Exception($validator->errors()->first(), 400);

            $tokenExist = PasswordResetToken::where('email', $request->email)->exists();
            if ($tokenExist)
                PasswordResetToken::where('email', $request->email)->delete();

            //  otp 6 number
            $token = rand(1000, 9999);
            PasswordResetToken::insert([
                'email' => $request->email,
                'token' => $token,
                'created_at' => now()
            ]);

            $user = User::where('email', $request->email)->first();

            myMailSend(
                $user->email,
                $user->full_name,
                'Forgot Password Mail',
                'Hi ' . $user->full_name . ', this is your one time password: ' . $token,
                null,
                $token
            );

            return response()->json([
                'message' => 'Reset OTP sent successfully',
            ], 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $validator = validator(
                $request->all(),
                [
                    'token' => 'required|string',

                    'password' => 'nullable|string|min:8',
                ],
                [
                    'token.required' => 'Token required',

                    'password.string' => 'Password must be a string',
                    'password.min' => 'Password must be at least 8 characters',
                ]
            );

            if ($validator->fails())
                throw new Exception($validator->errors()->first(), 400);

            $data = PasswordResetToken::where('token', $request->token)->first();
            if (empty($data))
                throw new Exception('Invalid token', 400);

            // Phase 1: OTP Verified successfully
            if (empty($request->password)) {
                // If no password is provided, just return a success message for OTP verification
                return response()->json([
                    'message' => 'OTP verified successfully',
                ], 200);
            }

            $user = User::where('email', $data->email)->first();
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            PasswordResetToken::where('token', $request->token)->delete();

            return response()->json([
                'message' => 'Password reset successfully',
            ], 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function resendCode(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ], [
                'email.required' => 'Email is required',
                'email.email' => 'Invalid email format',
            ]);

            if ($validator->fails())
                throw new Exception($validator->errors()->first(), 400);

            $user = User::where('email', $request->email)->first();
            if (!$user)
                throw new Exception('User not found', 404);
            $token = rand(1000, 9999);
                PasswordResetToken::where('email', $request->email)->delete();
                PasswordResetToken::insert([
                    'email' => $request->email,
                    'token' => $token,
                    'created_at' => now()
                ]);

            myMailSend(
                $user->email,
                $user->full_name,
                'Forgot Password Mail',
                'Hi ' . $user->full_name . ', this is your one time password: ' . $token,
                null,
                $token
            );

            return response()->json(['token' => $token], 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function deleteUser($id):JsonResponse
    {
        $user = User::find($id);

        $user->delete();
        
        return response()->json([
                'message' => 'User Deleted successfully',
            ], 200);
    }

    public function listenFrom(Request $request): JsonResponse
    {
        $user = Auth::user();
        $user->update([
            'listen_from' => $request->listen_from,
            'state' => $request->state ?? null ,
            'county' => $request->county ?? null,
        ]);
        return response()->json([
            'message' => 'Listen From Updated successfully',
        ], 200);
    }

    public function jwt(){
        $service = new AppStoreConnectAuth();
        $jwt = $service->generateToken();
        return response()->json($jwt);
    }

    private function generateUsername(string $fullName): string
    {
        // Remove spaces and special characters
        $baseName = str_replace('-', '', Str::slug($fullName));
        
        // Determine a random target length between 5 and 12
        $targetLength = rand(5, 12);
        
        // If the name is already longer than the target, trim it
        if (strlen($baseName) > $targetLength - 3) {
            $baseName = substr($baseName, 0, $targetLength - 3);
        }

        // Append random alphanumeric characters to fill the remaining length
        $remainingLength = $targetLength - strlen($baseName);
        $randomSuffix = Str::lower(Str::random($remainingLength));

        return $baseName . $randomSuffix;
    }

    public function location(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'state' => 'required|string',
            'county' => 'required|string',
        ],[
            'state.required' => 'State is required',
            'county.required' => 'County is required',
            'state.string' => 'State must be a string',
            'county.string' => 'County must be a string',
        ]);
        if($validator->fails()) return response()->json(['error' => $validator->errors()->first()], 422);
        DB::beginTransaction();
        $user->update([
            'state' => $request->state,
            'county' => $request->county,
        ]);
        // update state and county in every post of that user
        SetPostLocationJob::dispatch($request->state, $request->county, $user->id);
        DB::commit();
        return response()->json([
            'message' => 'Location Updated successfully',
        ], 200);
    }
}
