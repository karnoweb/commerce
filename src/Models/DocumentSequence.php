<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

/**
 * Per-(key, branch, year) counter backing the default sequential
 * order/invoice number generators. Owned entirely by this package —
 * scope_branch_id is a soft host key, never a hard FK.
 */
class DocumentSequence extends BaseModel
{
    protected $fillable = [
        'key',
        'scope_branch_id',
        'scope_year',
        'current_number',
    ];

    protected function casts(): array
    {
        return [
            'scope_year' => 'integer',
            'current_number' => 'integer',
        ];
    }
}
