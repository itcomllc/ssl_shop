<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CertificateOrderRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            'domain_name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/'
            ],
            'csr' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!$this->validateCSR($value)) {
                        $fail('Invalid CSR format.');
                    }
                },
            ],
            'approver_email' => 'required|email|max:255',
            'payment_token' => 'required|string',
            'enable_subscription' => 'sometimes|boolean'
        ];
    }

    private function validateCSR($csr)
    {
        return openssl_csr_get_subject($csr) !== false;
    }

    public function messages()
    {
        return [
            'domain_name.regex' => 'Please enter a valid domain name.',
            'csr.required' => 'CSR is required.',
            'approver_email.email' => 'Please enter a valid email address.',
        ];
    }
}
