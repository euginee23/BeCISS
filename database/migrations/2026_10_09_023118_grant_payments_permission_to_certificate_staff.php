<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Payments used to be recorded by whoever could process certificates, so
     * those staff keep that ability under the new cashier permission.
     */
    public function up(): void
    {
        $this->eachStaff(function (array $permissions): array {
            if (in_array('certificates', $permissions, true) && ! in_array('payments', $permissions, true)) {
                $permissions[] = 'payments';
            }

            return $permissions;
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->eachStaff(fn (array $permissions): array => array_values(array_diff($permissions, ['payments'])));
    }

    /**
     * @param  callable(list<string>): list<string>  $transform
     */
    private function eachStaff(callable $transform): void
    {
        DB::table('users')->where('role', 'staff')->orderBy('id')->each(function (object $user) use ($transform): void {
            $permissions = json_decode($user->permissions ?? '[]', true) ?: [];

            DB::table('users')->where('id', $user->id)->update([
                'permissions' => json_encode($transform($permissions)),
            ]);
        });
    }
};
