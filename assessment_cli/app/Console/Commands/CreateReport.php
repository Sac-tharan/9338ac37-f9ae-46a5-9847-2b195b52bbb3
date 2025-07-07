<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreateReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

     protected $signature = 'json:open {filename}';

     protected $description = 'Open and display JSON file contents';

     public function handle()
     {
         $filename = $this->argument('filename');

         $data = $this->openJsonFile($filename);

         if (is_string($data)) {
             // Means error message returned
             $this->error($data);
             return 1; // Error exit code
         }

         // Pretty print JSON data in console
         $this->info(json_encode($data, JSON_PRETTY_PRINT));

         return 0; // Success exit code
     }

     private function openJsonFile(string $filename)
     {
         $path = storage_path("app/data/{$filename}");
         if (!file_exists($path)) {
             return "File {$filename} not found!";
         }

         $json = file_get_contents($path);
         $data = json_decode($json, true);

         if ($data === null) {
             return "Invalid JSON in file {$filename}!";
         }

         return $data;
     }


}
