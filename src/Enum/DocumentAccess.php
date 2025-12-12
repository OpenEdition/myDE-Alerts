<?php

namespace MyDigitalEnvironment\AlertsBundle\Enum;

enum DocumentAccess:string
{
    case OPEN = 'info:eu-repo/semantics/openAccess';
    case RESTRICTED = 'info:eu-repo/semantics/restrictedAccess';
    case EMBARGOED = 'info:eu-repo/semantics/embargoedAccess';
}
