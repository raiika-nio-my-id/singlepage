<?php

namespace App\Http\Resources;

use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanyProfile
 */
class AdminClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['user', 'aliases']);

        return [
            'id' => $this->id,
            'subdomain' => $this->subdomain,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'status' => $this->status->value,
            'expires_at' => $this->expires_at?->toDateString(),
            'hide_footer' => (bool) $this->hide_footer,
            'is_pinned' => (bool) $this->is_pinned,
            'sort_order' => (int) $this->sort_order,
            'html' => $this->html,
            'aliases' => $this->aliases
                ->pluck('subdomain')
                ->values()
                ->all(),
            'public_url' => $this->publicUrl(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
