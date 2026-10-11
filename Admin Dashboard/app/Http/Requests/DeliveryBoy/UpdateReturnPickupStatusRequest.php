<?php

namespace App\Http\Requests\DeliveryBoy;

use App\Enums\Order\OrderItemReturnPickupStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReturnPickupStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     *
     * Handles PHP's native limitation where PUT/PATCH requests with multipart/form-data
     * do not automatically populate $_POST or $_FILES. Parses php://input directly
     * into input values and uploaded file instances so mobile clients using PUT work seamlessly.
     */
    protected function prepareForValidation(): void
    {
        // ==========================================================
        // Return Dispute System
        // Phase 2 (Bugfix)
        // Binary-Safe PUT Multipart Form-Data Parser
        // ==========================================================
        if (in_array($this->method(), ['PUT', 'PATCH']) && str_contains($this->header('Content-Type', ''), 'multipart/form-data')) {
            $rawBody = $this->getContent();
            if (empty($rawBody)) {
                return;
            }

            $contentType = $this->header('Content-Type');
            $boundary = null;
            if (preg_match('/boundary=([^;\s]+)/i', $contentType, $matches)) {
                $boundary = trim($matches[1], '"\' ');
            }

            \Illuminate\Support\Facades\Log::info('PUT Multipart Parser Debug', [
                'content_type' => $contentType,
                'boundary' => $boundary,
                'raw_body_length' => strlen($rawBody),
            ]);

            if ($boundary) {
                $boundaryDelimiter = '--' . $boundary;
                $parts = explode($boundaryDelimiter, $rawBody);
                $inputs = [];
                $files = [];

                \Illuminate\Support\Facades\Log::info('PUT Multipart Split Parts', [
                    'parts_count' => count($parts),
                ]);

                foreach ($parts as $index => $part) {
                    $part = ltrim($part, "\r\n");
                    if ($part === '' || $part === '--' || $part === "--\r\n") {
                        continue;
                    }

                    $pos = strpos($part, "\r\n\r\n");
                    if ($pos === false) {
                        $pos = strpos($part, "\n\n");
                        if ($pos === false) {
                            continue;
                        }
                        $headerLength = $pos + 2;
                    } else {
                        $headerLength = $pos + 4;
                    }

                    $headerSection = substr($part, 0, $pos);
                    $bodyBytes = substr($part, $headerLength);

                    if (str_ends_with($bodyBytes, "\r\n")) {
                        $bodyBytes = substr($bodyBytes, 0, -2);
                    } elseif (str_ends_with($bodyBytes, "\n")) {
                        $bodyBytes = substr($bodyBytes, 0, -1);
                    }

                    if (!preg_match('/name="([^"]+)"/i', $headerSection, $nameMatch)) {
                        continue;
                    }
                    $rawFieldName = $nameMatch[1];
                    $fieldName = rtrim($rawFieldName, '[]');

                    $filename = null;
                    if (preg_match('/filename="([^"]*)"/i', $headerSection, $fileMatch)) {
                        $filename = $fileMatch[1];
                    }

                    \Illuminate\Support\Facades\Log::info('PUT Multipart Part Parse', [
                        'index' => $index,
                        'fieldName' => $fieldName,
                        'filename' => $filename,
                        'body_length' => strlen($bodyBytes),
                    ]);

                    if ($filename === null || $filename === '') {
                        if (preg_match('/^([^\[]+)\[([^\]]*)\]$/', $rawFieldName, $bracketMatch)) {
                            $fieldName = $bracketMatch[1];
                            $inputs[$fieldName][] = trim($bodyBytes);
                        } else {
                            $inputs[$rawFieldName] = trim($bodyBytes);
                        }
                    } else {
                        $mimeType = 'application/octet-stream';
                        if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $headerSection, $mimeMatch)) {
                            $mimeType = trim($mimeMatch[1]);
                        }

                        $ext = pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg';
                        $tmpPath = tempnam(sys_get_temp_dir(), 'put_upload_') . '.' . $ext;
                        if (file_put_contents($tmpPath, $bodyBytes) !== false) {
                            $uploadedFile = new \Illuminate\Http\UploadedFile($tmpPath, $filename, $mimeType, null, true);
                            if (preg_match('/^([^\[]+)\[([^\]]*)\]$/', $rawFieldName, $bracketMatch)) {
                                $fieldName = $bracketMatch[1];
                                $files[$fieldName][] = $uploadedFile;
                            } elseif (strcasecmp($fieldName, 'image') === 0) {
                                $files['image'] = $uploadedFile;
                                $files['images'][] = $uploadedFile;
                            } elseif (strcasecmp($fieldName, 'images') === 0) {
                                $files['images'][] = $uploadedFile;
                            } else {
                                $files[$rawFieldName] = $uploadedFile;
                            }
                        }
                    }
                }

                if (!empty($inputs)) {
                    $this->merge($inputs);
                }
                if (!empty($files)) {
                    foreach ($files as $key => $fileVal) {
                        $this->files->set($key, $fileVal);
                    }
                }
            }
        }

        if ($this->hasFile('image') && !$this->hasFile('images')) {
            $this->files->set('images', [$this->file('image')]);
        }
        if ($this->hasFile('images') && !is_array($this->file('images'))) {
            $this->files->set('images', [$this->file('images')]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // ==========================================================
        // Return Dispute System
        // Phase 2
        // Rider Pickup Mandatory Proof and Package Condition Validation
        // ==========================================================
        $isPickedUp = $this->input('status') === OrderItemReturnPickupStatusEnum::PICKED_UP();
        
        return [
            'status' => ['required', 'in:' . OrderItemReturnPickupStatusEnum::PICKED_UP() . ',' . OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER()],
            'otp' => ['nullable', 'string', 'size:6'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            // 'images' => [$isPickedUp ? 'required' : 'nullable', 'array', $isPickedUp ? 'min:1' : 'nullable'],
            'images.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'pickup_condition' => [$isPickedUp ? 'required' : 'nullable', 'string', 'in:good,damaged,opened_package,other'],
            'pickup_remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => __('validation.status_required'),
            'status.in' => __('validation.status_in'),
            'otp.size' => __('validation.otp_size') ?? 'The OTP must be exactly 6 digits.',
            'image.image' => __('validation.image') ?? 'The uploaded file must be an image.',
            'images.*.image' => __('validation.image') ?? 'Each uploaded file must be an image.',
            // 'image.required_without' => 'Product verification photos are required when picking up the item from the customer.',
            // 'images.required_without' => 'Product verification photos are required when picking up the item from the customer.',
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'pickup status',
            'otp' => 'return verification OTP',
            'image' => 'pickup condition image',
            'images' => 'pickup condition images',
            'pickup_condition' => 'pickup condition',
            'pickup_remarks' => 'pickup remarks',
        ];
    }
}
