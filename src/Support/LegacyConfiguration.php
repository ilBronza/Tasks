<?php

namespace IlBronza\Tasks\Support;

class LegacyConfiguration
{
    public static function register(): void
    {
        $legacy = [];
        foreach (['routePrefixTasks' => 'routePrefix', 'defaultRoles' => 'defaultRoles',
            'conversation' => 'conversation', 'models.task' => 'models.task'] as $old => $new) {
            if (config()->has('notes.' . $old))
                data_set($legacy, $new, config('notes.' . $old));
        }

        // Published tasks.php wins over published notes.php, including role lists.
        $defaults = require __DIR__ . '/../../config/tasks.php';
        $settings = static::merge(static::merge($defaults, $legacy), config('tasks', []));
        config(['tasks' => $settings]);

        // Keep application code that reads the former keys working.
        config([
            'notes.routePrefixTasks' => $settings['routePrefix'],
            'notes.models.task' => $settings['models']['task'],
            'notes.conversation' => $settings['conversation'],
        ]);
    }

    private static function merge(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            $base[$key] = is_array($value) && !array_is_list($value) && is_array($base[$key] ?? null)
                ? static::merge($base[$key], $value) : $value;
        }

        return $base;
    }
}
