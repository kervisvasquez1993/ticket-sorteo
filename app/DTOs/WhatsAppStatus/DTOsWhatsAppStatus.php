<?php

namespace App\DTOs\WhatsAppStatus;

use App\Http\Requests\WhatsAppStatus\CreateWhatsAppStatusRequest;
use App\Http\Requests\WhatsAppStatus\UpdateWhatsAppStatusRequest;
use Illuminate\Support\Facades\Auth;

class DTOsWhatsAppStatus 
{
    public function __construct(
        // Define your properties here
        // private readonly string $property1,
        // private readonly string $property2,
    ) {}
    
    public static function fromRequest(CreateWhatsAppStatusRequest $request): self
    {
        $validated = $request->validated();
        
        return new self(
            // property1: $validated['property1'],
            // property2: $validated['property2'],
        );
    }
    
    public static function fromUpdateRequest(UpdateWhatsAppStatusRequest $request): self
    {
        $validated = $request->validated();
        
        return new self(
            // property1: $validated['property1'],
            // property2: $validated['property2'],
        );
    }
    
    public function toArray(): array
    {
        return [
            // 'property1' => $this->property1,
            // 'property2' => $this->property2,
        ];
    }
    
    // Add getter methods for each property
    // public function getProperty1(): string
    // {
    //     return $this->property1;
    // }
}
