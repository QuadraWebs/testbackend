<?php

namespace App\Services;

use App\Models\ReceiptImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ReceiptAiService
{
    /**
     * Process a receipt image with AI to extract text and data
     *
     * @param ReceiptImage $image
     * @return array
     */
    public function processUploadedImage($uploadedImage)
    {
        try {
            // For now, return mock data
            // In production, this would call an actual AI API
            return $this->getMockResponse();
            
            // Uncomment the below code when you have a real AI API to call
            /*
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.receipt_ai.api_key'),
                'Content-Type' => 'application/json',
            ])->post(config('services.receipt_ai.endpoint'), [
                // Send the image data directly
                'image_data' => $uploadedImage->image_data,
                'filename' => $uploadedImage->original_filename
            ]);
            
            if ($response->successful()) {
                return $this->validateAndFormatResponse($response->json());
            }
            
            Log::error('AI API Error', [
                'status' => $response->status(),
                'response' => $response->body(),
                'filename' => $uploadedImage->original_filename
            ]);
            
            throw new Exception('Failed to process receipt with AI: ' . $response->body());
            */
        } catch (Exception $e) {
            Log::error('Receipt AI processing error', [
                'message' => $e->getMessage(),
                'filename' => $uploadedImage->original_filename,
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    
    /**
     * Validate and format the AI response
     *
     * @param array $response
     * @return array
     */
    private function validateAndFormatResponse($response)
    {
        // Validate required fields
        $requiredFields = ['vendor_name', 'total_amount', 'date'];
        foreach ($requiredFields as $field) {
            if (!isset($response[$field])) {
                throw new Exception("AI response missing required field: {$field}");
            }
        }
        
        // Validate items array if present
        if (isset($response['items']) && is_array($response['items'])) {
            foreach ($response['items'] as $index => $item) {
                $requiredItemFields = ['description', 'quantity', 'unit_price', 'total_price'];
                foreach ($requiredItemFields as $field) {
                    if (!isset($item[$field])) {
                        throw new Exception("Item at index {$index} missing required field: {$field}");
                    }
                }
            }
        }
        
        return $response;
    }
    
    /**
     * Get mock response for development/testing
     *
     * @return array
     */
    private function getMockResponse()
    {
        $mockResponses = [
            [
                'vendor_name' => 'Uncle Jack SOGO',
                'total_amount' => 14.0,
                'date' => '26/04/25',
                'items' => [
                    [
                        'description' => 'UJ SIGNATURE BURGER W FRIED CHICKEN',
                        'quantity' => 1,
                        'unit_price' => 13.2,
                        'total_price' => 13.2,
                        'expense_category' => 'Miscellaneous',
                        'notes' => 'client dinner'
                    ]
                ],
                'payment_method' => 'Credit Card',
                'vendor_address' => 'LG-K11, Kompleks SOGO, 190 Jalan Tuanku Abdul Rahman, 50100 Kuala Lumpur.',
                'notes' => 'client dinner',
                'is_deductible' => true
            ],
            [
                'vendor_name' => 'Machines Sdn Bhd',
                'total_amount' => 3154.19,
                'date' => '2025-02-12',
                'items' => [
                    [
                        'description' => 'Apple Mac mini M4 chip 16GB RAM, 512GB SSD',
                        'quantity' => 1,
                        'unit_price' => 3349.0,
                        'total_price' => 3349.0,
                        'expense_category' => 'Electronic Gadget'
                    ]
                ],
                'payment_method' => 'SPayLater',
                'vendor_address' => 'No. 3, Jalan Kajibumi U1/70, Temasya Niaga, Temasya Glenmarie, Seksyen U1',
                'notes' => '',
                'is_deductible' => false
            ]
        ];
        
        // Return a random mock response
        return $mockResponses;
    }
}
