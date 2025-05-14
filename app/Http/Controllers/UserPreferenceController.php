<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserPreferenceController extends Controller
{
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
        \Log::info('Request Data:', $request->all());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = Auth::id();
        $user = User::find($userId);
        
        // Update user table fields
        $user->update([
            'nric' => $request->nric,
            'name' => $request->nricName,
            'phone_number' => $request->phoneNumber,
            'tin' => $request->tin,
        ]);
        
        // Fields to be saved in user_preferences table
        $preferencesToSave = [
            'businessMeals' => $request->businessMeals,
            'businessTravel' => $request->businessTravel,
            'personalDeductions' => $request->personalDeductions,
            'profession' => $request->profession,
            'statementMethod' => $request->statementMethod,
            'workLocation' => $request->workLocation,
        ];
        
        $savedPreferences = [];
        
        // Save each preference
        foreach ($preferencesToSave as $question => $answer) {
            if ($answer !== null) {
                // Special handling for array values like personalDeductions
                if (is_array($answer)) {
                    $answer = json_encode($answer);
                }
                
                // Update or create the preference
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
        \Log::info('User preferences saved successfully', ['user_id' => $userId, 'preferences' => $savedPreferences]);
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
