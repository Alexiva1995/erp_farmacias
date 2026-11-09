<?php

namespace App\Models;

use App\Enums\KitchenWasteReasonEnum;
use App\Enums\KitchenWasteStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class KitchenWaste extends Model
{
    use HasFactory;

    protected $table = 'kitchen_wastes';

    protected $fillable = [
        'user_id',
        'kitchen_station_id',
        'item_type',
        'product_id',
        'dish_id',
        'lot_id',
        'quantity',
        'unit_cost',
        'total_loss_usd',
        'reason',
        'notes',
        'status',
        'photo_path',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_loss_usd' => 'decimal:2',
        'reason' => KitchenWasteReasonEnum::class,
        'status' => KitchenWasteStatusEnum::class,
        'approved_at' => 'datetime',
        'occurred_at' => 'datetime',
    ];

    /**
     * Garantiza que las columnas de aprobación y foto existan en la base de datos activa.
     */
    public static function ensureApprovalColumns(): void
    {
        if (!Schema::hasTable('kitchen_wastes')) {
            return;
        }

        if (!Schema::hasColumn('kitchen_wastes', 'status') || !Schema::hasColumn('kitchen_wastes', 'photo_path')) {
            Schema::table('kitchen_wastes', function (Blueprint $table) {
                if (!Schema::hasColumn('kitchen_wastes', 'status')) {
                    $table->string('status', 20)->default('pending')->after('notes')->index();
                }
                if (!Schema::hasColumn('kitchen_wastes', 'photo_path')) {
                    $table->string('photo_path')->nullable()->after('status');
                }
                if (!Schema::hasColumn('kitchen_wastes', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('photo_path');
                }
                if (!Schema::hasColumn('kitchen_wastes', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by');
                }
                if (!Schema::hasColumn('kitchen_wastes', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('approved_at');
                }
            });
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function kitchenStation(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class, 'lot_id');
    }
}
