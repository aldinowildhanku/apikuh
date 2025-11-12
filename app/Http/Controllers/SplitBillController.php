<?php

namespace App\Http\Controllers; 

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str; 
use App\Models\Invoice;
use App\Models\InvoicePerson;
use App\Models\InvoiceItem;
use Exception;
use Carbon\Carbon; 

class SplitBillController extends Controller
{
    /**
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculateAndStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'persons'                  => 'required|array|min:1',
            'persons.*.name'           => 'required|string|max:255',
            'persons.*.items'          => 'required|array|min:1',
            'persons.*.items.*.name'   => 'required|string|max:255',
            'persons.*.items.*.price'  => 'required|numeric|min:0',
            'discount_amount'          => 'nullable|numeric|min:0',
            'shipping_cost'            => 'nullable|numeric|min:0',
            'service_charge'           => 'nullable|numeric|min:0',
            'bank_name'                => 'nullable|string|max:255',
            'account_number'           => 'nullable|string|max:255',
            'qris_image'               => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', 
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Validation Error',
                'errors'  => $validator->errors()
            ], 422); 
        }

        // Ambil data dari request
        $personsData    = $request->input('persons');
        $discountAmount = (float)($request->input('discount_amount', 0));
        $shippingCost   = (float)($request->input('shipping_cost', 0));
        $serviceCharge  = (float)($request->input('service_charge', 0));
        $bankName       = $request->input('bank_name');
        $accountNumber  = $request->input('account_number');

        DB::beginTransaction();
        try {

            $totalBeforeDiscount = 0;
            $personTotals        = []; 

            foreach ($personsData as $person) {
                $currentPersonTotal = 0;
                foreach ($person['items'] as $item) {
                    $currentPersonTotal += (float)$item['price'];
                }
                $totalBeforeDiscount += $currentPersonTotal;
                $personTotals[] = [
                    'name'  => $person['name'],
                    'total' => $currentPersonTotal,
                    'items' => $person['items']
                ];
            }

            $grandTotal = $totalBeforeDiscount - $discountAmount + $shippingCost + $serviceCharge;

            if ($grandTotal < 0) {
                $grandTotal = 0;
            }

            $remainingAmountToDistribute = $grandTotal - $totalBeforeDiscount; 

            $sharePerUnitOfCost = ($totalBeforeDiscount > 0)
                ? $remainingAmountToDistribute / $totalBeforeDiscount
                : 0;

            $personsToSave = [];
            foreach ($personTotals as $personData) {
                $proratedAmount = $personData['total'] + ($personData['total'] * $sharePerUnitOfCost);
                $personsToSave[] = [
                    'name'                      => $personData['name'],
                    'person_total_amount'       => $personData['total'],
                    'amount_to_pay_after_prorate' => round($proratedAmount, 2),
                    'items'                     => $personData['items']
                ];
            }

            $qrisImageUrl = null;
            if ($request->hasFile('qris_image')) {
                $image = $request->file('qris_image');
                $imageName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('qris_images', $imageName, 'public');
                $qrisImageUrl = asset('storage/' . $imagePath); 
            }


            $timestamp = Carbon::now()->format('YmdHis'); 
            $uniqueSuffix = Str::upper(Str::random(4)); 
            $newInvoiceId = 'DHL' . $timestamp . $uniqueSuffix; 


            $invoice = Invoice::create([
                'invoice_id'            => $newInvoiceId, 
                'total_before_discount' => $totalBeforeDiscount,
                'discount_amount'       => $discountAmount,
                'shipping_cost'         => $shippingCost,
                'service_charge'        => $serviceCharge,
                'grand_total'           => round($grandTotal, 2), 
                'bank_name'             => $bankName,
                'account_number'        => $accountNumber,
                'qris_image_url'        => $qrisImageUrl,
            ]);

            foreach ($personsToSave as $personData) {
                $invoicePerson = $invoice->persons()->create([
                    'person_name'               => $personData['name'],
                    'person_total_amount'       => $personData['person_total_amount'],
                    'amount_to_pay_after_prorate' => $personData['amount_to_pay_after_prorate'],
                ]);

                foreach ($personData['items'] as $item) {
                    $invoicePerson->items()->create([
                        'item_name'  => $item['name'],
                        'item_price' => $item['price'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status'    => 'success',
                'message'   => 'Split bill calculated and saved successfully.',
                'invoice_id' => $invoice->invoice_id,
                'invoice_details_url' => url('/api/split-bill/' . $invoice->invoice_id),
                'data'      => [
                    'invoice' => $invoice,
                    'persons' => $personsToSave,
                ]
            ], 201); 

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'failed',
                'message' => 'An error occurred during split bill calculation or saving: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 
     *
     * @param  string  $invoiceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($invoiceId)
    {
        try {

            $invoice = Invoice::with('persons.items')->where('invoice_id', $invoiceId)->first();

            if (!$invoice) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => 'Invoice not found.'
                ], 404); 
            }

            $formattedPersons = $invoice->persons->map(function ($person) {
                return [
                    'name'         => $person->person_name,
                    'total_items_amount' => (float)$person->person_total_amount,
                    'amount_to_pay' => (float)$person->amount_to_pay_after_prorate,
                    'items'        => $person->items->map(function ($item) {
                        return [
                            'name'  => $item->item_name,
                            'price' => (float)$item->item_price,
                        ];
                    })
                ];
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Invoice retrieved successfully.',
                'data'    => [
                    'invoice_id'          => $invoice->invoice_id,
                    'total_before_discount' => (float)$invoice->total_before_discount,
                    'discount_amount'     => (float)$invoice->discount_amount,
                    'shipping_cost'       => (float)$invoice->shipping_cost,
                    'service_charge'      => (float)$invoice->service_charge,
                    'grand_total'         => (float)$invoice->grand_total,
                    'bank_name'           => $invoice->bank_name,
                    'account_number'      => $invoice->account_number,
                    'qris_image_url'      => $invoice->qris_image_url,
                    'created_at'          => $invoice->created_at->toDateTimeString(),
                    'persons'             => $formattedPersons,
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'An error occurred while retrieving invoice: ' . $e->getMessage()
            ], 500); 
        }
    }
}