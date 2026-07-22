<?php

namespace App\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class BulkImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv',
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel',
                'max:'.(int) config('imports.max_upload_kilobytes', 10_240),
            ],
        ];
    }

    public function csv(): UploadedFile
    {
        $file = $this->file('file');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => 'A CSV file is required.']);
        }

        return $file;
    }
}
