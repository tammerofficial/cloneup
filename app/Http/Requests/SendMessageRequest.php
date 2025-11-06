<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'chat_id' => 'required|numeric|exists:chats,id',
            'message' => 'nullable|string|max:4096',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => [
                'file',
                'max:10240', // 10MB
                'mimetypes:image/jpeg,image/jpg,image/png,image/gif,image/webp,video/mp4,video/avi,video/quicktime,video/x-matroska,audio/mpeg,audio/wav,audio/ogg,audio/mp4,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,application/zip,application/x-rar-compressed'
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Message is required if no attachments
            if (empty($this->attachments) && empty($this->message)) {
                $validator->errors()->add('message', 'Either message or attachments must be provided.');
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'chat_id.required' => 'Chat ID is required.',
            'chat_id.exists' => 'The selected chat does not exist.',
            'message.max' => 'Message must not exceed 4096 characters.',
            'attachments.max' => 'You can upload a maximum of 10 files.',
            'attachments.*.file' => 'Each attachment must be a valid file.',
            'attachments.*.max' => 'Each file must not exceed 10MB.',
            'attachments.*.mimetypes' => 'Invalid file type. Allowed types: images, videos, audio, and documents.',
        ];
    }
}
