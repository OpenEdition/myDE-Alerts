<?php

namespace MyDigitalEnvironment\AlertsBundle\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum UpdateFrequency: int implements TranslatableInterface
{
    case DAILY = 1;
    case WEEKLY = 7;
    case MONTHLY = 28;

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::DAILY => $translator->trans('alerts.frequency.daily', [], 'my-de-alerts', $locale),
            self::WEEKLY => $translator->trans('alerts.frequency.weekly', [], 'my-de-alerts', $locale),
            self::MONTHLY => $translator->trans('alerts.frequency.monthly', [], 'my-de-alerts', $locale),
        };
    }
}
