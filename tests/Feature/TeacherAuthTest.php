<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login guru, proteksi akses, dan authorization role.
 */
class TeacherAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTeacher(): User
    {
        return User::factory()->create([
            'email' => 'guru@example.com',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
        ]);
    }

    public function test_guru_dapat_login_dengan_kredensial_benar(): void
    {
        $teacher = $this->makeTeacher();

        $response = $this->post('/login', [
            'email' => 'guru@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('teacher.dashboard'));
        $this->assertAuthenticatedAs($teacher);
    }

    public function test_login_gagal_dengan_password_salah(): void
    {
        $this->makeTeacher();

        $response = $this->from('/login')->post('/login', [
            'email' => 'guru@example.com',
            'password' => 'password-salah',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_tidak_disimpan_sebagai_plaintext(): void
    {
        $teacher = $this->makeTeacher();

        $this->assertNotSame('password', $teacher->password);
        $this->assertTrue(password_verify('password', $teacher->password));
    }

    public function test_tamu_diarahkan_ke_login_saat_membuka_dashboard_guru(): void
    {
        $response = $this->get('/teacher');

        $response->assertRedirect(route('login'));
    }

    public function test_siswa_tidak_dapat_membuka_dashboard_guru(): void
    {
        // Buat user dengan role student (bukan guru).
        $student = User::factory()->create([
            'role' => User::ROLE_STUDENT,
        ]);

        $response = $this->actingAs($student)->get('/teacher');

        $response->assertStatus(403);
    }

    public function test_guru_dapat_logout(): void
    {
        $teacher = $this->makeTeacher();

        $response = $this->actingAs($teacher)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
