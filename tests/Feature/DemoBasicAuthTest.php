<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoBasicAuthTest extends TestCase
{
    public function testAiGrowthDoctorRouteDoesNotRequireAuthWhenDemoAuthIsDisabled()
    {
        config(['demo.auth_enabled' => false]);

        $response = $this->get('/ai-growth-doctor?no_auto=1');

        $response->assertOk();
    }

    public function testAiGrowthDoctorRouteRequiresAuthWhenDemoAuthIsEnabled()
    {
        config([
            'demo.auth_enabled' => true,
            'demo.auth_user' => 'judge',
            'demo.auth_password' => 'secret',
            'demo.auth_realm' => 'AI Growth Doctor Demo',
        ]);

        $response = $this->get('/ai-growth-doctor?no_auto=1');

        $response->assertStatus(401);
        $response->assertHeader('WWW-Authenticate', 'Basic realm="AI Growth Doctor Demo"');
        $this->assertSame('Authentication required.', $response->getContent());
    }

    public function testAiGrowthDoctorRouteRejectsWrongCredentials()
    {
        config([
            'demo.auth_enabled' => true,
            'demo.auth_user' => 'judge',
            'demo.auth_password' => 'secret',
            'demo.auth_realm' => 'AI Growth Doctor Demo',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Basic ' . base64_encode('judge:wrong'),
        ])->get('/ai-growth-doctor?no_auto=1');

        $response->assertStatus(401);
    }

    public function testAiGrowthDoctorRouteAcceptsCorrectCredentials()
    {
        config([
            'demo.auth_enabled' => true,
            'demo.auth_user' => 'judge',
            'demo.auth_password' => 'secret',
            'demo.auth_realm' => 'AI Growth Doctor Demo',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Basic ' . base64_encode('judge:secret'),
        ])->get('/ai-growth-doctor?no_auto=1');

        $response->assertOk();
    }

    public function testUnrelatedRootRouteIsNotProtected()
    {
        config([
            'demo.auth_enabled' => true,
            'demo.auth_user' => 'judge',
            'demo.auth_password' => 'secret',
        ]);

        $response = $this->get('/');

        $response->assertRedirect('/ai-growth-doctor');
    }
}
