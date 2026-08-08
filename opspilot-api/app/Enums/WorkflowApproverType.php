<?php

namespace App\Enums;

enum WorkflowApproverType: string
{
    case Role = 'role';
    case User = 'user';
}
