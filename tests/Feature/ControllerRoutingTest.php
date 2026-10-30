<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ControllerRoutingTest extends TestCase
{
    #[Test]
    public function api_routes_resolve_to_controllers_in_the_api_namespace(): void
    {
        foreach (Route::getRoutes() as $route) {
            $controller = $route->getAction('controller');

            if (! str_starts_with($route->uri(), 'api/') || ! is_string($controller) || ! str_starts_with($controller, 'App\\Http\\Controllers\\')) {
                continue;
            }

            [$class, $method] = array_pad(explode('@', $controller), 2, '__invoke');
            $this->assertStringStartsWith('App\\Http\\Controllers\\Api\\', $class, $route->uri());
            $this->assertTrue(class_exists($class), $class.' must be autoloadable.');
            $this->assertTrue(method_exists($class, $method), $controller.' must exist.');
        }
    }

    #[Test]
    public function web_routes_resolve_to_controllers_in_the_root_namespace(): void
    {
        foreach (Route::getRoutes() as $route) {
            $controller = $route->getAction('controller');

            if (str_starts_with($route->uri(), 'api/') || ! is_string($controller) || ! str_starts_with($controller, 'App\\Http\\Controllers\\')) {
                continue;
            }

            [$class, $method] = array_pad(explode('@', $controller), 2, '__invoke');
            $this->assertSame('App\\Http\\Controllers', (new \ReflectionClass($class))->getNamespaceName(), $route->uri());
            $this->assertTrue(method_exists($class, $method), $controller.' must exist.');
        }
    }
}
