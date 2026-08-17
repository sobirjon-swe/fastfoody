<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel'ning test mijozi sukut boʻyicha `Accept-Language: en-us`
     * yuboradi, yaʼni har bir test inglizzabon brauzerdek koʻrinardi. Ilovaning
     * asosiy auditoriyasi oʻzbek tilida, shuning uchun testlar ham shu tilda
     * yuradi; tilni tekshiradigan testlar sarlavhani oʻzi almashtiradi.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'uz');
    }
}
