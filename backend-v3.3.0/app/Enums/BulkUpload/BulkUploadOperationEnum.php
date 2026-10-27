<?php

namespace App\Enums\BulkUpload;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static CREATE()
 * @method static UPDATE()
 * @method static UPSERT()
 */
enum BulkUploadOperationEnum: string
{
    use InvokableCases, Names, Values;

    case CREATE = 'create';
    case UPDATE = 'update';
    case UPSERT = 'upsert';
    case TRANSLATION = 'translation';
}
