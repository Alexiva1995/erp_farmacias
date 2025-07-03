<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donation extends Model
{
    use HasFactory;
    protected $fillable = ['institution_name'];

    public function donativeLogs(): HasMany
    {
        return $this->hasMany(DonativeLog::class);
    }
}
