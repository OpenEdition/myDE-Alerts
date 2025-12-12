<?php

namespace MyDigitalEnvironment\AlertsBundle\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Platform: string implements TranslatableInterface
{
    case BOOKS = 'OB';
    case JOURNALS = 'OJ';
    case HYPOTHESES = 'HO';
    case CALENDA = 'CO';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::BOOKS => $translator->trans('platform.books', [], 'my-de-alerts',  $locale),
            self::JOURNALS => $translator->trans('platform.journals', [], 'my-de-alerts',  $locale),
            self::HYPOTHESES => $translator->trans('platform.hypotheses', [], 'my-de-alerts',  $locale),
            self::CALENDA => $translator->trans('platform.calenda', [], 'my-de-alerts',  $locale)
        };
    }

}
