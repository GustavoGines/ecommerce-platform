<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use \Tests\Traits\CreatesTenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setUpTenancy();
    }
}
