<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingTransactionRequest;
use App\Http\Resources\Api\BookingTransactionApiResource;
use App\Models\BookingTransaction;
use App\Models\HomeService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BookingTransactionController extends Controller
{
    public function store(StoreBookingTransactionRequest $request)
    {
        try {
            // Validate the request data
            $validatedData = $request->validated();

            // Handle file upload
            if ($request->hasFile('proof')) {
                $filePath = $request->file('proof')->store('proofs', 'public');
                $validatedData['proof'] = $filePath; //Proof bukti pembayaran.png
            }

            // Retrieve the service IDs from the request
            // No need to decode JSON anymore, as it's sent as an array
            $serviceIds = $request->input('service_ids');

            if (empty($serviceIds)) {
                return response()->json(['message' => 'No services selected!'], 400);
            }

            // Fetch services from the database
            $services = HomeService::whereIn('id', $serviceIds)->get();

            if ($services->isEmpty()) {
                return response()->json(['message' => 'Invalid services!'], 400);
            }

            // Calculate total price, tax, insurance, and grand total
            $totalPrice = $services->sum('price');
            $tax = 0.11 * $totalPrice; // Assuming 11% tax
            // $insurance = 0.05 * $totalPrice; // This line is not visible but often follows tax
            $grandTotal = $totalPrice + $tax; // + $insurance; // Include insurance if calculated

            //Use carbon to set the schedule_at to tomorrow's date
            $validatedData['schedule_at'] = Carbon::tomorrow()->toDateString();

            // Populate the booking transaction data
            $validatedData['total_amount'] = $grandTotal;
            $validatedData['total_tax_amount'] = $tax;
            $validatedData['sub_total'] = $totalPrice;
            $validatedData['is_paid'] = false; // Assuming not paid yet
            $validatedData['booking_trx_id'] = BookingTransaction::generateUniqueTrxId(); // Assuming you have this static method

            // Create the booking transaction
            $bookingTransaction = BookingTransaction::create($validatedData);

            if (!$bookingTransaction) {
                return response()->json(['message' => 'Booking Transaction not created!', 'error' => 'Unknown error'], 500);
            }

            // Create transaction details for each service
            foreach ($services as $service) {
                $bookingTransaction->transactionDetails()->create([
                    'home_service_id' => $service->id,
                    'price' => $service->price,
                ]);
            }
            // Prepare the booking transaction data with details for the response
            return new BookingTransactionApiResource($bookingTransaction->load('transactionDetails')); // Changed from response()->json to resource
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create booking transaction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function booking_details(Request $request) // Import Request class for this method
    {
        $request->validate([
            'email' => 'required|string',
            'booking_trx_id' => 'required|string',
        ]);

        $booking = BookingTransaction::where('email', $request->email)
            ->where('booking_trx_id', $request->booking_trx_id)
            ->with([
                'transactionDetails',
                'transactionDetails.homeService'
            ])
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        return new BookingTransactionApiResource($booking);
    }
}
