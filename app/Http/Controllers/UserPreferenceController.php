<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserPreferenceController extends Controller
{
    public function getUser(Request $request)
    {
        $user = $request->user();
        $userData = $user->toArray();
        
        // Ensure data_filled is included in the response
        if (!isset($userData['data_filled'])) {
            $userData['data_filled'] = $user->data_filled ?? false;
        }
        
        return response()->json($userData);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'businessMeals' => 'nullable|string',
            'businessTravel' => 'nullable|string',
            'nric' => 'nullable|string',
            'nricName' => 'nullable|string',
            'personalDeductions' => 'nullable',
            'phoneNumber' => 'nullable|string',
            'profession' => 'nullable|string',
            'statementMethod' => 'nullable|string',
            'tin' => 'nullable|string',
            'workLocation' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = Auth::id();
        $user = User::find($userId);
        
        $user->update([
            'nric' => $request->nric,
            'name' => $request->nricName,
            'phone_number' => $request->phoneNumber,
            'tin' => $request->tin,
        ]);
        
        $preferencesToSave = [
            'businessMeals' => $request->businessMeals,
            'businessTravel' => $request->businessTravel,
            'personalDeductions' => $request->personalDeductions,
            'profession' => $request->profession,
            'statementMethod' => $request->statementMethod,
            'workLocation' => $request->workLocation,
        ];
        
        $savedPreferences = [];
        
        foreach ($preferencesToSave as $question => $answer) {
            if ($answer !== null) {
                if (is_array($answer)) {
                    $answer = json_encode($answer);
                }
                
                UserPreference::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'question' => $question
                    ],
                    [
                        'answer' => $answer
                    ]
                );
                
                $savedPreferences[$question] = $answer;
            }
        }
        
        $user->update([
            'data_filled' => true
        ]);

        return response()->json([
            'message' => 'User preferences saved successfully',
            'user_data' => [
                'nric' => $user->nric,
                'nric_name' => $user->name,
                'phone_number' => $user->phone_number,
                'tin' => $user->tin,
            ],
            'preferences' => $savedPreferences
        ], 200);
    }
    
    public function getUserPreferences()
    {
        $userId = Auth::id();
        $user = User::find($userId);
        $preferences = UserPreference::where('user_id', $userId)->get();
        
        $formattedPreferences = [];
        foreach ($preferences as $preference) {
            $answer = $preference->answer;
            
            if ($this->isJson($answer)) {
                $answer = json_decode($answer);
            }
            
            $formattedPreferences[$preference->question] = $answer;
        }
        
        $response = [
            'nric' => $user->nric,
            'nricName' => $user->nric_name,
            'phoneNumber' => $user->phone_number,
            'tin' => $user->tin,
        ];

        \Log::info('Formatted Preferences:', ['response' => $response]);
        return response()->json(array_merge($response, $formattedPreferences));
    }
    
    private function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
