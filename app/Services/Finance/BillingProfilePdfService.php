<?php

namespace App\Services\Finance;

use App\Models\BillingProfile;
use App\Services\SystemSettingService;
use App\Support\Documents\DompdfRenderer;
use Illuminate\Support\Facades\Storage;

class BillingProfilePdfService
{
    public function __construct(
        private DompdfRenderer $pdf,
        private SystemSettingService $settings,
    ) {}

    public function renderBinary(BillingProfile $profile): string
    {
        return $this->pdf->renderView('documents.pdf.billing', $this->viewData($profile, embedLogo: true));
    }

    /**
     * @return array{profile: BillingProfile, companyLogo: string|null}
     */
    public function viewData(BillingProfile $profile, bool $embedLogo = false): array
    {
        $profile->loadMissing(['client', 'site']);

        return [
            'profile' => $profile,
            'companyLogo' => $embedLogo
                ? ($this->companyLogoDataUri() ?? $this->companyLogoUrl())
                : $this->companyLogoUrl(),
        ];
    }

    public function filename(BillingProfile $profile): string
    {
        return 'billing-profile-BP-'.str_pad((string) $profile->id, 4, '0', STR_PAD_LEFT).'.pdf';
    }

    private function companyLogoDataUri(): ?string
    {
        $absolutePath = $this->companyLogoAbsolutePath();

        if ($absolutePath === null || ! is_readable($absolutePath)) {
            return null;
        }

        $binary = @file_get_contents($absolutePath);

        if ($binary === false || $binary === '') {
            return null;
        }

        $mime = @mime_content_type($absolutePath) ?: 'image/jpeg';

        if (! str_starts_with($mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    private function companyLogoUrl(): ?string
    {
        $settings = $this->settings->current();

        if (filled($settings->logo_path) && Storage::disk('public')->exists($settings->logo_path)) {
            return $settings->resolvedLogoUrl();
        }

        $fallback = public_path(config('psg.fallback_logo', 'images/logo.jpeg'));

        return is_file($fallback) ? asset(config('psg.fallback_logo', 'images/logo.jpeg')) : null;
    }

    private function companyLogoAbsolutePath(): ?string
    {
        $settings = $this->settings->current();

        if (filled($settings->logo_path) && Storage::disk('public')->exists($settings->logo_path)) {
            return Storage::disk('public')->path($settings->logo_path);
        }

        $fallback = public_path(config('psg.fallback_logo', 'images/logo.jpeg'));

        return is_file($fallback) ? $fallback : null;
    }
}
