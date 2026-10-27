<?php

namespace App\Enums\Blog;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

enum BlogStatusEnum: string
{
    use InvokableCases, Names, Values;

    case DRAFT = 'draft';
    case PUBLISHED = 'published';
}
