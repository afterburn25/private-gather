<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

final class RouteActionIntegrityTest extends TestCase
{
    public function test_every_registered_controller_action_resolves_to_a_real_public_method(): void
    {
        $failures = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if ($action === 'Closure' || ! str_contains($action, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $action, 2);
            if (! class_exists($class)) {
                $failures[] = $route->uri().' -> missing controller '.$class;
                continue;
            }
            if (! method_exists($class, $method)) {
                $failures[] = $route->uri().' -> missing action '.$class.'::'.$method;
                continue;
            }

            $reflection = new ReflectionMethod($class, $method);
            if (! $reflection->isPublic()) {
                $failures[] = $route->uri().' -> action is not public '.$class.'::'.$method;
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }
}
