<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Models\Vendor;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\DeductibilityType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ReceiptController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $receipts = Receipt::where('user_id', Auth::id())
            ->with(['vendor', 'paymentMethod'])
            ->orderBy('receipt_date', 'desc')
            ->paginate(15);
        
        return view('receipts.index', compact('receipts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vendors = Vendor::orderBy('name')->get();
        $categories = ExpenseCategory::where('user_id', Auth::id())
            ->orWhereNull('user_id')
            ->orderBy('name')
            ->get();
        $paymentMethods = PaymentMethod::where('user_id', Auth::id())
            ->orderBy('name')
            ->get();
        $deductibilityTypes = DeductibilityType::all();
        
        return view('receipts.create', compact('vendors', 'categories', 'paymentMethods', 'deductibilityTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_id' => 'nullable|exists:vendors,id',
            'receipt_date' => 'required|date',
            'total_amount' => 'required|numeric|min:0',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'receipt_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'receipt_image' => 'nullable|image|max:10240',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.category_id' => 'nullable|exists:expense_categories,id',
            'items.*.deductibility_id' => 'nullable|exists:deductibility_types,id',
            'items.*.deduction_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.notes' => 'nullable|string',
        ]);

        // Create vendor if it doesn't exist
        if (!$request->vendor_id && $request->new_vendor_name) {
            $vendor = Vendor::create([
                'name' => $request->new_vendor_name,
                'address' => $request->new_vendor_address,
            ]);
            $validated['vendor_id'] = $vendor->id;
        }

        // Create receipt
        $validated['user_id'] = Auth::id();
        $receipt = Receipt::create($validated);

        // Create receipt items
        foreach ($validated['items'] as $itemData) {
            $receipt->items()->create($itemData);
        }

        // Handle receipt image
        if ($request->hasFile('receipt_image')) {
            $path = $request->file('receipt_image')->store('receipts/' . Auth::id(), 'public');
            $receipt->images()->create([
                'image_path' => $path,
                'original_filename' => $request->file('receipt_image')->getClientOriginalName(),
                'mime_type' => $request->file('receipt_image')->getMimeType(),
                'file_size' => $request->file('receipt_image')->getSize(),
            ]);
        }

        return redirect()->route('receipts.show', $receipt)
            ->with('success', 'Receipt created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Receipt $receipt)
    {
        $this->authorize('view', $receipt);
        
        $receipt->load(['vendor', 'paymentMethod', 'items.category', 'items.deductibilityType', 'images']);
        
        return view('receipts.show', compact('receipt'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Receipt $receipt)
    {
        $this->authorize('update', $receipt);
        
        $receipt->load(['items.category', 'items.deductibilityType', 'images']);
        
        $vendors = Vendor::orderBy('name')->get();
        $categories = ExpenseCategory::where('user_id', Auth::id())
            ->orWhereNull('user_id')
            ->orderBy('name')
            ->get();
        $paymentMethods = PaymentMethod::where('user_id', Auth::id())
            ->orderBy('name')
            ->get();
        $deductibilityTypes = DeductibilityType::all();
        
        return view('receipts.edit', compact('receipt', 'vendors', 'categories', 'paymentMethods', 'deductibilityTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Receipt $receipt)
    {
        $this->authorize('update', $receipt);
        
        $validated = $request->validate([
            'vendor_id' => 'nullable|exists:vendors,id',
            'receipt_date' => 'required|date',
            'total_amount' => 'required|numeric|min:0',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'receipt_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'receipt_image' => 'nullable|image|max:10240',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:receipt_items,id',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.category_id' => 'nullable|exists:expense_categories,id',
            'items.*.deductibility_id' => 'nullable|exists:deductibility_types,id',
            'items.*.deduction_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.notes' => 'nullable|string',
        ]);

        // Create vendor if it doesn't exist
        if (!$request->vendor_id && $request->new_vendor_name) {
            $vendor = Vendor::create([
                'name' => $request->new_vendor_name,
                'address' => $request->new_vendor_address,
            ]);
            $validated['vendor_id'] = $vendor->id;
        }

        // Update receipt
        $receipt->update($validated);

        // Get existing item IDs
        $existingItemIds = $receipt->items->pluck('id')->toArray();
        $updatedItemIds = [];

        // Update or create receipt items
        foreach ($validated['items'] as $itemData) {
            if (isset($itemData['id'])) {
                $item = $receipt->items->find($itemData['id']);
                if ($item) {
                    $item->update($itemData);
                    $updatedItemIds[] = $item->id;
                }
            } else {
                $item = $receipt->items()->create($itemData);
                $updatedItemIds[] = $item->id;
            }
        }

        // Delete items that were not updated
        $itemsToDelete = array_diff($existingItemIds, $updatedItemIds);
        if (!empty($itemsToDelete)) {
            $receipt->items()->whereIn('id', $itemsToDelete)->delete();
        }

        // Handle receipt image
        if ($request->hasFile('receipt_image')) {
            $path = $request->file('receipt_image')->store('receipts/' . Auth::id(), 'public');
            $receipt->images()->create([
                'image_path' => $path,
                'original_filename' => $request->file('receipt_image')->getClientOriginalName(),
                'mime_type' => $request->file('receipt_image')->getMimeType(),
                'file_size' => $request->file('receipt_image')->getSize(),
            ]);
        }

        return redirect()->route('receipts.show', $receipt)
            ->with('success', 'Receipt updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Receipt $receipt)
    {
        $this->authorize('delete', $receipt);
        
        // Delete associated images from storage
        foreach ($receipt->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }
        
        $receipt->delete();

        return redirect()->route('receipts.index')
            ->with('success', 'Receipt deleted successfully.');
    }

    /**
     * Process receipt with AI.
     */
    public function processWithAi(Receipt $receipt)
    {
        $this->authorize('update', $receipt);
        
        // This would be implemented with your AI service
        // For now, just a placeholder
        
        return redirect()->route('receipts.show', $receipt)
            ->with('success', 'Receipt processed with AI successfully.');
    }
}
