<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use Exception;

class LeadController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:leads,email',
                'phone' => 'nullable|string|max:20',
            ],[
                'name.required' => 'Name is required',
                'email.required' => 'Email is required',
                'email.email' => 'Email must be a valid email address',
                'email.unique' => 'This email is already associated with another lead',
                'phone.string' => 'Phone must be a valid string',
                'phone.max' => 'Phone number cannot exceed 20 characters',
            ]);

            if($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            
            $leadCheck = Lead::where('user_id', $user->id)->first();
            if($leadCheck) throw new Exception('You have already created a lead', 400);

            $lead = Lead::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
            ]);
            return response()->json(['message' => 'Lead created successfully', 'lead' => $lead], 201);

        }catch(QueryException $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(): JsonResponse
    {
        try{
            $user = Auth::user();
            $lead = Lead::where('user_id', $user->id)->first();
            if(!$lead) throw new Exception('Lead not found',404);
            return response()->json(['lead' => $lead], 200);
        }catch(QueryException $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try{
            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|email|unique:leads,email,'.$id,
                'phone' => 'nullable|string|max:20',
            ],[
                'email.email' => 'Email must be a valid email address',
                'email.unique' => 'This email is already associated with another lead',
                'phone.string' => 'Phone must be a valid string',
                'phone.max' => 'Phone number cannot exceed 20 characters',
            ]);
            if($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            $lead = Lead::find($id);
            if(!$lead) throw new Exception('Lead not found',404);
            $lead->update([
                'name' => $request->name ?? $lead->name,
                'email' => $request->email ?? $lead->email,
                'phone' => $request->phone ?? $lead->phone,
            ]);
            return response()->json(['message' => 'Lead updated successfully', 'lead' => $lead], 200);
        }catch(QueryException $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
