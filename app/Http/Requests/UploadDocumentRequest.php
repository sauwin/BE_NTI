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
                            'application/pdf',                                                         // .pdf
                            'application/msword',                                                      // .doc
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',  // .docx
                            'application/vnd.ms-excel',                                                // .xls
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',         // .xlsx
                            'application/vnd.ms-powerpoint',                                           // .ppt
                            'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
                            'image/png',                                                               // .png
                            'image/jpeg',                                                              // .jpg, .jpeg
                        ];

                        if (!in_array($realMimeType, $allowedMimes)) {
                            $fail('Súbor má neplatný skutočný MIME typ (' . $realMimeType . '), aj keď prípona názvu hovorí inak.');
                        }
                    }
                },

                function ($attribute, $value, $fail) {
                    if (env('CLAMAV_SKIP_VALIDATION', true) === true) {
                        return;
                    }

                    if ($value && $value->isValid()) {
                        try {
                            $host = env('CLAMAV_REMOTE_HOST', '127.0.0.1');
                            $port = env('CLAMAV_REMOTE_PORT', 3310);
                            
                            $socket = @fsockopen($host, $port, $errno, $errstr, 2);
                            if (!$socket) {
                                \Log::error("ClamAV connection failed: $errstr ($errno)");
                                $fail('Antivírusový server je momentálne nedostupný.');
                                return;
                            }

                            fwrite($socket, "SCAN " . $value->getRealPath() . "\n");
                            $response = fgets($socket, 1024);
                            fclose($socket);

                            if (str_contains($response, 'FOUND')) {
                                $fail('Súbor bol zablokovaný antivírusom z dôvodu bezpečnostného rizika.');
                            }
                        } catch (\Exception $e) {
                            \Log::error("ClamAV scan error: " . $e->getMessage());
                            $fail('Chyba pri antivírusovej kontrole súboru.');
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
            'file.required' => 'Vyberte súbor na nahranie.',
            'file.file' => 'Odoslaný objekt musí byť platný súbor.',
            'file.max' => 'Maximálna veľkosť súboru je 20 MB.',
        ];
    }
}