<?php

namespace App\Enums;

enum WorkflowStatus: string
{
    case Inspecting = 'inspecting';
    case Clarifying = 'clarifying';
    case Planning = 'planning';
}
