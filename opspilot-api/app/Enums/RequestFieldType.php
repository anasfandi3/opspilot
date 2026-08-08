<?php

namespace App\Enums;

enum RequestFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Date = 'date';
    case Datetime = 'datetime';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Email = 'email';
    case Url = 'url';
}
