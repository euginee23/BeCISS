<?php

namespace App\Concerns;

use App\Models\Resident;
use Illuminate\Validation\Rule;

/**
 * The extended resident profile (personal details, education, employment and
 * sector classification) shared by every form that edits a resident.
 */
trait ResidentDetailFields
{
    public string $place_of_birth = '';

    public string $citizenship = 'Filipino';

    public string $religion = '';

    public string $blood_type = '';

    public string $educational_attainment = '';

    public string $employment_status = '';

    public bool $is_pwd = false;

    public string $pwd_id_number = '';

    public bool $is_solo_parent = false;

    public bool $is_4ps_beneficiary = false;

    public bool $is_indigenous = false;

    public bool $is_ofw = false;

    public bool $is_out_of_school_youth = false;

    /**
     * @return array<string, mixed>
     */
    protected function residentDetailRules(): array
    {
        return [
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'citizenship' => ['required', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'blood_type' => ['nullable', Rule::in(Resident::BLOOD_TYPES)],
            'educational_attainment' => ['nullable', Rule::in(array_keys(Resident::EDUCATION_LEVELS))],
            'employment_status' => ['nullable', Rule::in(array_keys(Resident::EMPLOYMENT_STATUSES))],
            'is_pwd' => ['boolean'],
            'pwd_id_number' => ['nullable', 'string', 'max:50'],
            'is_solo_parent' => ['boolean'],
            'is_4ps_beneficiary' => ['boolean'],
            'is_indigenous' => ['boolean'],
            'is_ofw' => ['boolean'],
            'is_out_of_school_youth' => ['boolean'],
        ];
    }

    /**
     * Rules for the civil status select, shared so every form accepts the same values.
     *
     * @return list<mixed>
     */
    protected function civilStatusRules(): array
    {
        return ['required', Rule::in(array_keys(Resident::CIVIL_STATUSES))];
    }

    protected function fillResidentDetails(Resident $resident): void
    {
        $this->place_of_birth = $resident->place_of_birth ?? '';
        $this->citizenship = $resident->citizenship ?: 'Filipino';
        $this->religion = $resident->religion ?? '';
        $this->blood_type = $resident->blood_type ?? '';
        $this->educational_attainment = $resident->educational_attainment ?? '';
        $this->employment_status = $resident->employment_status ?? '';
        $this->is_pwd = (bool) $resident->is_pwd;
        $this->pwd_id_number = $resident->pwd_id_number ?? '';
        $this->is_solo_parent = (bool) $resident->is_solo_parent;
        $this->is_4ps_beneficiary = (bool) $resident->is_4ps_beneficiary;
        $this->is_indigenous = (bool) $resident->is_indigenous;
        $this->is_ofw = (bool) $resident->is_ofw;
        $this->is_out_of_school_youth = (bool) $resident->is_out_of_school_youth;
    }

    /**
     * The detail fields ready to save, with blanks stored as null.
     *
     * @return array<string, mixed>
     */
    protected function residentDetailAttributes(): array
    {
        return [
            'place_of_birth' => $this->place_of_birth ?: null,
            'citizenship' => $this->citizenship ?: 'Filipino',
            'religion' => $this->religion ?: null,
            'blood_type' => $this->blood_type ?: null,
            'educational_attainment' => $this->educational_attainment ?: null,
            'employment_status' => $this->employment_status ?: null,
            'is_pwd' => $this->is_pwd,
            'pwd_id_number' => $this->is_pwd ? ($this->pwd_id_number ?: null) : null,
            'is_solo_parent' => $this->is_solo_parent,
            'is_4ps_beneficiary' => $this->is_4ps_beneficiary,
            'is_indigenous' => $this->is_indigenous,
            'is_ofw' => $this->is_ofw,
            'is_out_of_school_youth' => $this->is_out_of_school_youth,
        ];
    }
}
