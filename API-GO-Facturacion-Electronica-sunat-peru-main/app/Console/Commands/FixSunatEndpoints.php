<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;

class FixSunatEndpoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sunat:fix-endpoints';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrects the SUNAT beta and production endpoints for all companies in the database.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $correctBetaEndpoint = 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService';
        $correctProdEndpoint = 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService';

        $this->info('Starting endpoint correction process...');

        $companies = Company::all();
        $updatedCount = 0;

        if ($companies->isEmpty()) {
            $this->info('No companies found in the database.');
            return 0;
        }

        foreach ($companies as $company) {
            $needsUpdate = false;
            $messages = [];

            if ($company->endpoint_beta !== $correctBetaEndpoint) {
                $messages[] = "Incorrect beta endpoint found.";
                $company->endpoint_beta = $correctBetaEndpoint;
                $needsUpdate = true;
            }

            if ($company->endpoint_produccion !== $correctProdEndpoint) {
                $messages[] = "Incorrect production endpoint found.";
                $company->endpoint_produccion = $correctProdEndpoint;
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $this->line("Updating company: {$company->razon_social} (ID: {$company->id})");
                foreach ($messages as $message) {
                    $this->line("  - {$message}");
                }
                $company->save();
                $updatedCount++;
                $this->info("  -> Endpoints corrected successfully.");
            } else {
                $this->line("Company '{$company->razon_social}' (ID: {$company->id}) already has correct endpoints. Skipping.");
            }
        }

        if ($updatedCount > 0) {
            $this->info("Process finished. {$updatedCount} companies were updated.");
        } else {
            $this->info('Process finished. All companies already had the correct endpoints.');
        }

        return 0;
    }
}
