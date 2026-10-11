<?php

namespace App\Enums\BulkUpload;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static PENDING()
 * @method static PROCESSING()
 * @method static COMPLETED()
 * @method static FAILED()
 * @method static CANCELLED()
 */
enum BulkUploadJobStatusEnum: string
{
    use InvokableCases, Names, Values;

    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
