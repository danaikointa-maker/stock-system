<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\EnvService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_step1_shows_requirements(): void
    {
        $this->get(route('setup.wizard'))
            ->assertOk()
            ->assertSee('ตรวจสอบระบบ')
            ->assertSee('PHP Version');
    }

    public function test_step2_shows_database_options(): void
    {
        $this->get(route('setup.database'))
            ->assertOk()
            ->assertSee('SQLite')
            ->assertSee('MySQL');
    }

    public function test_step2_save_sqlite(): void
    {
        $this->post(route('setup.saveDatabase'), [
            'driver' => 'sqlite',
        ])->assertRedirect(route('setup.app'));
    }

    public function test_step3_shows_app_config(): void
    {
        $this->get(route('setup.app'))
            ->assertOk()
            ->assertSee('ตั้งค่าแอป')
            ->assertSee('URL ของเว็บ');
    }

    public function test_step3_save_app_config(): void
    {
        $this->post(route('setup.saveAppConfig'), [
            'name'     => 'MyShop',
            'url'      => 'https://myshop.com',
            'timezone' => 'Asia/Bangkok',
            'debug'    => 'false',
        ])->assertRedirect(route('setup.admin'));
    }

    public function test_step4_shows_admin_form(): void
    {
        $this->get(route('setup.admin'))
            ->assertOk()
            ->assertSee('สร้างผู้ดูแลระบบ');
    }

    public function test_step4_save_admin(): void
    {
        $this->post(route('setup.saveAdmin'), [
            'name'                  => 'Admin Test',
            'email'                 => 'test@demo.test',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('setup.social'));
    }

    public function test_step5_shows_social_login(): void
    {
        $this->get(route('setup.social'))
            ->assertOk()
            ->assertSee('LINE')
            ->assertSee('Google');
    }

    public function test_step6_shows_install_page(): void
    {
        $this->get(route('setup.install'))
            ->assertOk()
            ->assertSee('ติดตั้งระบบ');
    }

    public function test_env_service_reads_and_writes(): void
    {
        $env = new EnvService();

        // อ่านค่า
        $name = $env->get('APP_NAME');
        $this->assertNotNull($name);

        // เขียนค่า (ใช้ temporary file)
        $env->set(['APP_NAME' => 'TestApp']);
        $this->assertEquals('TestApp', $env->get('APP_NAME'));

        // คืนค่าเดิม
        $env->set(['APP_NAME' => $name]);
    }

    public function test_setting_model_crud(): void
    {
        Setting::put('test_key', 'test_value', 'test');
        $this->assertEquals('test_value', Setting::val('test_key'));

        Setting::put('test_key', 'updated', 'test');
        $this->assertEquals('updated', Setting::val('test_key'));

        $this->assertEquals('default', Setting::val('nonexistent', 'default'));
    }
}
