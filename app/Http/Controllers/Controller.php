<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

abstract class Controller
{
    /**
     * Operator LIKE yang case-insensitive sesuai driver database.
     * PostgreSQL memakai ILIKE; SQLite/MySQL memakai LIKE.
     */
    protected function likeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * Aturan validasi sorting yang dipakai semua endpoint list.
     *
     * @param  array<int, string>  $allowed
     * @return array<string, mixed>
     */
    protected function sortRules(array $allowed): array
    {
        return [
            'sort' => ['nullable', 'array', 'max:5'],
            'sort.*.column' => ['required', 'string', 'distinct', Rule::in($allowed)],
            'sort.*.direction' => ['required', 'in:asc,desc'],
            'sort_by' => ['nullable', Rule::in($allowed)],
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ];
    }

    /**
     * Terapkan satu atau beberapa kolom sorting.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<int, string>  $allowed
     * @param  array<int, string>  $tieBreakers
     */
    protected function applySort(
        Builder $query,
        array $validated,
        array $allowed,
        string $defaultColumn,
        string $defaultDirection = 'asc',
        array $tieBreakers = [],
    ): Builder {
        $applied = [];
        $sorts = $validated['sort'] ?? null;

        if (! is_array($sorts) || $sorts === []) {
            $sorts = [[
                'column' => $validated['sort_by'] ?? $defaultColumn,
                'direction' => $validated['sort_direction'] ?? $defaultDirection,
            ]];
        }

        foreach ($sorts as $sort) {
            $column = $sort['column'] ?? null;
            $direction = $sort['direction'] ?? 'asc';

            if (! is_string($column)
                || ! in_array($column, $allowed, true)
                || in_array($column, $applied, true)
            ) {
                continue;
            }

            $query->orderBy($column, $direction === 'desc' ? 'desc' : 'asc');
            $applied[] = $column;
        }

        if ($applied === []) {
            $query->orderBy($defaultColumn, $defaultDirection === 'desc' ? 'desc' : 'asc');
            $applied[] = $defaultColumn;
        }

        // Tie-breaker unik agar urutan antar halaman tetap stabil.
        foreach ($tieBreakers as $tieBreaker) {
            if (! in_array($tieBreaker, $applied, true)) {
                $query->orderBy($tieBreaker);
                $applied[] = $tieBreaker;
            }
        }

        return $query;
    }
}
