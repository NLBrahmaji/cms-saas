<?php

namespace App\Navigation;

enum NavigationItemType: string
{
    case Page = 'page';
    case Url = 'url';

    /**
     * Intended target field requirements for a valid item of this type.
     *
     * @return array{page_id: 'required'|'null', url: 'required'|'null'}
     */
    public function intendedFieldRequirements(): array
    {
        return match ($this) {
            self::Page => [
                'page_id' => 'required',
                'url' => 'null',
            ],
            self::Url => [
                'page_id' => 'null',
                'url' => 'required',
            ],
        };
    }

    /**
     * @return list<self>
     */
    public static function supported(): array
    {
        return [
            self::Page,
            self::Url,
        ];
    }
}
