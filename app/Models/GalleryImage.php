<?php

namespace App\Models;

use App\Enums\GalleryItemType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class GalleryImage extends Model
{
    protected $fillable = [
        'is_active',
        'item_type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected $appends = [
        'url',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'item_type' => GalleryItemType::class,
            'size' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function equipmentProps(): BelongsToMany
    {
        return $this->belongsToMany(
            EquipmentProp::class,
            'equipment_prop_gallery_image',
            'gallery_image_id',
            'equipment_prop_id'
        )->withTimestamps();
    }
}
