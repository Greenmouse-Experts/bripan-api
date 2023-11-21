<?php

namespace App\Imports;

use App\Models\Membership;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MembershipsImport implements ToModel, WithHeadingRow
{
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        //
    }

    public function model(array $row)
    {
        // Convert all keys to lowercase
        $row = array_change_key_case($row, CASE_LOWER);

        return new Membership([
            'name' => $row['name'],  // Adjust to lowercase
            'title' => $row['title'],  // Adjust to lowercase
        ]);
    }
}
