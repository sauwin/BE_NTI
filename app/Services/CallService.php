<?php

namespace App\Services;

use App\Models\Call;
use App\Models\Program;
use App\Services\AuditService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class CallService
{
    public function active(?string $program_type = null)
    {
        $query = Call::where('status', 'open');

        if ($program_type) {
            $program_type = strtolower(trim($program_type));
            $query->where('program', $program_type);
        }

        return $query->orderBy('deadline_at')->first();
    }

    public function index()
    {
        return Call::orderByDesc('created_at')->get();
    }

    public function create(array $data, $userId = null)
    {
        $documents = $data['form_config'] ?? $data['required_documents'] ?? [];
        if (is_string($documents)) {
            $documents = json_decode($documents, true) ?? [];
        }

        if (is_array($documents)) {
            Validator::make(
                ['documents' => $documents],
                [
                    'documents' => 'array',
                    'documents.*.max_size_mb' => 'nullable|numeric',
                    'documents.*.is_mandatory' => 'required|boolean',
                    'documents.*.document_name' => 'required|string',
                    'documents.*.type' => 'nullable|string',
                ]
            )->validate();

            foreach ($documents as &$document) {
                if (empty($document['type'])) {
                    $document['type'] = Str::snake($document['document_name']);
                }
            }
        }

        $callName = $data['title'] ?? $data['name'] ?? 'Bez názvu';

        $call = Call::create([
            'program' => $data['program_type'],
            'name' => $callName,
            'status' => $data['status'] ?? 'draft',
            'opens_at' => !empty($data['opens_at']) ? now()->parse($data['opens_at']) : null,
            'deadline_at' => !empty($data['deadline_at']) ? now()->parse($data['deadline_at']) : null,
            'min_team_size' => $data['min_team_size'] ?? 1,
            'max_team_size' => $data['max_team_size'] ?? null,
            'evaluation_criteria' => $data['evaluation_criteria'] ?? [],
            'required_documents' => $documents,
            'created_by' => $userId ?? 1,
        ]);

        $call->label = $callName;

        AuditService::log('create_call', 'call', [
            'call_id' => $call->id,
            'program_type' => $data['program_type'] ?? null,
            'name' => $callName,
        ]);

        return $call;
    }

    public function update(int $id, array $data)
    {
        $call = Call::findOrFail($id);

        if (isset($data['program_type'])) {
            $call->program = $data['program_type'];
        }

        if (isset($data['title'])) {
            $call->name = $data['title'];
        }

        if (isset($data['max_team_size']) && $data['max_team_size'] !== null) {
            $min = $data['min_team_size'] ?? $call->min_team_size;
            if ($data['max_team_size'] < $min) {
                throw new \InvalidArgumentException('Max team size cannot be less than min team size.');
            }
            $call->max_team_size = $data['max_team_size'];
        } elseif (array_key_exists('max_team_size', $data)) {
            $call->max_team_size = null;
        }

        $documents = $data['form_config'] ?? null;
        if (is_string($documents)) {
            $documents = json_decode($documents, true) ?? [];
        } elseif (isset($data['required_documents'])) {
            $documents = $data['required_documents'];
        }

        if (is_array($documents)) {
            Validator::make(
                ['documents' => $documents],
                [
                    'documents' => 'array',
                    'documents.*.max_size_mb' => 'nullable|numeric',
                    'documents.*.is_mandatory' => 'required|boolean',
                    'documents.*.document_name' => 'required|string',
                    'documents.*.type' => 'nullable|string',
                ]
            )->validate();

            foreach ($documents as &$document) {
                if (is_array($document) && empty($document['type']) && !empty($document['document_name'])) {
                    $document['type'] = Str::snake($document['document_name']);
                }
            }
        }

        if ($documents !== null) {
            $call->required_documents = $documents;
        }

        if (isset($data['min_team_size'])) {
            $call->min_team_size = $data['min_team_size'];
        }
        if (isset($data['status'])) {
            $call->status = $data['status'];
        }
        if (array_key_exists('opens_at', $data)) {
            $call->opens_at = $data['opens_at'] ? now()->parse($data['opens_at']) : null;
        }
        if (array_key_exists('deadline_at', $data)) {
            $call->deadline_at = $data['deadline_at'] ? now()->parse($data['deadline_at']) : null;
        }

        $call->save();

        return $call;
    }

    public function delete(int $id)
    {
        $call = Call::findOrFail($id);

        if ($call->status !== 'draft') {
            throw new \DomainException('Only draft calls can be deleted.');
        }

        $callId = $call->id;
        $call->delete();

        AuditService::log('delete_call', 'call', [
            'call_id' => $callId,
        ]);

        return true;
    }

    public function find(int $id)
    {
        return Call::findOrFail($id);
    }
}
