<?php

namespace App\Support\Currency;

use App\Models\Country;
use App\Models\CurrencyMetadata;
use Illuminate\Validation\ValidationException;

class ZoneCurrencyResolver
{
    public function __construct(private CurrencyCodeValidator $currencyCodeValidator)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{country:Country,country_code:?string,currency_code:string,currency_symbol:?string,country_detection_status:string}
     *
     * @throws ValidationException
     */
    public function resolveForZonePayload(array $data): array
    {
        $detectedCountries = $this->detectedCountryCodes($data['detected_countries'] ?? null);
        if (count($detectedCountries) > 1) {
            throw ValidationException::withMessages([
                'country_id' => ['This zone appears to cross multiple countries. Split it into separate zones.'],
            ]);
        }

        $country = $this->countryFromPayload($data, $detectedCountries);
        if (! $country) {
            throw ValidationException::withMessages([
                'country_id' => ['Country could not be resolved for this delivery zone. Please select the country before saving.'],
            ]);
        }

        $currencyCode = $this->currencyCodeValidator->normalize($country->currency);
        if (! $this->currencyCodeValidator->isValid($currencyCode)) {
            throw ValidationException::withMessages([
                'country_id' => ['Selected country does not have a valid ISO currency code.'],
            ]);
        }

        $this->ensureZoneCurrencyEnabled($currencyCode, $country);

        return [
            'country' => $country,
            'country_code' => $this->normalizeCountryCode($country->iso2),
            'currency_code' => $currencyCode,
            'currency_symbol' => $country->currency_symbol,
            'country_detection_status' => count($detectedCountries) === 1 ? 'confirmed' : 'manual',
        ];
    }

    public function applyStoreCurrencyFromZone(array $storeData, Country $country, string $currencyCode): array
    {
        $storeData['country'] = $country->name;
        // Existing stores.country_code semantics use countries.phonecode, not ISO2.
        $storeData['country_code'] = $country->phonecode;
        $storeData['currency_code'] = $currencyCode;

        return $storeData;
    }

    private function ensureZoneCurrencyEnabled(string $currencyCode, Country $country): void
    {
        $metadata = $this->currencyCodeValidator->metadata($currencyCode);
        $currency = CurrencyMetadata::query()->firstOrNew(['code' => $currencyCode]);

        // A zone-selected country makes its currency operationally available, but this does not change any financial amounts.
        $currency->name = $currency->name ?: ($metadata['name'] ?? $currencyCode);
        $currency->symbol = $currency->symbol ?: ($country->currency_symbol ?: ($metadata['symbol'] ?? null));
        $currency->decimal_places = $currency->decimal_places ?? $metadata['decimal_places'];
        $currency->minor_unit_factor = $currency->minor_unit_factor ?? $metadata['minor_unit_factor'];
        $currency->is_enabled = true;
        $currency->save();
    }

    /**
     * @return array<int, string>
     */
    private function detectedCountryCodes(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($code) => $this->normalizeCountryCode(is_array($code) ? ($code['code'] ?? null) : $code))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $detectedCountries
     */
    private function countryFromPayload(array $data, array $detectedCountries): ?Country
    {
        if (! empty($data['country_id'])) {
            $country = Country::query()->find((int) $data['country_id']);

            if ($country && count($detectedCountries) === 1
                && $this->normalizeCountryCode($country->iso2) !== $detectedCountries[0]) {
                throw ValidationException::withMessages([
                    'country_id' => ['Selected country does not match the country detected from the delivery zone.'],
                ]);
            }

            return $country;
        }

        if (count($detectedCountries) === 1) {
            return Country::query()
                ->whereRaw('UPPER(iso2) = ?', [$detectedCountries[0]])
                ->first();
        }

        $countryCode = $this->normalizeCountryCode($data['country_code'] ?? null);
        if (! $countryCode) {
            return null;
        }

        return Country::query()->whereRaw('UPPER(iso2) = ?', [$countryCode])->first();
    }

    private function normalizeCountryCode(mixed $code): ?string
    {
        $normalized = strtoupper(trim((string) $code));

        return $normalized === '' ? null : $normalized;
    }
}
