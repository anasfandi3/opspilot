<?php

namespace App\Enums;

enum WorkflowConditionLogic: string
{
    case All = 'all';
    case Any = 'any';
}
