<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    public const AVAILABLE = 'available';
    public const ON_LOAN = 'on_loan';
    public const NEEDS_CHECK = 'needs_check';

    protected $table = 'equipment';

    protected $fillable = ['name', 'asset_tag', 'status'];
}
