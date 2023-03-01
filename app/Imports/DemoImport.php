<?php

namespace App\Imports;

use Milon\Barcode\DNS1D;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class DemoImport implements ToCollection, WithChunkReading, ShouldQueue
{
    /**
     * Set the chunk size
     *
     * @return int
     */
    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * @param Collection $collection
     */
    public function collection(Collection $rows)
    {
        $rows->shift();
        $rows->filter();

        try {
            DB::beginTransaction();

            DB::commit();

            return "All good";
        } catch (\Exception $e) {
            DB::rollback();
            dd($e->getMessage());
            // something went wrong
        }
    }
}
