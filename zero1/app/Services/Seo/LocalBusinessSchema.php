<?php

namespace App\Services\Seo;

use App\Models\MediaAsset;
use App\Models\OpeningHour;
use App\Support\SiteSettings;

/**
 * JSON-LD schema.org/BarberShop montado apenas com dados cadastrados.
 */
class LocalBusinessSchema
{
    public function __construct(private SiteSettings $settings) {}

    public function build(): array
    {
        $s = $this->settings;
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'BarberShop',
            'name' => $s->businessName(),
            'url' => url('/'),
            'currenciesAccepted' => 'BRL',
        ];

        if ($s->get('business.street')) {
            $data['address'] = array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => trim($s->get('business.street').', '.$s->get('business.number', ''), ', '),
                'addressLocality' => $s->get('business.city'),
                'addressRegion' => $s->get('business.state'),
                'postalCode' => $s->get('business.postal_code'),
                'addressCountry' => $s->get('business.country', 'BR'),
            ]);
        }
        if ($phone = $s->get('business.phone')) {
            $data['telephone'] = '+55 '.$phone;
        }
        if ($s->get('business.latitude') && $s->get('business.longitude')) {
            $data['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $s->get('business.latitude'),
                'longitude' => (float) $s->get('business.longitude'),
            ];
        }
        if ($maps = $s->get('business.maps_url')) {
            $data['hasMap'] = $maps;
        }
        $sameAs = array_values(array_filter([$s->get('business.instagram')]));
        if ($sameAs) {
            $data['sameAs'] = $sameAs;
        }
        if (($logoId = $s->get('brand.logo_media_id')) && ($logo = MediaAsset::find($logoId))) {
            $data['logo'] = $logo->url();
            $data['image'] = $logo->url();
        }

        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        $hours = OpeningHour::query()->where('is_closed', false)->whereNotNull('opens_at')->orderBy('weekday')->get();
        if ($hours->isNotEmpty()) {
            $data['openingHoursSpecification'] = $hours->map(fn (OpeningHour $h) => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $days[$h->weekday],
                'opens' => substr($h->opens_at, 0, 5),
                'closes' => substr($h->closes_at, 0, 5),
            ])->values()->all();
        }

        return $data;
    }
}
