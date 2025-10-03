<?php

namespace App\DTOs\Purchase;

use App\Http\Requests\Purchase\CreatePurchaseRequest;
use App\Http\Requests\Purchase\CreateSinglePurchaseRequest;
use App\Http\Requests\Purchase\UpdatePurchaseRequest;
use App\Models\EventPrice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DTOsPurchase
{
    public function __construct(
        private readonly int $event_id,
        private readonly int $event_price_id,
        private readonly int $payment_method_id,
        private readonly int $quantity,
        private readonly string $email,           // ✅ Obligatorio
        private readonly string $whatsapp,        // ✅ Obligatorio
        private readonly ?string $currency = null,
        private readonly ?int $user_id = null,    // ✅ Ahora es opcional
        private readonly ?array $specific_numbers = null,
        private readonly ?string $payment_reference = null,
        private readonly ?string $payment_proof_url = null,
        private readonly ?float $total_amount = null,
    ) {}

    public static function fromRequest(CreatePurchaseRequest $request): self
    {
        $validated = $request->validated();
        $paymentProofUrl = self::uploadPaymentProofToS3($request);

        $eventPrice = EventPrice::findOrFail($validated['event_price_id']);
        $quantity = $validated['quantity'] ?? 1;
        $totalAmount = $eventPrice->amount * $quantity;

        return new self(
            event_id: $validated['event_id'],
            event_price_id: $validated['event_price_id'],
            payment_method_id: $validated['payment_method_id'],
            quantity: $quantity,
            email: $validated['email'],
            whatsapp: $validated['whatsapp'],
            currency: $validated['currency'] ?? $eventPrice->currency,
            user_id: Auth::check() ? Auth::id() : null,  // ✅ Solo si está autenticado
            specific_numbers: $validated['specific_numbers'] ?? null,
            payment_reference: $validated['payment_reference'] ?? null,
            payment_proof_url: $paymentProofUrl,
            total_amount: $totalAmount,
        );
    }

    private static function uploadPaymentProofToS3(CreatePurchaseRequest $request): ?string
    {
        if ($request->hasFile('payment_proof_url')) {
            $file = $request->file('payment_proof_url');
            $fileName = 'payment-proofs/' . uniqid() . '.' . $file->getClientOriginalExtension();

            $uploaded = Storage::disk('s3')->put($fileName, file_get_contents($file), [
                'visibility' => 'public',
                'ContentType' => $file->getMimeType()
            ]);

            if ($uploaded) {
                $url = "https://backend-imagen-br.s3.us-east-2.amazonaws.com/" . $fileName;
                Log::info('Payment proof uploaded successfully', [
                    'file_name' => $fileName,
                    'url' => $url
                ]);
                return $url;
            }
        }
        return null;
    }

    public static function fromUpdateRequest(UpdatePurchaseRequest $request): self
    {
        $validated = $request->validated();
        $eventPrice = EventPrice::findOrFail($validated['event_price_id']);
        $quantity = $validated['quantity'] ?? 1;
        $totalAmount = $eventPrice->amount * $quantity;

        return new self(
            event_id: $validated['event_id'],
            event_price_id: $validated['event_price_id'],
            payment_method_id: $validated['payment_method_id'],
            quantity: $quantity,
            email: $validated['email'],
            whatsapp: $validated['whatsapp'],
            currency: $validated['currency'] ?? $eventPrice->currency,
            user_id: Auth::check() ? Auth::id() : null,
            specific_numbers: $validated['specific_numbers'] ?? null,
            payment_reference: $validated['payment_reference'] ?? null,
            payment_proof_url: null,
            total_amount: $totalAmount,
        );
    }



    public static function fromSinglePurchaseRequest(CreateSinglePurchaseRequest $request): self
    {
        $validated = $request->validated();
        $paymentProofUrl = self::uploadPaymentProofToS3Single($request);

        $eventPrice = EventPrice::findOrFail($validated['event_price_id']);

        // ✅ Calcular el total según la cantidad de números
        $ticketCount = count($validated['ticket_numbers']);
        $totalAmount = $eventPrice->amount * $ticketCount;

        return new self(
            event_id: $validated['event_id'],
            event_price_id: $validated['event_price_id'],
            payment_method_id: $validated['payment_method_id'],
            quantity: $ticketCount, // ✅ Cantidad de números seleccionados
            email: $validated['email'],
            whatsapp: $validated['whatsapp'],
            currency: $validated['currency'] ?? $eventPrice->currency,
            user_id: Auth::check() ? Auth::id() : null,
            specific_numbers: $validated['ticket_numbers'], // ✅ Array de números
            payment_reference: $validated['payment_reference'] ?? null,
            payment_proof_url: $paymentProofUrl,
            total_amount: $totalAmount,
        );
    }

    private static function uploadPaymentProofToS3Single(CreateSinglePurchaseRequest $request): ?string
    {
        if ($request->hasFile('payment_proof_url')) {
            $file = $request->file('payment_proof_url');
            $fileName = 'payment-proofs/' . uniqid() . '.' . $file->getClientOriginalExtension();

            $uploaded = Storage::disk('s3')->put($fileName, file_get_contents($file), [
                'visibility' => 'public',
                'ContentType' => $file->getMimeType()
            ]);

            if ($uploaded) {
                $url = "https://backend-imagen-br.s3.us-east-2.amazonaws.com/" . $fileName;
                Log::info('Payment proof uploaded successfully', [
                    'file_name' => $fileName,
                    'url' => $url
                ]);
                return $url;
            }
        }
        return null;
    }

    public function toArray(): array
    {
        return array_filter([
            'event_id' => $this->event_id,
            'event_price_id' => $this->event_price_id,
            'payment_method_id' => $this->payment_method_id,
            'quantity' => $this->quantity,
            'email' => $this->email,
            'whatsapp' => $this->whatsapp,
            'currency' => $this->currency,
            'user_id' => $this->user_id,
            'specific_numbers' => $this->specific_numbers,
            'payment_reference' => $this->payment_reference,
            'payment_proof_url' => $this->payment_proof_url,
            'total_amount' => $this->total_amount,
        ], fn($value) => !is_null($value));
    }

    // Getters
    public function getEventId(): int
    {
        return $this->event_id;
    }
    public function getEventPriceId(): int
    {
        return $this->event_price_id;
    }
    public function getPaymentMethodId(): int
    {
        return $this->payment_method_id;
    }
    public function getQuantity(): int
    {
        return $this->quantity;
    }
    public function getEmail(): string
    {
        return $this->email;
    }
    public function getWhatsapp(): string
    {
        return $this->whatsapp;
    }
    public function getCurrency(): ?string
    {
        return $this->currency;
    }
    public function getUserId(): ?int
    {
        return $this->user_id;
    }
    public function getSpecificNumbers(): ?array
    {
        return $this->specific_numbers;
    }
    public function getPaymentReference(): ?string
    {
        return $this->payment_reference;
    }
    public function getPaymentProofUrl(): ?string
    {
        return $this->payment_proof_url;
    }
    public function getTotalAmount(): ?float
    {
        return $this->total_amount;
    }
}
