<?php

namespace App\Enums;

enum WorkflowCommand: string
{
    case RequestClarification = 'request_clarification';
    case AmendRequest = 'amend_request';
    case BeginPlanning = 'begin_planning';
}
