<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'title', 'slug', 'icon', 'image', 'image_alt', 'short_description', 'description',
        'what_we_do', 'suitable_for', 'process_steps', 'faqs',
        'meta_title', 'meta_description', 'is_featured', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'what_we_do' => 'array',
            'suitable_for' => 'array',
            'process_steps' => 'array',
            'faqs' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function getUrlAttribute(): string
    {
        return url('/'.$this->slug);
    }

    public function getImageUrlAttribute(): string
    {
        return media_url($this->image, asset('images/placeholder.svg'));
    }

    public function getIconClassAttribute(): string
    {
        return $this->icon ?: 'fa-solid fa-screwdriver-wrench';
    }
}
