<?php

namespace App\Http\Resources\Website;

use App\Models\WebsiteSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WebsiteSetting */
class WebsiteSettingResource extends JsonResource
{
    /**
     * @var list<string>
     */
    public const ADDRESS_KEYS = [
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
    ];

    /**
     * @var list<string>
     */
    public const SOCIAL_LINK_KEYS = [
        'facebook',
        'instagram',
        'linkedin',
        'x',
        'youtube',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'site_name' => $this->site_name,
            'tagline' => $this->tagline,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'address' => $this->sanitizeJsonObject($this->address, self::ADDRESS_KEYS),
            'social_links' => $this->sanitizeJsonObject($this->social_links, self::SOCIAL_LINK_KEYS),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @param  list<string>  $allowedKeys
     * @return array<string, mixed>|null
     */
    private function sanitizeJsonObject(?array $data, array $allowedKeys): ?array
    {
        if ($data === null) {
            return null;
        }

        $filtered = [];

        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $data)) {
                $filtered[$key] = $data[$key];
            }
        }

        if ($filtered === []) {
            return null;
        }

        return $filtered;
    }
}
