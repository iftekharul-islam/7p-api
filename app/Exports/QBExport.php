<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class QBExport implements FromArray, ShouldAutoSize
{
    use Exportable;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Retrieve the data to be exported.
     *
     * @return array
     */
    public function array(): array
    {
        info(gettype($this->data));
        return $this->data;
    }
}
