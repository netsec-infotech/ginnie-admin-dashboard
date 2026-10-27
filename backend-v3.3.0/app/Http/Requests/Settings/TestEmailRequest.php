<?php

namespace App\Http\Requests\Settings;

use App\Enums\Email\SmtpContentTypeEnum;
use App\Enums\Email\SmtpEncryptionEnum;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Inputs for the admin "Send test email" action. Mirrors the writable subset of
 * the email setting payload plus the recipient — the controller dispatches a
 * one-off mail using these values so the operator can verify the SMTP config
 * before saving.
 */
class TestEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateSetting', [Setting::class, 'email']) === true;
    }

    public function rules(): array
    {
        return [
            'test_email'      => ['required', 'email', 'max:191'],
            'smtpHost'        => ['required', 'string', 'max:191'],
            'smtpPort'        => ['required', 'integer', 'between:1,65535'],
            'smtpEmail'       => ['required', 'email', 'max:191'],
            'smtpPassword'    => ['required', 'string', 'max:191'],
            'smtpEncryption'  => ['required', new Enum(SmtpEncryptionEnum::class)],
            'smtpContentType' => ['nullable', new Enum(SmtpContentTypeEnum::class)],
            'mailFromAddress' => ['required', 'email', 'max:191'],
            'mailFromName'    => ['nullable', 'string', 'max:191'],
        ];
    }

    public function attributes(): array
    {
        return [
            'test_email'      => __('labels.test_email_recipient_label'),
            'smtpHost'        => __('labels.smtp_host'),
            'smtpPort'        => __('labels.smtp_port'),
            'smtpEmail'       => __('labels.smtp_email'),
            'smtpPassword'    => __('labels.smtp_password'),
            'smtpEncryption'  => __('labels.smtp_encryption'),
            'smtpContentType' => __('labels.smtp_content_type'),
            'mailFromAddress' => __('labels.mail_from_address'),
            'mailFromName'    => __('labels.mail_from_name'),
        ];
    }

    /**
     * Transient SMTP config the controller wires into the mailer at runtime.
     */
    public function toSmtpConfig(): array
    {
        return [
            'host'        => (string) $this->input('smtpHost'),
            'port'        => (int) $this->input('smtpPort'),
            'username'    => (string) $this->input('smtpEmail'),
            'password'    => (string) $this->input('smtpPassword'),
            'encryption'  => (string) $this->input('smtpEncryption'),
            'contentType' => (string) ($this->input('smtpContentType') ?: 'text'),
            'fromAddress' => (string) $this->input('mailFromAddress'),
            'fromName'    => (string) $this->input('mailFromName'),
        ];
    }
}
