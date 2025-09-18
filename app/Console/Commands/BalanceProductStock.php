<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Lot;
use App\Models\ProductLot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BalanceProductStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:balance-stock {--chunk=100 : Number of products to process at once} {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Balance product stock by creating missing lots when stock field is higher than sum of lot quantities';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $chunkSize = $this->option('chunk');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting product stock balancing...');

        $totalProducts = Product::count();
        $processedProducts = 0;
        $balancedProducts = 0;
        $skippedProducts = 0;
        $createdLots = 0;

        $this->info("Processing {$totalProducts} products in chunks of {$chunkSize}...");

        $progressBar = $this->output->createProgressBar($totalProducts);
        $progressBar->start();

        try {
            if (!$isDryRun) {
                DB::beginTransaction();
            }

            Product::with('lots')->chunk($chunkSize, function ($products) use (&$processedProducts, &$balancedProducts, &$skippedProducts, &$createdLots, $progressBar, $isDryRun) {
                foreach ($products as $product) {
                    $processedProducts++;

                    $currentLotStock = $product->lots->sum('quantity');
                    $productStock = $product->stock ?? 0;

                    $difference = $productStock - $currentLotStock;

                    if ($difference <= 0) {
                        $skippedProducts++;
                        if ($difference < 0) {
                            $this->warn("Product ID {$product->id}: Lot stock ({$currentLotStock}) is higher than product stock ({$productStock})", 'v');
                        }
                    } else {
                        $lotData = $this->prepareLotData($product, $difference);

                        if ($isDryRun) {
                            $this->info("DRY RUN - Would create lot for Product ID {$product->id}: {$difference} units", 'v');
                        } else {
                            ProductLot::create($lotData);
                            $this->info("Product ID {$product->id}: Created lot with {$difference} units (Lot #{$lotData['lot_number']})", 'v');
                        }

                        $balancedProducts++;
                        $createdLots++;
                    }

                    $progressBar->advance();
                }
            });

            if (!$isDryRun) {
                DB::commit();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info('Stock balancing completed successfully!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Products Processed', $processedProducts],
                    ['Products Balanced', $balancedProducts],
                    ['Products Skipped', $skippedProducts],
                    ['Lots Created', $createdLots],
                ]
            );

            if ($isDryRun) {
                $this->warn('This was a dry run - no actual changes were made');
                $this->info('Run without --dry-run to apply changes');
            }

        } catch (\Exception $e) {
            if (!$isDryRun) {
                DB::rollBack();
            }
            $progressBar->finish();
            $this->newLine();
            $this->error('An error occurred during stock balancing: ' . $e->getMessage());
            if (!$isDryRun) {
                $this->error('Transaction rolled back. No changes were made.');
            }
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Prepare lot data for creation
     *
     * @param Product $product
     * @param int $quantity
     * @return array
     */
    private function prepareLotData(Product $product, int $quantity): array
    {
        return [
            'product_id' => $product->id,
            'supplier_id' => null,
            'lot_number' => $this->generateUniqueLotNumber($product),
            'expiration_date' => $this->getExpirationDate($product),
            'quantity' => $quantity,
            'location' => null,
            'unit_cost' => $product->unit_cost ?? 0,
        ];
    }

    /**
     * Generate a unique 5-digit lot number for the product
     *
     * @param Product $product
     * @return string
     */
    private function generateUniqueLotNumber(Product $product): string
    {
        $maxAttempts = 100;
        $attempts = 0;

        do {
            $lotNumber = str_pad(random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
            $exists = $product->lots()->where('lot_number', $lotNumber)->exists();
            $attempts++;

            if ($attempts >= $maxAttempts) {
                $lotNumber = substr(time() . random_int(100, 999), -5);
                break;
            }
        } while ($exists);

        return $lotNumber;
    }

    /**
     * Get expiration date based on the most recent lot
     *
     * @param Product $product
     * @return Carbon|null
     */
    private function getExpirationDate(Product $product): ?Carbon
    {
        $mostRecentLot = $product->lots()
            ->whereNotNull('expiration_date')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($mostRecentLot && $mostRecentLot->expiration_date) {
            return Carbon::parse($mostRecentLot->expiration_date);
        }

        return Carbon::now()->addYear();
    }
}
