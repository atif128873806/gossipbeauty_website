<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordTest extends TestCase
{
    public function test_owner_can_replace_temporary_password(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'password' => 'OldTemporaryPass123!']);

        $this->actingAs($owner)->put(route('admin.profile.password'), [
            'current_password' => 'OldTemporaryPass123!',
            'password' => 'NewUniquePassword456!',
            'password_confirmation' => 'NewUniquePassword456!',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertTrue(Hash::check('NewUniquePassword456!', $owner->fresh()->password));
    }

    public function test_wrong_current_password_does_not_change_access(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'password' => 'OldTemporaryPass123!']);

        $this->actingAs($owner)->put(route('admin.profile.password'), [
            'current_password' => 'wrong-password',
            'password' => 'NewUniquePassword456!',
            'password_confirmation' => 'NewUniquePassword456!',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('OldTemporaryPass123!', $owner->fresh()->password));
    }
}
