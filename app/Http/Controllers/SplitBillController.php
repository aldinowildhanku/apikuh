<?php

namespace App\Http\Controllers; // Pastikan namespace ini benar, sesuai struktur folder

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str; // Untuk UUID/random string
use App\Models\Invoice;
use App\Models\InvoicePerson;
use App\Models\InvoiceItem;
use Exception;
use Carbon\Carbon; // *** DITAMBAHKAN: Import Carbon untuk waktu ***

class SplitBillController extends Controller
{
    /**
     * Endpoint untuk melakukan perhitungan dan menyimpan split bill.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculateAndStore(Request $request)
    {
        // 1. Validasi Input
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
            'qris_image'               => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Validation Error',
                'errors'  => $validator->errors()
            ], 422); // Unprocessable Entity
        }

        // Ambil data dari request
        $personsData    = $request->input('persons');
        $discountAmount = (float)($request->input('discount_amount', 0));
        $shippingCost   = (float)($request->input('shipping_cost', 0));
        $serviceCharge  = (float)($request->input('service_charge', 0));
        $bankName       = $request->input('bank_name');
        $accountNumber  = $request->input('account_number');

        DB::beginTransaction(); // Memulai transaksi database
        try {
            // 2. Hitung Total Sebelum Diskon
            $totalBeforeDiscount = 0;
            $personTotals        = []; // Untuk menyimpan total belanjaan setiap orang

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

            // 3. Hitung Grand Total
            $grandTotal = $totalBeforeDiscount - $discountAmount + $shippingCost + $serviceCharge;

            // Pastikan grand total tidak negatif
            if ($grandTotal < 0) {
                $grandTotal = 0;
            }

            // 4. Hitung Pro-rata Alokasi Biaya Tambahan/Diskon
            $remainingAmountToDistribute = $grandTotal - $totalBeforeDiscount; // Perbedaan yang perlu dialokasikan

            // Jika totalBeforeDiscount adalah 0 (misal: semua barang gratis), hindari pembagian nol
            $sharePerUnitOfCost = ($totalBeforeDiscount > 0)
                ? $remainingAmountToDistribute / $totalBeforeDiscount
                : 0;

            $personsToSave = [];
            foreach ($personTotals as $personData) {
                $proratedAmount = $personData['total'] + ($personData['total'] * $sharePerUnitOfCost);
                $personsToSave[] = [
                    'name'                      => $personData['name'],
                    'person_total_amount'       => $personData['total'],
                    'amount_to_pay_after_prorate' => round($proratedAmount, 2), // Bulatkan 2 desimal
                    'items'                     => $personData['items']
                ];
            }

            // 5. Upload Gambar QRIS (jika ada)
            $qrisImageUrl = null;
            if ($request->hasFile('qris_image')) {
                $image = $request->file('qris_image');
                $imageName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
                // Simpan di storage/app/public/qris_images
                // Pastikan Anda sudah menjalankan `php artisan storage:link` sebelumnya
                $imagePath = $image->storeAs('qris_images', $imageName, 'public');
                $qrisImageUrl = asset('storage/' . $imagePath); // Dapatkan URL yang bisa diakses publik
            }

            // 6. Simpan Invoice Utama
            // *** MODIFIKASI INVOICE_ID DIMULAI DI SINI ***
            $timestamp = Carbon::now()->format('YmdHis'); // Contoh: 20250613185724
            $uniqueSuffix = Str::upper(Str::random(4)); // Contoh: ABCD
            $newInvoiceId = 'DHL' . $timestamp . $uniqueSuffix; // Contoh: DHL20250613185724ABCD
            // *** MODIFIKASI INVOICE_ID BERAKHIR DI SINI ***

            $invoice = Invoice::create([
                'invoice_id'            => $newInvoiceId, // Gunakan invoice ID yang baru
                'total_before_discount' => $totalBeforeDiscount,
                'discount_amount'       => $discountAmount,
                'shipping_cost'         => $shippingCost,
                'service_charge'        => $serviceCharge,
                'grand_total'           => round($grandTotal, 2), // Bulatkan 2 desimal
                'bank_name'             => $bankName,
                'account_number'        => $accountNumber,
                'qris_image_url'        => $qrisImageUrl,
            ]);

            // 7. Simpan Detail Setiap Orang dan Barang Mereka
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

            DB::commit(); // Menyelesaikan transaksi

            return response()->json([
                'status'    => 'success',
                'message'   => 'Split bill calculated and saved successfully.',
                'invoice_id' => $invoice->invoice_id,
                'invoice_details_url' => url('/api/split-bill/' . $invoice->invoice_id), // URL untuk mengambil detail
                'data'      => [
                    'invoice' => $invoice,
                    'persons' => $personsToSave, // Ini data yang sudah diproses untuk respon
                ]
            ], 201); // Created

        } catch (Exception $e) {
            DB::rollBack(); // Rollback transaksi jika terjadi error
            return response()->json([
                'status'  => 'failed',
                'message' => 'An error occurred during split bill calculation or saving: ' . $e->getMessage()
            ], 500); // Internal Server Error
        }
    }

    /**
     * Endpoint untuk mengambil detail split bill berdasarkan invoice_id.
     *
     * @param  string  $invoiceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($invoiceId)
    {
        try {
            // Mengambil invoice dengan relasi persons dan items
            $invoice = Invoice::with('persons.items')->where('invoice_id', $invoiceId)->first();

            if (!$invoice) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => 'Invoice not found.'
                ], 404); // Not Found
            }

            // Format data persons untuk output yang lebih rapi
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
            ], 500); // Internal Server Error
        }
    }
}