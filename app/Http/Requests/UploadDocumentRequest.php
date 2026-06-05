<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }
    
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:20480',

                function ($attribute, $value, $fail) {
                    if ($value && $value->isValid()) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $realMimeType = finfo_file($finfo, $value->getRealPath());
                        finfo_close($finfo);

                        $allowedMimes = [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-powerpoint',
                            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                            'image/png',
                            'image/jpeg',
                            'application/zip', // ← .docx/.xlsx/.pptx
                        ];

                        if (! in_array($realMimeType, $allowedMimes)) {
                            $fail('The file has an invalid actual MIME type ('.$realMimeType.'), even though the file extension suggests otherwise.');
                        }
                    }
                },

                function ($attribute, $value, $fail) {
                    if (filter_var(env('CLAMAV_SKIP_VALIDATION', true), FILTER_VALIDATE_BOOLEAN)) {
                        return;
                    }

                    if ($value && $value->isValid()) {
                        try {
                            $host = env('CLAMAV_REMOTE_HOST', '127.0.0.1');
                            $port = env('CLAMAV_REMOTE_PORT', 3310);

                            $socket = @fsockopen($host, $port, $errno, $errstr, 2);
                            if (! $socket) {
                                \Log::error("ClamAV connection failed: $errstr ($errno)");
                                $fail('The antivirus server is currently unavailable.');
                                return;
                            }

                            fwrite($socket, 'SCAN '.$value->getRealPath()."\n");
                            $response = fgets($socket, 1024);
                            fclose($socket);

                            if (str_contains($response, 'FOUND')) {
                                $fail('The file was blocked by the antivirus software due to a security risk.');
                            }
                        } catch (\Exception $e) {
                            \Log::error('ClamAV scan error: '.$e->getMessage());
                            $fail('Error during the antivirus scan of the file.');
                        }
                    }
                },
            ],
            'type' => 'required|string|max:100',
            'classification' => 'in:public,internal,confidential',
            'application_id' => 'required_without:task_id|integer|exists:applications,id',
            'task_id' => 'required_without:application_id|integer|exists:tasks,id',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Select the file you want to upload.',
            'file.file' => 'The uploaded file must be a valid file.',
            'file.max' => 'The maximum file size is 20 MB.',
        ];
    }
}