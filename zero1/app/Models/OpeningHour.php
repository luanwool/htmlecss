<?php

namespace App\Models;

use App\Support\BusinessTime;
use Illuminate\Database\Eloquent\Model;

class OpeningHour extends Model
{
    protected $fillable = ['weekday', 'is_closed', 'opens_at', 'closes_at'];

    protected function casts(): array
    {
        return ['is_closed' => 'boolean', 'weekday' => 'integer'];
    }

    public function dayName(): string
    {
        return BusinessTime::weekdayName($this->weekday);
    }

    public function label(): string
    {
        if ($this->is_closed || ! $this->opens_at || ! $this->closes_at) {
            return 'Fechado';
        }

        return substr($this->opens_at, 0, 5).' – '.substr($this->closes_at, 0, 5);
    }
}
