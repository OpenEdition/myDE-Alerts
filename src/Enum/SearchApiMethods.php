<?php

namespace MyDigitalEnvironment\AlertsBundle\Enum;

enum SearchApiMethods: string
{
    case DOCUMENTS = 'documents';
    case FACETS = 'facets';
}