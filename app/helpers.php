<?php

use Illuminate\Database\Eloquent\Builder;

if (! function_exists('table_per_page')) {
    function table_per_page(): int
    {
        return (int) config('psg.pagination.per_page', 25);
    }
}

if (! function_exists('status_counts')) {
    /**
     * Single grouped COUNT query for status/filter cards.
     *
     * @param  Builder<*>|\Illuminate\Database\Query\Builder  $query
     * @return array<string, int>
     */
    function status_counts($query, string $column = 'status'): array
    {
        return $query
            ->clone()
            ->reorder()
            ->selectRaw($column.' as status_key, COUNT(*) as aggregate')
            ->groupBy($column)
            ->pluck('aggregate', 'status_key')
            ->map(fn ($count) => (int) $count)
            ->all();
    }
}
