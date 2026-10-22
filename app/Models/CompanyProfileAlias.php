<?php

namespace App\Models;

use Database\Factories\CompanyProfileAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_profile_id', 'subdomain'])]
class CompanyProfileAlias extends Model
{
    /** @use HasFactory<CompanyProfileAliasFactory> */
    use HasFactory;

    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class);
    }
}
