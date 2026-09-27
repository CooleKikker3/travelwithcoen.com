<?php

namespace App\Support;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;

/**
 * Wraps the file translation loader and applies the CMS overrides from SiteTexts.
 */
class DatabaseOverridesLoader implements Loader
{
    public function __construct(private Loader $files) {}

    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->files->load($locale, $group, $namespace);

        if (($namespace === null || $namespace === '*') && in_array($group, SiteTexts::GROUPS, true)) {
            foreach (SiteTexts::overrides() as $key => $values) {
                if (str_starts_with($key, "{$group}.") && filled($values[$locale] ?? null)) {
                    Arr::set($lines, substr($key, strlen($group) + 1), $values[$locale]);
                }
            }
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint)
    {
        $this->files->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->files->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->files->namespaces();
    }
}
