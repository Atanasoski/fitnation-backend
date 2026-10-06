<?php

namespace App\Enums;

enum Gender: string
{
    use HasValueLabel;

    case Male = 'male';
    case Female = 'female';
    case Other = 'other';
}
