<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateProductStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:calculate-stock {--chunk=100 : Number of products to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and update product stock based on the sum of lot quantities';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting product stock calculation...');

        $chunkSize = $this->option('chunk');
        $totalProducts = Product::count();
        $processedProducts = 0;
        $updatedProducts = 0;
        $skippedProducts = 0;

        $this->info("Processing {$totalProducts} products in chunks of {$chunkSize}...");

        $progressBar = $this->output->createProgressBar($totalProducts);
        $progressBar->start();

        try {
            DB::beginTransaction();

            Product::with('lots')->chunk($chunkSize, function ($products) use (&$processedProducts, &$updatedProducts, &$skippedProducts, $progressBar) {
                foreach ($products as $product) {
                    $processedProducts++;

                    if ($product->lots->isEmpty()) {
                        $skippedProducts++;
                        $this->warn("Product ID {$product->id} has no lots. Skipping...", 'v');
                    } else {
                        $totalStock = $product->lots->sum('quantity');

                        $product->update(['stock' => $totalStock]);

                        $updatedProducts++;
                        $this->info("Product ID {$product->id}: Updated stock to {$totalStock}", 'v');
                    }

                    $progressBar->advance();
                }
            });

            DB::commit();

            $progressBar->finish();
            $this->newLine(2);

            $this->info('Stock calculation completed successfully!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Products Processed', $processedProducts],
                    ['Products Updated', $updatedProducts],
                    ['Products Skipped (No Lots)', $skippedProducts],
                ]
            );

        } catch (\Exception $e) {
            DB::rollBack();
            $progressBar->finish();
            $this->newLine();
            $this->error('An error occurred during stock calculation: ' . $e->getMessage());
            $this->error('Transaction rolled back. No changes were made.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
