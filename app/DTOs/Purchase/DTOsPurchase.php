<?php

namespace App\DTOs\Purchase;

use App\Http\Requests\Purchase\CreatePurchaseRequest;
use App\Http\Requests\Purchase\UpdatePurchaseRequest;
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
        private readonly ?string $currency = null,
        private readonly ?int $user_id = null,
        private readonly ?array $specific_numbers = null,
        private readonly ?string $payment_reference = null,
        private readonly ?string $payment_proof_url = null,
    ) {}

    public static function fromRequest(CreatePurchaseRequest $request): self
    {
        $validated = $request->validated();
        $paymentProofUrl = self::uploadPaymentProofToS3($request);

        return new self(
            event_id: $validated['event_id'],
            event_price_id: $validated['event_price_id'],
            payment_method_id: $validated['payment_method_id'],
            quantity: $validated['quantity'] ?? 1,
            currency: $validated['currency'] ?? null,
            user_id: Auth::id(),
            specific_numbers: $validated['specific_numbers'] ?? null,
            payment_reference: $validated['payment_reference'] ?? null,
            payment_proof_url: $paymentProofUrl,
        );
    }

    /**
     * Subir comprobante de pago a S3
     */
    private static function uploadPaymentProofToS3(CreatePurchaseRequest $request): ?string
    {
        if ($request->hasFile('payment_proof_url')) {
            $file = $request->file('payment_proof_url');

            // Generar nombre único para el archivo
            $fileName = 'payment-proofs/' . uniqid() . '.' . $file->getClientOriginalExtension();

            // Subir a S3 con visibilidad pública
            $uploaded = Storage::disk('s3')->put($fileName, file_get_contents($file), [
                'visibility' => 'public',
                'ContentType' => $file->getMimeType()
            ]);

            if ($uploaded) {
                // Retornar URL completa (ajusta según tu región)
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

        return new self(
            event_id: $validated['event_id'],
            event_price_id: $validated['event_price_id'],
            payment_method_id: $validated['payment_method_id'],
            quantity: $validated['quantity'] ?? 1,
            currency: $validated['currency'] ?? null,
            user_id: Auth::id(),
            specific_numbers: $validated['specific_numbers'] ?? null,
            payment_reference: $validated['payment_reference'] ?? null,
            payment_proof_url: null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'event_id' => $this->event_id,
            'event_price_id' => $this->event_price_id,
            'payment_method_id' => $this->payment_method_id,
            'quantity' => $this->quantity,
            'currency' => $this->currency,
            'user_id' => $this->user_id,
            'specific_numbers' => $this->specific_numbers,
            'payment_reference' => $this->payment_reference,
            'payment_proof_url' => $this->payment_proof_url,
        ], fn($value) => !is_null($value));
    }

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
}
