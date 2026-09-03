<?php

namespace App\Actions\Fortify;

use App\Concerns\CapitalizesWords;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use CapitalizesWords, PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * The name is collected in parts so the resident profile form can be
     * pre-filled; `name` is stored as the composed display value.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->registrationNameRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $firstName = self::capitalizeWords($input['first_name']);
        $middleName = self::capitalizeWords($input['middle_name'] ?? null) ?: null;
        $lastName = self::capitalizeWords($input['last_name']);
        $suffix = trim($input['suffix'] ?? '') ?: null;

        return User::create([
            'name' => implode(' ', array_filter([$firstName, $middleName, $lastName, $suffix])),
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'suffix' => $suffix,
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
