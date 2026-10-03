<?php

declare(strict_types=1);

namespace Modules\SchemaManager\Services;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/** فهرست Modelهای Eloquent پروژه و جدول هرکدام (برای اطمینان از پوشش baseline) */
final class SchemaModels
{
    /** @return array<class-string,string> کلاس → نام جدول */
    public static function tables(): array
    {
        $files = array_merge(
            glob(base_path('app/Models/*.php')) ?: [],
            glob(base_path('Modules/*/app/Models/*.php')) ?: [],
        );

        $out = [];
        foreach ($files as $file) {
            $rel = str_replace('\\', '/', substr($file, strlen(rtrim(base_path(), '/\\')) + 1));
            if (preg_match('#^Modules/([^/]+)/app/Models/(.+)\.php$#', $rel, $m) === 1) {
                $class = "Modules\\{$m[1]}\\Models\\" . str_replace('/', '\\', $m[2]);
            } elseif (preg_match('#^app/Models/(.+)\.php$#', $rel, $m) === 1) {
                $class = 'App\\Models\\' . str_replace('/', '\\', $m[1]);
            } else {
                continue;
            }

            try {
                if (! class_exists($class) || ! is_subclass_of($class, Model::class) || (new \ReflectionClass($class))->isAbstract()) {
                    continue;
                }
                $out[$class] = (new $class())->getTable();
            } catch (Throwable) {
                continue;
            }
        }
        ksort($out);

        return $out;
    }
}