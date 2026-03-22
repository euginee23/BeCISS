<?php

use App\Models\BarangayProfile;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Barangay Settings')]
#[Layout('layouts::app')]
class extends Component
{
    public string $barangayName = '';
    public string $municipality = '';
    public string $province = '';
    public string $zipCode = '';
    public string $address = '';
    public string $phone = '';
    public string $email = '';
    public string $website = '';
    public string $captainName = '';
    public string $secretaryName = '';
    public string $treasurerName = '';
    public string $officeHours = '';

    public function mount(): void
    {
        $profile = BarangayProfile::first();

        if ($profile) {
            $this->barangayName = $profile->barangay_name ?? '';
            $this->municipality = $profile->municipality ?? '';
            $this->province = $profile->province ?? '';
            $this->zipCode = $profile->zip_code ?? '';
            $this->address = $profile->address ?? '';
            $this->phone = $profile->phone ?? '';
            $this->email = $profile->email ?? '';
            $this->website = $profile->website ?? '';
            $this->captainName = $profile->captain_name ?? '';
            $this->secretaryName = $profile->secretary_name ?? '';
            $this->treasurerName = $profile->treasurer_name ?? '';
            $this->officeHours = $profile->office_hours ?? '';
        }
    }

    public function save(): void
    {
        $this->validate([
            'barangayName' => ['required', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'zipCode' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'captainName' => ['nullable', 'string', 'max:255'],
            'secretaryName' => ['nullable', 'string', 'max:255'],
            'treasurerName' => ['nullable', 'string', 'max:255'],
            'officeHours' => ['nullable', 'string', 'max:500'],
        ]);

        BarangayProfile::updateOrCreate([], [
            'barangay_name' => $this->barangayName,
            'municipality' => $this->municipality ?: null,
            'province' => $this->province ?: null,
            'zip_code' => $this->zipCode ?: null,
            'address' => $this->address ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'website' => $this->website ?: null,
            'captain_name' => $this->captainName ?: null,
            'secretary_name' => $this->secretaryName ?: null,
            'treasurer_name' => $this->treasurerName ?: null,
            'office_hours' => $this->officeHours ?: null,
        ]);

        $this->dispatch('saved');
    }
};
?>

<div class="flex flex-col gap-6 max-w-3xl">
    <div>
        <flux:heading size="xl">Barangay Settings</flux:heading>
        <flux:text class="text-zinc-500 dark:text-zinc-400 mt-1">
            Manage your barangay's information, contact details, and leadership. This information is used throughout the system.
        </flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">

        {{-- Basic Information --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 flex flex-col gap-5">
            <div class="flex items-center gap-3 pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <div class="size-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                    <flux:icon name="building-office-2" class="size-4 text-emerald-600 dark:text-emerald-400" />
                </div>
                <flux:heading>Basic Information</flux:heading>
            </div>

            <div class="grid gap-4">
                <flux:field>
                    <flux:label>Barangay Name <flux:badge size="sm" color="red" class="ml-1">Required</flux:badge></flux:label>
                    <flux:input wire:model="barangayName" placeholder="e.g. Barangay San Isidro" />
                    <flux:error name="barangayName" />
                </flux:field>

                <div class="grid sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Municipality / City</flux:label>
                        <flux:input wire:model="municipality" placeholder="e.g. Caloocan City" />
                        <flux:error name="municipality" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Province / Region</flux:label>
                        <flux:input wire:model="province" placeholder="e.g. Metro Manila" />
                        <flux:error name="province" />
                    </flux:field>
                </div>

                <div class="grid sm:grid-cols-3 gap-4">
                    <flux:field>
                        <flux:label>ZIP Code</flux:label>
                        <flux:input wire:model="zipCode" placeholder="e.g. 1400" />
                        <flux:error name="zipCode" />
                    </flux:field>
                    <flux:field class="sm:col-span-2">
                        <flux:label>Full Address</flux:label>
                        <flux:input wire:model="address" placeholder="Street address of the barangay hall" />
                        <flux:error name="address" />
                    </flux:field>
                </div>
            </div>
        </div>

        {{-- Contact Information --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 flex flex-col gap-5">
            <div class="flex items-center gap-3 pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <div class="size-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <flux:icon name="phone" class="size-4 text-blue-600 dark:text-blue-400" />
                </div>
                <flux:heading>Contact Information</flux:heading>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Phone Number</flux:label>
                    <flux:input wire:model="phone" type="tel" placeholder="e.g. 02-123-4567" />
                    <flux:error name="phone" />
                </flux:field>

                <flux:field>
                    <flux:label>Email Address</flux:label>
                    <flux:input wire:model="email" type="email" placeholder="barangay@example.gov.ph" />
                    <flux:error name="email" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>Website</flux:label>
                    <flux:input wire:model="website" placeholder="https://barangay.gov.ph" />
                    <flux:error name="website" />
                </flux:field>
            </div>
        </div>

        {{-- Leadership --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 flex flex-col gap-5">
            <div class="flex items-center gap-3 pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <div class="size-8 rounded-lg bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center">
                    <flux:icon name="user-group" class="size-4 text-teal-600 dark:text-teal-400" />
                </div>
                <flux:heading>Leadership</flux:heading>
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <flux:field>
                    <flux:label>Barangay Captain</flux:label>
                    <flux:input wire:model="captainName" placeholder="Full name" />
                    <flux:error name="captainName" />
                </flux:field>

                <flux:field>
                    <flux:label>Barangay Secretary</flux:label>
                    <flux:input wire:model="secretaryName" placeholder="Full name" />
                    <flux:error name="secretaryName" />
                </flux:field>

                <flux:field>
                    <flux:label>Barangay Treasurer</flux:label>
                    <flux:input wire:model="treasurerName" placeholder="Full name" />
                    <flux:error name="treasurerName" />
                </flux:field>
            </div>
        </div>

        {{-- Operations --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 flex flex-col gap-5">
            <div class="flex items-center gap-3 pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <div class="size-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                    <flux:icon name="clock" class="size-4 text-amber-600 dark:text-amber-400" />
                </div>
                <flux:heading>Operations</flux:heading>
            </div>

            <flux:field>
                <flux:label>Office Hours</flux:label>
                <flux:textarea wire:model="officeHours" placeholder="e.g. Monday - Friday, 8:00 AM - 5:00 PM&#10;Saturday, 8:00 AM - 12:00 PM" rows="3" />
                <flux:error name="officeHours" />
            </flux:field>
        </div>

        {{-- Save Button --}}
        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>Save Changes</span>
                <span wire:loading>Saving...</span>
            </flux:button>

            <x-action-message class="text-emerald-600 dark:text-emerald-400 text-sm font-medium" on="saved">
                Saved successfully.
            </x-action-message>
        </div>
    </form>
</div>
