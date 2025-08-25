<?php

namespace EmranAlhaddad\ContentSync\Services;

class DiffService
{
    /** @return array<string,array{current:mixed,incoming:mixed}> */
    public function diffArrays(array $current, array $incoming): array
    {
        $keys = array_values(array_unique(array_merge(array_keys($current), array_keys($incoming))));
        $diff = [];
        foreach ($keys as $k) {
            $cv = $current[$k] ?? null;
            $iv = $incoming[$k] ?? null;
            if ($cv === $iv) continue;
            $diff[$k] = ['current' => $cv, 'incoming' => $iv];
        }
        return $diff;
    }
}
