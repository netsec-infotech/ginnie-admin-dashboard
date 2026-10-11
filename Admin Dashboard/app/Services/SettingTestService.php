<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Sends one-off probes against in-flight setting payloads so the operator can
 * verify SMTP and payment-gateway credentials before saving them. All methods
 * return a uniform envelope:
 *   ['success' => bool, 'message' => string, 'gateway_status' => ?int,
 *    'gateway_body' => ?string, 'gateway_error' => ?string]
 */
class SettingTestService
{
    public function __construct(private readonly SettingService $settingService) {}

    /**
     * Fire a transient SMTP email by overriding the mail config for the
     * duration of the request, then restoring the prior values.
     *
     * @param array{host:string,port:int,username:string,password:string,encryption:string,contentType:string} $smtp
     */
    public function sendTestEmail(string $recipient, array $smtp): array
    {
        $brand = $this->settingService->getBrandName();
        $original = [
            'host'       => Config::get('mail.mailers.smtp.host'),
            'port'       => Config::get('mail.mailers.smtp.port'),
            'username'   => Config::get('mail.mailers.smtp.username'),
            'password'   => Config::get('mail.mailers.smtp.password'),
            'encryption' => Config::get('mail.mailers.smtp.encryption'),
            'from_addr'  => Config::get('mail.from.address'),
            'from_name'  => Config::get('mail.from.name'),
            'default'    => Config::get('mail.default'),
        ];

        try {
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.transport', 'smtp');
            Config::set('mail.mailers.smtp.host', $smtp['host']);
            Config::set('mail.mailers.smtp.port', $smtp['port']);
            Config::set('mail.mailers.smtp.username', $smtp['username']);
            Config::set('mail.mailers.smtp.password', $smtp['password']);
            Config::set('mail.mailers.smtp.encryption', $smtp['encryption']);
            Config::set('mail.from.address', $smtp['username']);
            Config::set('mail.from.name', $brand);

            app('mail.manager')->forgetMailers();

            $subject = __('labels.test_email_subject', ['brand' => $brand]);
            $body    = __('labels.test_email_body', ['brand' => $brand]);

            $sender = function ($message) use ($recipient, $subject) {
                $message->to($recipient)->subject($subject);
            };

            if (($smtp['contentType'] ?? 'text') === 'html') {
                Mail::html('<p>' . e($body) . '</p>', $sender);
            } else {
                Mail::raw($body, $sender);
            }

            return [
                'success'        => true,
                'message'        => __('labels.test_email_success_message', ['recipient' => $recipient]),
                'gateway_status' => null,
                'gateway_body'   => null,
                'gateway_error'  => null,
            ];
        } catch (TransportExceptionInterface $e) {
            Log::warning('Test SMTP send failed', ['error' => $e->getMessage()]);
            return [
                'success'        => false,
                'message'        => __('labels.test_email_transport_failed'),
                'gateway_status' => null,
                'gateway_body'   => null,
                'gateway_error'  => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Test SMTP send error', ['error' => $e->getMessage()]);
            return [
                'success'        => false,
                'message'        => __('labels.something_went_wrong'),
                'gateway_status' => null,
                'gateway_body'   => null,
                'gateway_error'  => $e->getMessage(),
            ];
        } finally {
            Config::set('mail.default', $original['default']);
            Config::set('mail.mailers.smtp.host', $original['host']);
            Config::set('mail.mailers.smtp.port', $original['port']);
            Config::set('mail.mailers.smtp.username', $original['username']);
            Config::set('mail.mailers.smtp.password', $original['password']);
            Config::set('mail.mailers.smtp.encryption', $original['encryption']);
            Config::set('mail.from.address', $original['from_addr']);
            Config::set('mail.from.name', $original['from_name']);
            app('mail.manager')->forgetMailers();
        }
    }

    /**
     * Verify a payment gateway's credentials with a cheap read-only API call.
     */
    public function testPaymentGateway(string $gateway, array $credentials): array
    {
        return match ($gateway) {
            'stripe'      => $this->testStripe($credentials),
            'razorpay'    => $this->testRazorpay($credentials),
            'paystack'    => $this->testPaystack($credentials),
            'flutterwave' => $this->testFlutterwave($credentials),
            'xendit'      => $this->testXendit($credentials),
            default       => [
                'success'        => false,
                'message'        => __('labels.test_payment_unsupported_gateway'),
                'gateway_status' => null,
                'gateway_body'   => null,
                'gateway_error'  => null,
            ],
        };
    }

    /**
     * Stripe: retrieve account info using the secret key (read-only ping).
     */
    private function testStripe(array $c): array
    {
        $secret = trim((string) ($c['secret_key'] ?? ''));
        if ($secret === '') {
            return $this->missingCredentials();
        }

        try {
            $response = Http::withToken($secret)
                ->acceptJson()
                ->timeout(15)
                ->get('https://api.stripe.com/v1/account');

            return $this->httpResult(
                $response->successful(),
                $response->status(),
                $response->body(),
                $response->successful()
                    ? __('labels.test_payment_success', ['gateway' => 'Stripe'])
                    : __('labels.test_payment_failed', ['gateway' => 'Stripe']),
            );
        } catch (Throwable $e) {
            return $this->exceptionResult($e, 'Stripe');
        }
    }

    /**
     * Razorpay: list one order using basic auth (key_id + secret).
     */
    private function testRazorpay(array $c): array
    {
        $keyId  = trim((string) ($c['key_id'] ?? ''));
        $secret = trim((string) ($c['secret_key'] ?? ''));
        if ($keyId === '' || $secret === '') {
            return $this->missingCredentials();
        }

        try {
            $response = Http::withBasicAuth($keyId, $secret)
                ->acceptJson()
                ->timeout(15)
                ->get('https://api.razorpay.com/v1/payments', ['count' => 1]);

            return $this->httpResult(
                $response->successful(),
                $response->status(),
                $response->body(),
                $response->successful()
                    ? __('labels.test_payment_success', ['gateway' => 'Razorpay'])
                    : __('labels.test_payment_failed', ['gateway' => 'Razorpay']),
            );
        } catch (Throwable $e) {
            return $this->exceptionResult($e, 'Razorpay');
        }
    }

    /**
     * Paystack: ping /bank using the secret key (works on both test and live).
     */
    private function testPaystack(array $c): array
    {
        $secret = trim((string) ($c['secret_key'] ?? ''));
        if ($secret === '') {
            return $this->missingCredentials();
        }

        try {
            $response = Http::withToken($secret)
                ->acceptJson()
                ->timeout(15)
                ->get('https://api.paystack.co/bank', ['perPage' => 1]);

            return $this->httpResult(
                $response->successful(),
                $response->status(),
                $response->body(),
                $response->successful()
                    ? __('labels.test_payment_success', ['gateway' => 'Paystack'])
                    : __('labels.test_payment_failed', ['gateway' => 'Paystack']),
            );
        } catch (Throwable $e) {
            return $this->exceptionResult($e, 'Paystack');
        }
    }

    /**
     * Flutterwave: GET /banks/NG with secret key as bearer.
     */
    private function testFlutterwave(array $c): array
    {
        $secret = trim((string) ($c['secret_key'] ?? ''));
        if ($secret === '') {
            return $this->missingCredentials();
        }

        try {
            $response = Http::withToken($secret)
                ->acceptJson()
                ->timeout(15)
                ->get('https://api.flutterwave.com/v3/banks/NG');

            return $this->httpResult(
                $response->successful(),
                $response->status(),
                $response->body(),
                $response->successful()
                    ? __('labels.test_payment_success', ['gateway' => 'Flutterwave'])
                    : __('labels.test_payment_failed', ['gateway' => 'Flutterwave']),
            );
        } catch (Throwable $e) {
            return $this->exceptionResult($e, 'Flutterwave');
        }
    }

    /**
     * Xendit: retrieve account balance using basic auth secret key (read-only ping).
     */
    private function testXendit(array $c): array
    {
        $secret = trim((string) ($c['secret_key'] ?? ''));
        if ($secret === '') {
            return $this->missingCredentials();
        }

        try {
            $response = Http::withBasicAuth($secret, '')
                ->acceptJson()
                ->timeout(15)
                ->get('https://api.xendit.co/balance');

            return $this->httpResult(
                $response->successful(),
                $response->status(),
                $response->body(),
                $response->successful()
                    ? __('labels.test_payment_success', ['gateway' => 'Xendit'])
                    : __('labels.test_payment_failed', ['gateway' => 'Xendit']),
            );
        } catch (Throwable $e) {
            return $this->exceptionResult($e, 'Xendit');
        }
    }

    private function missingCredentials(): array
    {
        return [
            'success'        => false,
            'message'        => __('labels.test_payment_missing_credentials'),
            'gateway_status' => null,
            'gateway_body'   => null,
            'gateway_error'  => null,
        ];
    }

    private function httpResult(bool $success, int $status, string $body, string $message): array
    {
        return [
            'success'        => $success,
            'message'        => $message,
            'gateway_status' => $status,
            'gateway_body'   => $body,
            'gateway_error'  => null,
        ];
    }

    private function exceptionResult(Throwable $e, string $gateway): array
    {
        Log::warning("Test payment gateway probe failed: {$gateway}", ['error' => $e->getMessage()]);
        return [
            'success'        => false,
            'message'        => __('labels.test_payment_failed', ['gateway' => $gateway]),
            'gateway_status' => null,
            'gateway_body'   => null,
            'gateway_error'  => $e->getMessage(),
        ];
    }
}
