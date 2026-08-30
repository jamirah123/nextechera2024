<?php

if (! function_exists('table_per_page')) {
    function table_per_page(): int
    {
        return (int) config('psg.pagination.per_page', 25);
    }
}
