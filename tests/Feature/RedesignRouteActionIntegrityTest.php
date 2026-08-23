<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

class RedesignRouteActionIntegrityTest extends TestCase
{
    public function test_every_registered_controller_action_resolves_to_a_public_method(): void
    {
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue;
            }

            [$controller, $method] = explode('@', $action, 2);
            $this->assertTrue(class_exists($controller), "Missing route controller {$controller} for {$route->uri()}");
            $this->assertTrue(method_exists($controller, $method), "Missing route action {$controller}::{$method} for {$route->uri()}");
            $this->assertTrue((new ReflectionMethod($controller, $method))->isPublic(), "Route action {$controller}::{$method} must be public");
        }
    }

    public function test_redesign_group_routes_are_registered(): void
    {
        foreach ([
            'community.groups.index',
            'community.groups.store',
            'community.groups.show',
            'community.groups.join',
            'community.groups.leave',
            'community.groups.posts.store',
            'community.groups.members.approve',
        ] as $name) {
            $this->assertTrue(Route::has($name), "Missing redesign route {$name}");
        }
    }
}
