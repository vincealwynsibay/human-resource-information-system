<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCaseWithSeed extends TestCase
{

    use RefreshDatabase;

    protected $seed = true;
}