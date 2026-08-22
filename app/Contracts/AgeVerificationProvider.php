<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;

interface AgeVerificationProvider
{
    /** @return array{provider:string,reference:string,url:string} */
    public function begin(User $participant, string $opaqueReference, string $returnUrl): array;

    public function inquiryStatus(string $providerReference): ?string;

    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool;
}
