<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/**
 * History of PDF / Excel / CSV / JSON exports downloaded by the student.
 */
class ExportLog extends Model
{
    use BelongsToUser;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'created_at' => 'datetime',
            'row_count' => 'integer',
            'file_size' => 'integer',
        ];
    }
}
