<?php

namespace App\Http\Resources;

use App\Helpers\TranslationHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class GlobalProductAttributeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        // Support both model instance and plain array payload
        $title = method_exists($this->resource, 'getTranslations')
            ? TranslationHelper::resolveText($this->resource->getTranslations('title') ?: [])
            : data_get($this->resource, 'title');
        $slug = data_get($this->resource, 'slug');
        $values = data_get($this->resource, 'values', []);

        return [
            'title' => $title,
            'slug' => $slug,
            'values' => GlobalProductAttributeValueResource::collection(collect($values)),
        ];
    }
}
